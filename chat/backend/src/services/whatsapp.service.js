'use strict';
const fetch = require('node-fetch');
const db = require('../config/db');
const logger = require('../config/logger');

const VERSION = () => process.env.WHATSAPP_API_VERSION || 'v20.0';
const TOKEN = () => process.env.WHATSAPP_TOKEN;
const PHONE = () => process.env.WHATSAPP_PHONE_NUMBER_ID;

function baseUrl() {
    return `https://graph.facebook.com/${VERSION()}/${PHONE()}`;
}

async function callApi(endpoint, body) {
    const res = await fetch(`${baseUrl()}${endpoint}`, {
        method: 'POST',
        headers: {
            Authorization: `Bearer ${TOKEN()}`,
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(body),
    });
    const json = await res.json();
    if (!res.ok) {
        logger.error('[WhatsApp] API Error:', JSON.stringify(json));
        throw new Error(json.error?.message || 'Erro na API do WhatsApp');
    }
    return json;
}

/** Envia mensagem de texto. */
async function sendText(to, text, quotedMsgId = null) {
    const body = {
        messaging_product: 'whatsapp',
        recipient_type: 'individual',
        to,
        type: 'text',
        text: { preview_url: false, body: text },
    };
    if (quotedMsgId) {
        body.context = { message_id: quotedMsgId };
    }
    return callApi('/messages', body);
}

/** Envia mídia por URL. */
async function sendMedia(to, type, link, caption = '') {
    const body = {
        messaging_product: 'whatsapp',
        recipient_type: 'individual',
        to,
        type,
        [type]: { link, caption },
    };
    return callApi('/messages', body);
}

/** Envia documento. */
async function sendDocument(to, link, filename, caption = '') {
    return callApi('/messages', {
        messaging_product: 'whatsapp',
        recipient_type: 'individual',
        to,
        type: 'document',
        document: { link, filename, caption },
    });
}

/** Marca mensagem como lida. */
async function markAsRead(waMessageId) {
    return callApi('/messages', {
        messaging_product: 'whatsapp',
        status: 'read',
        message_id: waMessageId,
    });
}

/**
 * Obtém configurações de WhatsApp do banco de dados (para uso em runtime
 * quando o usuário configurar pelo painel Admin).
 */
async function getSettingsFromDb() {
    const rows = await db.query("SELECT `key`, `value` FROM settings WHERE `key` LIKE 'whatsapp_%'");
    const cfg = {};
    rows.forEach((r) => { cfg[r.key] = r.value; });
    return cfg;
}

module.exports = { sendText, sendMedia, sendDocument, markAsRead, getSettingsFromDb };
