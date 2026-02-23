'use strict';
const jwt = require('jsonwebtoken');
const db = require('../config/db');

/**
 * Middleware de autenticação JWT.
 * Injeta req.user com { id, name, email, role, status }.
 */
async function authMiddleware(req, res, next) {
    try {
        const authHeader = req.headers.authorization;
        if (!authHeader || !authHeader.startsWith('Bearer ')) {
            return res.status(401).json({ error: 'Token não fornecido' });
        }

        const token = authHeader.split(' ')[1];
        const payload = jwt.verify(token, process.env.JWT_SECRET);

        const user = await db.queryOne(
            'SELECT id, name, email, role, status FROM users WHERE id = ?',
            [payload.sub]
        );
        if (!user) return res.status(401).json({ error: 'Usuário não encontrado' });

        req.user = user;
        next();
    } catch (err) {
        if (err.name === 'TokenExpiredError') {
            return res.status(401).json({ error: 'Token expirado', code: 'TOKEN_EXPIRED' });
        }
        return res.status(401).json({ error: 'Token inválido' });
    }
}

module.exports = authMiddleware;
