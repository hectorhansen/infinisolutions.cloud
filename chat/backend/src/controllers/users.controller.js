'use strict';
const bcrypt = require('bcrypt');
const db = require('../config/db');

// GET /users
async function listUsers(req, res) {
    const users = await db.query(
        'SELECT id, name, email, role, status, avatar, created_at FROM users ORDER BY name'
    );
    res.json(users);
}

// POST /users
async function createUser(req, res) {
    const { name, email, password, role = 'agent' } = req.body;
    if (!name || !email || !password) return res.status(400).json({ error: 'Campos obrigatórios: name, email, password' });

    const exists = await db.queryOne('SELECT id FROM users WHERE email = ?', [email]);
    if (exists) return res.status(409).json({ error: 'E-mail já cadastrado' });

    const hash = await bcrypt.hash(password, 12);
    const result = await db.query(
        'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)',
        [name, email, hash, role]
    );
    const user = await db.queryOne('SELECT id, name, email, role, status FROM users WHERE id = ?', [result.insertId]);
    res.status(201).json(user);
}

// GET /users/:id
async function getUser(req, res) {
    const user = await db.queryOne(
        'SELECT id, name, email, role, status, avatar, created_at FROM users WHERE id = ?',
        [req.params.id]
    );
    if (!user) return res.status(404).json({ error: 'Usuário não encontrado' });
    res.json(user);
}

// PUT /users/:id
async function updateUser(req, res) {
    const { name, email, password, role } = req.body;
    const { id } = req.params;

    const user = await db.queryOne('SELECT * FROM users WHERE id = ?', [id]);
    if (!user) return res.status(404).json({ error: 'Usuário não encontrado' });

    let hash = user.password;
    if (password) hash = await bcrypt.hash(password, 12);

    await db.query(
        'UPDATE users SET name = ?, email = ?, password = ?, role = ? WHERE id = ?',
        [name || user.name, email || user.email, hash, role || user.role, id]
    );
    const updated = await db.queryOne('SELECT id, name, email, role, status FROM users WHERE id = ?', [id]);
    res.json(updated);
}

// DELETE /users/:id
async function deleteUser(req, res) {
    const { id } = req.params;
    if (Number(id) === req.user.id) return res.status(400).json({ error: 'Não é possível excluir o próprio usuário' });
    await db.query('DELETE FROM users WHERE id = ?', [id]);
    res.json({ message: 'Usuário removido' });
}

module.exports = { listUsers, createUser, getUser, updateUser, deleteUser };
