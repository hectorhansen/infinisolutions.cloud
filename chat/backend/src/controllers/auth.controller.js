'use strict';
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const db = require('../config/db');

function generateTokens(user) {
    const payload = { sub: user.id, role: user.role };
    const access = jwt.sign(payload, process.env.JWT_SECRET, { expiresIn: process.env.JWT_EXPIRES_IN || '8h' });
    const refresh = jwt.sign(payload, process.env.JWT_REFRESH_SECRET, { expiresIn: process.env.JWT_REFRESH_EXPIRES_IN || '7d' });
    return { access, refresh };
}

// POST /auth/login
async function login(req, res) {
    const { email, password } = req.body;
    if (!email || !password) return res.status(400).json({ error: 'Email e senha obrigatórios' });

    const user = await db.queryOne('SELECT * FROM users WHERE email = ?', [email]);
    if (!user) return res.status(401).json({ error: 'Credenciais inválidas' });

    const match = await bcrypt.compare(password, user.password);
    if (!match) return res.status(401).json({ error: 'Credenciais inválidas' });

    const { access, refresh } = generateTokens(user);

    // Salva refresh token
    const expiresAt = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000);
    await db.query(
        'INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?, ?, ?)',
        [user.id, refresh, expiresAt]
    );

    // Atualiza status para online
    await db.query("UPDATE users SET status = 'online' WHERE id = ?", [user.id]);

    res.json({
        token: access,
        refreshToken: refresh,
        user: {
            id: user.id,
            name: user.name,
            email: user.email,
            role: user.role,
            status: 'online',
            avatar: user.avatar,
        },
    });
}

// POST /auth/refresh
async function refresh(req, res) {
    const { refreshToken } = req.body;
    if (!refreshToken) return res.status(400).json({ error: 'Refresh token obrigatório' });

    try {
        const payload = jwt.verify(refreshToken, process.env.JWT_REFRESH_SECRET);
        const stored = await db.queryOne(
            'SELECT * FROM refresh_tokens WHERE token = ? AND expires_at > NOW()',
            [refreshToken]
        );
        if (!stored) return res.status(401).json({ error: 'Refresh token inválido ou expirado' });

        const user = await db.queryOne('SELECT * FROM users WHERE id = ?', [payload.sub]);
        if (!user) return res.status(401).json({ error: 'Usuário não encontrado' });

        const { access, refresh: newRefresh } = generateTokens(user);

        // Rotaciona o refresh token
        await db.query('DELETE FROM refresh_tokens WHERE token = ?', [refreshToken]);
        const expiresAt = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000);
        await db.query(
            'INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?, ?, ?)',
            [user.id, newRefresh, expiresAt]
        );

        res.json({ token: access, refreshToken: newRefresh });
    } catch {
        return res.status(401).json({ error: 'Refresh token inválido' });
    }
}

// POST /auth/logout
async function logout(req, res) {
    const { refreshToken } = req.body;
    if (refreshToken) await db.query('DELETE FROM refresh_tokens WHERE token = ?', [refreshToken]);
    await db.query("UPDATE users SET status = 'offline' WHERE id = ?", [req.user.id]);
    res.json({ message: 'Logout realizado' });
}

// GET /auth/me
async function me(req, res) {
    const user = await db.queryOne(
        'SELECT id, name, email, role, status, avatar FROM users WHERE id = ?',
        [req.user.id]
    );
    res.json(user);
}

// PATCH /auth/status
async function updateStatus(req, res) {
    const { status } = req.body;
    const valid = ['online', 'offline', 'away'];
    if (!valid.includes(status)) return res.status(400).json({ error: 'Status inválido' });
    await db.query('UPDATE users SET status = ? WHERE id = ?', [status, req.user.id]);
    res.json({ status });
}

module.exports = { login, refresh, logout, me, updateStatus };
