'use strict';
const fs = require('fs');
const path = require('path');
const fetch = require('node-fetch');
const logger = require('../config/logger');

const BASE = path.resolve(__dirname, '../../storage/atendentes');

/** Garante que o diretório existe. */
function ensureDir(agentId, contactId) {
    const dir = path.join(BASE, String(agentId), String(contactId));
    fs.mkdirSync(dir, { recursive: true });
    return dir;
}

/**
 * Baixa uma mídia da API do WhatsApp e salva no storage local.
 * @returns {{ localPath: string, publicUrl: string, size: number, mime: string }}
 */
async function downloadWhatsAppMedia(mediaId, agentId, contactId, mime) {
    const token = process.env.WHATSAPP_TOKEN;
    const version = process.env.WHATSAPP_API_VERSION || 'v20.0';

    // 1. Busca a URL da mídia
    const infoRes = await fetch(
        `https://graph.facebook.com/${version}/${mediaId}`,
        { headers: { Authorization: `Bearer ${token}` } }
    );
    const infoJson = await infoRes.json();
    if (!infoJson.url) throw new Error(`Falha ao obter URL da mídia ${mediaId}`);

    // 2. Baixa o arquivo
    const fileRes = await fetch(infoJson.url, {
        headers: { Authorization: `Bearer ${token}` },
    });
    if (!fileRes.ok) throw new Error(`Falha ao baixar mídia: ${fileRes.statusText}`);

    // 3. Determina extensão
    const extMap = {
        'image/jpeg': '.jpg', 'image/png': '.png', 'image/gif': '.gif', 'image/webp': '.webp',
        'video/mp4': '.mp4', 'video/3gpp': '.3gp',
        'audio/ogg': '.ogg', 'audio/mpeg': '.mp3', 'audio/mp4': '.m4a', 'audio/wav': '.wav',
        'application/pdf': '.pdf',
        'application/msword': '.doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': '.docx',
    };
    const ext = extMap[mime] || '.bin';
    const filename = `${Date.now()}_${mediaId}${ext}`;

    const dir = ensureDir(agentId, contactId);
    const localPath = path.join(dir, filename);

    // 4. Grava o arquivo
    const buffer = await fileRes.buffer();
    fs.writeFileSync(localPath, buffer);

    // URL pública relativa ao servidor
    const publicUrl = `/chat/storage/atendentes/${agentId}/${contactId}/${filename}`;

    logger.info(`[Storage] Mídia salva: ${localPath} (${buffer.length} bytes)`);

    return { localPath, publicUrl, size: buffer.length, mime };
}

module.exports = { ensureDir, downloadWhatsAppMedia };
