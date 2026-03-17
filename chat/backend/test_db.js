require('dotenv').config({ path: __dirname + '/.env' });
const mysql = require('mysql2/promise');

async function testConnection() {
    try {
        console.log('Testando conexão estruturada ao MySQL...');
        console.log('Host:', process.env.DB_HOST);
        console.log('User:', process.env.DB_USER);
        // console.log('Pass:', process.env.DB_PASSWORD ? '******' : 'MISSING');

        const conn = await mysql.createConnection({
            host: process.env.DB_HOST || '127.0.0.1',
            user: process.env.DB_USER || 'u752688765_nucleofix',
            password: process.env.DB_PASSWORD || 'SenhaSegura2025',
            database: process.env.DB_NAME || 'u752688765_nucleofix'
        });

        await conn.execute('SELECT 1');
        console.log('--- CONEXÃO MYSQL BEM SUCEDIDA ---');
        await conn.end();
    } catch (err) {
        console.error('--- ERRO DE CONEXÃO MYSQL ---');
        console.error(err.message);
    }
}

testConnection();
