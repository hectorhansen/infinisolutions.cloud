const mysql = require('mysql2/promise');

async function updatePassword() {
    try {
        const conn = await mysql.createConnection({
            host: '127.0.0.1',
            user: 'u752688765_nucleofix',
            password: 'SenhaSegura2025',
            database: 'u752688765_nucleofix'
        });

        // Hash do bcryptjs para "nucleofix@2025"
        const newHash = '$2a$12$7GXes8VFEyN.ZHrXQj9VMOOILZ/9HoBMNV1z3QU3WxtGF8QLb/.Zq';

        await conn.execute(
            'UPDATE users SET password = ? WHERE email = ?',
            [newHash, 'admin@nucleofix.cloud']
        );

        console.log('Senha atualizada com sucesso!');
        await conn.end();
    } catch (error) {
        console.error('Erro:', error);
    }
}

updatePassword();
