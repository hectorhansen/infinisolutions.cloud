'use strict';
const mysql = require('mysql2/promise');
const logger = require('./logger');

const pool = mysql.createPool({
    host: process.env.DB_HOST || 'localhost',
    port: Number(process.env.DB_PORT) || 3306,
    database: process.env.DB_NAME,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0,
    timezone: 'Z',
    charset: 'utf8mb4',
});

/** Executa uma query com parâmetros posicionais.
 * @param {string} sql
 * @param {any[]}  params
 */
async function query(sql, params = []) {
    const [rows] = await pool.execute(sql, params);
    return rows;
}

/** Alias semântico para queries que retornam uma única linha. */
async function queryOne(sql, params = []) {
    const rows = await query(sql, params);
    return rows[0] || null;
}

/** Helper para verificar conexão. */
async function raw(sql) {
    const [rows] = await pool.execute(sql);
    return rows;
}

/** Expõe o pool para transações manuais se necessário. */
async function getConnection() {
    return pool.getConnection();
}

module.exports = { query, queryOne, raw, getConnection, pool };
