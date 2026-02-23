'use strict';
const db = require('../config/db');

async function listQuickReplies(_req, res) {
    const replies = await db.query('SELECT * FROM quick_replies ORDER BY shortcut');
    res.json(replies);
}
async function createQuickReply(req, res) {
    const { shortcut, title, body } = req.body;
    if (!shortcut || !body) return res.status(400).json({ error: 'shortcut e body obrigatórios' });
    const r = await db.query(
        'INSERT INTO quick_replies (shortcut, title, body, created_by) VALUES (?, ?, ?, ?)',
        [shortcut, title || shortcut, body, req.user.id]
    );
    const reply = await db.queryOne('SELECT * FROM quick_replies WHERE id = ?', [r.insertId]);
    res.status(201).json(reply);
}
async function updateQuickReply(req, res) {
    const { shortcut, title, body } = req.body;
    await db.query(
        'UPDATE quick_replies SET shortcut = COALESCE(?, shortcut), title = COALESCE(?, title), body = COALESCE(?, body) WHERE id = ?',
        [shortcut, title, body, req.params.id]
    );
    res.json(await db.queryOne('SELECT * FROM quick_replies WHERE id = ?', [req.params.id]));
}
async function deleteQuickReply(req, res) {
    await db.query('DELETE FROM quick_replies WHERE id = ?', [req.params.id]);
    res.json({ message: 'Resposta rápida removida' });
}

module.exports = { listQuickReplies, createQuickReply, updateQuickReply, deleteQuickReply };
