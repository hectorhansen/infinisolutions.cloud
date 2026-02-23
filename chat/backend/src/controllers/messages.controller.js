'use strict';
const path = require('path');
const db = require('../config/db');
const waService = require('../services/whatsapp.service');
const socketService = require('../services/socket.service');
const upload = require('../middlewares/upload');

// GET /messages/:conversationId
async function listMessages(req, res) {
    const { conversationId } = req.params;
    const { before, limit = 50 } = req.query;

    // Valida acesso
    const conv = await db.queryOne('SELECT * FROM conversations WHERE id = ?', [conversationId]);
    if (!conv) return res.status(404).json({ error: 'Conversa não encontrada' });
    if (req.user.role !== 'admin' && conv.agent_id !== req.user.id) {
        return res.status(403).json({ error: 'Acesso negado' });
    }

    let sql = 'SELECT * FROM messages WHERE conversation_id = ?';
    const params = [conversationId];
    if (before) { sql += ' AND id < ?'; params.push(before); }
    sql += ' ORDER BY id DESC LIMIT ?';
    params.push(Number(limit));

    const messages = await db.query(sql, params);
    res.json(messages.reverse());
}

// POST /messages/:conversationId/text
async function sendText(req, res) {
    const { conversationId } = req.params;
    const { text } = req.body;
    if (!text?.trim()) return res.status(400).json({ error: 'Texto obrigatório' });

    const conv = await db.queryOne(
        'SELECT c.*, co.phone FROM conversations c JOIN contacts co ON co.id = c.contact_id WHERE c.id = ?',
        [conversationId]
    );
    if (!conv) return res.status(404).json({ error: 'Conversa não encontrada' });

    // Envia via WhatsApp
    const waRes = await waService.sendText(conv.phone, text);

    // Salva no BD
    const result = await db.query(
        `INSERT INTO messages (conversation_id, sender_type, sender_id, type, body, wa_message_id, status)
     VALUES (?, 'agent', ?, 'text', ?, ?, 'sent')`,
        [conversationId, req.user.id, text, waRes.messages?.[0]?.id || null]
    );

    await db.query(
        'UPDATE conversations SET last_message = ?, last_msg_at = NOW(), updated_at = NOW() WHERE id = ?',
        [text, conversationId]
    );

    const saved = await db.queryOne('SELECT * FROM messages WHERE id = ?', [result.insertId]);
    socketService.toConversation(conversationId, 'message:new', saved);
    res.status(201).json(saved);
}

// POST /messages/:conversationId/media
async function sendMedia(req, res) {
    const { conversationId } = req.params;
    const file = req.file;
    if (!file) return res.status(400).json({ error: 'Arquivo obrigatório' });

    const conv = await db.queryOne(
        'SELECT c.*, co.phone FROM conversations c JOIN contacts co ON co.id = c.contact_id WHERE c.id = ?',
        [conversationId]
    );
    if (!conv) return res.status(404).json({ error: 'Conversa não encontrada' });

    const caption = req.body.caption || '';
    const agentId = req.user.id;
    const contactId = conv.contact_id;

    // URL pública
    const publicUrl = `/chat/storage/atendentes/${agentId}/${contactId}/${path.basename(file.path)}`;

    // Determina tipo WhatsApp
    const mime = file.mimetype;
    let waType = 'document';
    if (mime.startsWith('image/')) waType = 'image';
    if (mime.startsWith('video/')) waType = 'video';
    if (mime.startsWith('audio/')) waType = 'audio';

    const baseUrl = process.env.FRONTEND_URL || 'https://nucleofix.cloud';
    const fileLink = `${baseUrl}${publicUrl}`;

    let waRes;
    if (waType === 'document') {
        waRes = await waService.sendDocument(conv.phone, fileLink, file.originalname, caption);
    } else {
        waRes = await waService.sendMedia(conv.phone, waType, fileLink, caption);
    }

    const result = await db.query(
        `INSERT INTO messages (conversation_id, sender_type, sender_id, type, body, media_url, media_path, media_mime, media_size, wa_message_id, status)
     VALUES (?, 'agent', ?, ?, ?, ?, ?, ?, ?, ?, 'sent')`,
        [conversationId, agentId, waType, caption, publicUrl, file.path, mime, file.size, waRes.messages?.[0]?.id || null]
    );

    await db.query(
        'UPDATE conversations SET last_message = ?, last_msg_at = NOW(), updated_at = NOW() WHERE id = ?',
        [`[${waType}]`, conversationId]
    );

    const saved = await db.queryOne('SELECT * FROM messages WHERE id = ?', [result.insertId]);
    socketService.toConversation(conversationId, 'message:new', saved);
    res.status(201).json(saved);
}

module.exports = { listMessages, sendText, sendMedia };
