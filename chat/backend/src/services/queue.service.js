'use strict';
const db = require('../config/db');
const socketService = require('./socket.service');
const logger = require('../config/logger');

/**
 * QueueService — distribui conversas automaticamente para agentes online.
 */

/**
 * Atribui uma conversa ao agente online com menor carga de trabalho.
 * Se não houver agentes online, a conversa fica em status 'waiting'.
 */
async function assignConversation(conversationId) {
    // Busca agentes online ordenados pela quantidade de conversas abertas (menor carga)
    const agents = await db.query(`
    SELECT u.id,
           COUNT(c.id) AS open_count
    FROM   users u
    LEFT JOIN conversations c ON c.agent_id = u.id AND c.status = 'open'
    WHERE  u.role   = 'agent'
    AND    u.status = 'online'
    GROUP  BY u.id
    ORDER  BY open_count ASC
  `);

    if (!agents.length) {
        logger.info(`[Queue] Nenhum agente online. Conversa ${conversationId} em espera.`);
        return null;
    }

    // Dentre os agentes de menor carga, sorteia aleatoriamente (anti-concentração)
    const minLoad = agents[0].open_count;
    const eligible = agents.filter((a) => a.open_count === minLoad);
    const chosen = eligible[Math.floor(Math.random() * eligible.length)];

    await db.query(
        `UPDATE conversations SET agent_id = ?, status = 'open', updated_at = NOW() WHERE id = ?`,
        [chosen.id, conversationId]
    );

    const conversation = await db.queryOne(
        `SELECT c.*, co.phone, co.name AS contact_name
     FROM   conversations c
     JOIN   contacts co ON co.id = c.contact_id
     WHERE  c.id = ?`,
        [conversationId]
    );

    socketService.emitConversationAssigned(conversation);
    logger.info(`[Queue] Conversa ${conversationId} → Agente ${chosen.id}`);
    return conversation;
}

/**
 * Retorna ou cria uma conversa para um número de telefone.
 * Se for nova, dispara a distribuição automática.
 */
async function getOrCreateConversation(phone, contactName = null) {
    // Busca ou cria contato
    let contact = await db.queryOne('SELECT * FROM contacts WHERE phone = ?', [phone]);
    if (!contact) {
        const result = await db.query(
            'INSERT INTO contacts (phone, name) VALUES (?, ?)',
            [phone, contactName || phone]
        );
        contact = await db.queryOne('SELECT * FROM contacts WHERE id = ?', [result.insertId]);
    } else if (contactName && contactName !== phone && !contact.name) {
        await db.query('UPDATE contacts SET name = ? WHERE id = ?', [contactName, contact.id]);
        contact.name = contactName;
    }

    // Busca conversa aberta ou em espera para este contato
    let conversation = await db.queryOne(
        `SELECT * FROM conversations WHERE contact_id = ? AND status IN ('open', 'waiting') ORDER BY created_at DESC LIMIT 1`,
        [contact.id]
    );

    if (!conversation) {
        // Nova conversa → entra na fila
        const result = await db.query(
            `INSERT INTO conversations (contact_id, status) VALUES (?, 'waiting')`,
            [contact.id]
        );
        conversation = await db.queryOne('SELECT * FROM conversations WHERE id = ?', [result.insertId]);
        await assignConversation(conversation.id);
        conversation = await db.queryOne('SELECT * FROM conversations WHERE id = ?', [conversation.id]);
    }

    return { contact, conversation };
}

module.exports = { assignConversation, getOrCreateConversation };
