'use strict';
const db = require('../config/db');

// GET /settings
async function getSettings(_req, res) {
    const rows = await db.query('SELECT `key`, `value` FROM settings');
    const settings = {};
    rows.forEach((r) => { settings[r.key] = r.value; });
    // Não expõe o token completo no GET
    if (settings.whatsapp_token) settings.whatsapp_token = '••••••' + settings.whatsapp_token.slice(-6);
    res.json(settings);
}

// PUT /settings
async function updateSettings(req, res) {
    const allowed = [
        'whatsapp_phone_number_id',
        'whatsapp_token',
        'whatsapp_verify_token',
        'whatsapp_phone_display',
        'system_name',
    ];
    const updates = req.body;
    const promises = Object.entries(updates)
        .filter(([key]) => allowed.includes(key))
        .map(([key, value]) =>
            db.query(
                'INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?',
                [key, value, value]
            )
        );
    await Promise.all(promises);

    // Atualiza env vars em runtime
    if (updates.whatsapp_token) process.env.WHATSAPP_TOKEN = updates.whatsapp_token;
    if (updates.whatsapp_phone_number_id) process.env.WHATSAPP_PHONE_NUMBER_ID = updates.whatsapp_phone_number_id;
    if (updates.whatsapp_verify_token) process.env.WHATSAPP_VERIFY_TOKEN = updates.whatsapp_verify_token;

    res.json({ message: 'Configurações atualizadas' });
}

module.exports = { getSettings, updateSettings };
