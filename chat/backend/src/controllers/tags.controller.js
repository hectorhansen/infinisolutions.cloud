'use strict';
const db = require('../config/db');

async function listTags(_req, res) {
    const tags = await db.query('SELECT * FROM tags ORDER BY name');
    res.json(tags);
}
async function createTag(req, res) {
    const { name, color = '#10B981' } = req.body;
    if (!name) return res.status(400).json({ error: 'Nome obrigatório' });
    const r = await db.query('INSERT INTO tags (name, color) VALUES (?, ?)', [name, color]);
    const tag = await db.queryOne('SELECT * FROM tags WHERE id = ?', [r.insertId]);
    res.status(201).json(tag);
}
async function updateTag(req, res) {
    const { name, color } = req.body;
    await db.query('UPDATE tags SET name = COALESCE(?, name), color = COALESCE(?, color) WHERE id = ?', [name, color, req.params.id]);
    const tag = await db.queryOne('SELECT * FROM tags WHERE id = ?', [req.params.id]);
    res.json(tag);
}
async function deleteTag(req, res) {
    await db.query('DELETE FROM tags WHERE id = ?', [req.params.id]);
    res.json({ message: 'Tag removida' });
}

module.exports = { listTags, createTag, updateTag, deleteTag };
