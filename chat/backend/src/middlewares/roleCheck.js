'use strict';

/** Retorna um middleware que exige um dos papéis informados. */
function requireRole(...roles) {
    return (req, res, next) => {
        if (!req.user) return res.status(401).json({ error: 'Não autenticado' });
        if (!roles.includes(req.user.role)) {
            return res.status(403).json({ error: 'Acesso negado' });
        }
        next();
    };
}

const isAdmin = requireRole('admin');
const isAgent = requireRole('admin', 'agent');

module.exports = { requireRole, isAdmin, isAgent };
