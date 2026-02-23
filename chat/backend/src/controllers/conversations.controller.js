'use strict';
const db = require('../config/db');
const socketService = require('../services/socket.service');
const queueService = require('../services/queue.service');

// GET /conversations  (agent vê só as suas; admin vê todas)
async function listConversations(req, res) {
    const { status, search, tag } = req.query;
    const isAdmin = req.user.role === 'admin';

    let sql = `
    SELECT c.id, c.status, c.unread_count, c.last_message, c.last_msg_at,
           c.agent_id, u.name AS agent_name,
           co.id AS contact_id, co.phone, co.name AS contact_name, co.avatar,
           GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ',')  AS tags,
           GROUP_CONCAT(t.color ORDER BY t.name SEPARATOR ',') AS tag_colors
    FROM   conversations c
    JOIN   contacts co ON co.id = c.contact_id
    LEFT JOIN users u  ON u.id = c.agent_id
    LEFT JOIN conversation_tags ct ON ct.conversation_id = c.id
    LEFT JOIN tags t ON t.id = ct.tag_id
    WHERE  1=1
  `;
    const params = [];

    if (!isAdmin) {
        sql += ' AND c.agent_id = ?';
        params.push(req.user.id);
    }
    if (status) { sql += ' AND c.status = ?'; params.push(status); }
    if (search) {
        sql += ' AND (co.name LIKE ? OR co.phone LIKE ?)';
        params.push(`%${search}%`, `%${search}%`);
    }
    if (tag) {
        sql += ' AND EXISTS (SELECT 1 FROM conversation_tags ct2 JOIN tags t2 ON t2.id = ct2.tag_id WHERE ct2.conversation_id = c.id AND t2.name = ?)';
        params.push(tag);
    }

    sql += ' GROUP BY c.id ORDER BY c.last_msg_at DESC, c.created_at DESC LIMIT 200';

    const rows = await db.query(sql, params);
    res.json(rows);
}

// GET /conversations/:id
async function getConversation(req, res) {
    const conv = await db.queryOne(
        `SELECT c.*, co.phone, co.name AS contact_name, co.avatar,
            u.name AS agent_name
     FROM   conversations c
     JOIN   contacts co ON co.id = c.contact_id
     LEFT JOIN users u ON u.id = c.agent_id
     WHERE  c.id = ?`,
        [req.params.id]
    );
    if (!conv) return res.status(404).json({ error: 'Conversa não encontrada' });
    if (req.user.role !== 'admin' && conv.agent_id !== req.user.id) {
        return res.status(403).json({ error: 'Acesso negado' });
    }
    res.json(conv);
}

// PATCH /conversations/:id/assign
async function assignConversation(req, res) {
    const { agentId } = req.body;
    const { id } = req.params;
    await db.query(
        "UPDATE conversations SET agent_id = ?, status = 'open', updated_at = NOW() WHERE id = ?",
        [agentId, id]
    );
    const conv = await db.queryOne('SELECT * FROM conversations WHERE id = ?', [id]);
    socketService.emitConversationAssigned(conv);
    res.json(conv);
}

// PATCH /conversations/:id/status
async function updateStatus(req, res) {
    const { status } = req.body;
    const valid = ['open', 'closed', 'archived', 'waiting'];
    if (!valid.includes(status)) return res.status(400).json({ error: 'Status inválido' });
    await db.query('UPDATE conversations SET status = ?, updated_at = NOW() WHERE id = ?', [status, req.params.id]);
    res.json({ status });
}

// POST /conversations/:id/tags
async function addTag(req, res) {
    const { tagId } = req.body;
    await db.query(
        'INSERT IGNORE INTO conversation_tags (conversation_id, tag_id) VALUES (?, ?)',
        [req.params.id, tagId]
    );
    socketService.toConversation(req.params.id, 'conversation:tag_added', { tagId });
    res.json({ message: 'Tag adicionada' });
}

// DELETE /conversations/:id/tags/:tagId
async function removeTag(req, res) {
    await db.query(
        'DELETE FROM conversation_tags WHERE conversation_id = ? AND tag_id = ?',
        [req.params.id, req.params.tagId]
    );
    socketService.toConversation(req.params.id, 'conversation:tag_removed', { tagId: req.params.tagId });
    res.json({ message: 'Tag removida' });
}

// PUT /conversations/:id/read
async function markRead(req, res) {
    await db.query('UPDATE conversations SET unread_count = 0 WHERE id = ?', [req.params.id]);
    res.json({ unread_count: 0 });
}

// GET /conversations/stats  (admin)
async function getStats(req, res) {
    const [total] = await db.query("SELECT COUNT(*) AS n FROM conversations WHERE status <> 'archived'");
    const [open] = await db.query("SELECT COUNT(*) AS n FROM conversations WHERE status = 'open'");
    const [waiting] = await db.query("SELECT COUNT(*) AS n FROM conversations WHERE status = 'waiting'");
    const agents = await db.query("SELECT id, name, status, (SELECT COUNT(*) FROM conversations WHERE agent_id = u.id AND status = 'open') AS open_count FROM users u WHERE role = 'agent'");
    res.json({ total: total.n, open: open.n, waiting: waiting.n, agents });
}

module.exports = { listConversations, getConversation, assignConversation, updateStatus, addTag, removeTag, markRead, getStats };
