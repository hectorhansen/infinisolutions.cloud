'use strict';
const multer = require('multer');
const path = require('path');
const fs = require('fs');

const MAX_SIZE = (Number(process.env.MAX_FILE_SIZE_MB) || 25) * 1024 * 1024;
const BASE = path.resolve(__dirname, '../../storage/atendentes');

/**
 * Cria storage dinâmico: /storage/atendentes/{agentId}/{contactId}/
 * O agentId e contactId são obtidos dos parâmetros da rota ou do body.
 */
const storage = multer.diskStorage({
    destination(req, _file, cb) {
        const agentId = req.user?.id || 'unknown';
        const contactId = req.params.contactId || req.body.contact_id || 'unknown';
        const dir = path.join(BASE, String(agentId), String(contactId));
        fs.mkdirSync(dir, { recursive: true });
        cb(null, dir);
    },
    filename(_req, file, cb) {
        const ext = path.extname(file.originalname);
        const base = path.basename(file.originalname, ext).replace(/\s+/g, '_').slice(0, 80);
        const ts = Date.now();
        cb(null, `${ts}_${base}${ext}`);
    },
});

const fileFilter = (_req, file, cb) => {
    const allowed = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'video/mp4', 'video/3gpp', 'video/quicktime',
        'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/wav', 'audio/webm',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
    if (allowed.includes(file.mimetype)) return cb(null, true);
    cb(new Error(`Tipo de arquivo não permitido: ${file.mimetype}`));
};

const upload = multer({ storage, fileFilter, limits: { fileSize: MAX_SIZE } });

module.exports = upload;
