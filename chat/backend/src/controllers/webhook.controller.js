'use strict';
const queueService = require('../services/queue.service');
const storageService = require('../services/storage.service');
const socketService = require('../services/socket.service');
const waService = require('../services/whatsapp.service');
const db = require('../config/db');
const logger = require('../config/logger');

// ── GET /chat/api/webhook — verificação do challenge ──────────
async function verifyWebhook(req, res) {
    const VERIFY_TOKEN = process.env.WHATSAPP_VERIFY_TOKEN;
    const mode = req.query['hub.mode'];
    const token = req.query['hub.verify_token'];
    const challenge = req.query['hub.challenge'];

    if (mode === 'subscribe' && token === VERIFY_TOKEN) {
        logger.info('[Webhook] Verificação bem-sucedida');
        return res.status(200).send(challenge);
    }
    return res.status(403).json({ error: 'Token inválido' });
}

// ── POST /chat/api/webhook — mensagens recebidas ──────────────
async function receiveWebhook(req, res) {
    // Responde imediatamente à Meta (evita timeout e re-envio)
    res.status(200).send('EVENT_RECEIVED');

    try {
        const body = JSON.parse(req.body.toString());
        if (body.object !== 'whatsapp_business_account') return;

        for (const entry of body.entry || []) {
            for (const change of entry.changes || []) {
                if (change.field !== 'messages') continue;
                const value = change.value;
                const messages = value.messages || [];
                const statuses = value.statuses || [];
                const contacts = value.contacts || [];

                // Processa atualizações de status (lido/entregue)
                for (const status of statuses) {
                    await processStatusUpdate(status);
                }

                // Processa mensagens recebidas
                for (const msg of messages) {
                    const profileName = contacts.find((c) => c.wa_id === msg.from)?.profile?.name || null;
                    await processIncomingMessage(msg, profileName);
                }
            }
        }
    } catch (err) {
        logger.error('[Webhook] Erro ao processar payload:', err.message);
    }
}

async function processStatusUpdate(status) {
    const map = { sent: 'sent', delivered: 'delivered', read: 'read', failed: 'failed' };
    const newStatus = map[status.status];
    if (!newStatus) return;

    await db.query(
        'UPDATE messages SET status = ? WHERE wa_message_id = ?',
        [newStatus, status.id]
    );
    const msg = await db.queryOne('SELECT * FROM messages WHERE wa_message_id = ?', [status.id]);
    if (msg) socketService.toConversation(msg.conversation_id, 'message:status', { wa_message_id: status.id, status: newStatus });
}

async function processIncomingMessage(msg, profileName) {
    const phone = msg.from;
    const { contact, conversation } = await queueService.getOrCreateConversation(phone, profileName);

    let type = msg.type;
    let body = null;
    let mediaUrl = null;
    let mediaPath = null;
    let mediaMime = null;
    let mediaSize = null;
    let mediaDur = null;

    // Texto simples
    if (type === 'text') {
        body = msg.text?.body || '';
    }

    // Mídia (imagem, vídeo, áudio, documento, sticker)
    const mediaTypes = ['image', 'video', 'audio', 'document', 'sticker'];
    if (mediaTypes.includes(type)) {
        const mediaPayload = msg[type];
        mediaMime = mediaPayload.mime_type;
        mediaDur = mediaPayload.duration || null;
        body = mediaPayload.caption || null;

        // Baixa e salva a mídia
        const agentId = conversation.agent_id || 'unassigned';
        try {
            const saved = await storageService.downloadWhatsAppMedia(mediaPayload.id, agentId, contact.id, mediaMime);
            mediaUrl = saved.publicUrl;
            mediaPath = saved.localPath;
            mediaSize = saved.size;
        } catch (e) {
            logger.error('[Webhook] Falha ao baixar mídia:', e.message);
        }
    }

    // Localização
    if (type === 'location') {
        const loc = msg.location;
        body = `📍 ${loc.latitude}, ${loc.longitude}${loc.name ? ' — ' + loc.name : ''}`;
    }

    // Salva mensagem no BD
    const result = await db.query(
        `INSERT INTO messages
      (conversation_id, sender_type, type, body, media_url, media_path, media_mime, media_size, media_duration, wa_message_id, status)
     VALUES (?, 'contact', ?, ?, ?, ?, ?, ?, ?, ?, 'delivered')`,
        [conversation.id, type, body, mediaUrl, mediaPath, mediaMime, mediaSize, mediaDur, msg.id]
    );

    const savedMsg = await db.queryOne('SELECT * FROM messages WHERE id = ?', [result.insertId]);

    // Atualiza conversa
    await db.query(
        `UPDATE conversations SET last_message = ?, last_msg_at = NOW(), unread_count = unread_count + 1, updated_at = NOW() WHERE id = ?`,
        [body || `[${type}]`, conversation.id]
    );

    // Marca como lida na API do WhatsApp
    await waService.markAsRead(msg.id).catch(() => { });

    // Emite evento em tempo real
    socketService.emitNewMessage(
        { ...savedMsg, contact_phone: phone, contact_name: contact.name || phone },
        conversation.agent_id
    );

    logger.info(`[Webhook] Msg de ${phone} → conversa ${conversation.id} (${type})`);
}

module.exports = { verifyWebhook, receiveWebhook };
