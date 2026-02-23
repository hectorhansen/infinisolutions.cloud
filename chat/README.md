# Nucleofix Chat — Sistema de Multi-Atendimento WhatsApp

Sistema completo de atendimento via WhatsApp rodando em `nucleofix.cloud/chat`.

---

## 🗂️ Estrutura

```
chat/
├── backend/          # Node.js + Express + Socket.io
│   ├── database/
│   │   └── schema.sql         ← Importar no phpMyAdmin
│   ├── src/
│   │   ├── app.js
│   │   ├── config/
│   │   ├── controllers/
│   │   ├── middlewares/
│   │   ├── routes/
│   │   └── services/
│   ├── storage/              ← Arquivos de mídia
│   ├── .env.example
│   └── package.json
└── frontend/         # React + Vite + TypeScript + Tailwind
    └── src/
        ├── pages/    Login | Chat | Admin | Settings
        ├── store/    authStore | chatStore (Zustand)
        └── services/ api.ts | socket.ts
```

---

## ⚙️ Instalação

### 1. Banco de Dados (Hostinger/phpMyAdmin)
1. Acesse o **phpMyAdmin** da Hostinger
2. Crie um banco de dados (ex: `nucleofix_chat`)
3. Clique em **Importar** e selecione `backend/database/schema.sql`
4. Clique em **Executar**

### 2. Backend
```bash
cd backend

# Instalar dependências
npm install

# Configurar variáveis de ambiente
copy .env.example .env
# Edite o .env com suas credenciais MySQL e WhatsApp

# Iniciar servidor (produção)
npm start

# Ou em desenvolvimento (com hot-reload)
npm run dev
```

### 3. Frontend
```bash
cd frontend

# Instalar dependências
npm install

# Build para produção (gera em backend/public/)
npm run build

# Ou para desenvolvimento local (com proxy para porta 3001)
npm run dev
```

---

## 🔧 Configuração do `.env`

```env
# Banco de Dados Hostinger
DB_HOST=localhost
DB_PORT=3306
DB_NAME=nucleofix_chat
DB_USER=SEU_USUARIO
DB_PASSWORD=SUA_SENHA

# JWT (gere strings aleatórias longas)
JWT_SECRET=MINIMO_32_CARACTERES_ALEATORIOS
JWT_REFRESH_SECRET=OUTRA_STRING_ALEATORIA

# WhatsApp (preencha após obter as credenciais na Meta)
WHATSAPP_TOKEN=EAAxxxxx...
WHATSAPP_PHONE_NUMBER_ID=123456789
WHATSAPP_VERIFY_TOKEN=nucleofix_verify_2025

# URL do frontend (para CORS)
FRONTEND_URL=https://nucleofix.cloud
```

---

## 📱 Configuração do WhatsApp Business API

1. Acesse [developers.facebook.com](https://developers.facebook.com/apps/)
2. Crie um App → WhatsApp → Business
3. Obtenha o **Phone Number ID** e **Token permanente**
4. Configure o Webhook no Meta Developer Console:
   - **URL**: `https://nucleofix.cloud/chat/api/webhook`
   - **Verify Token**: o mesmo que você colocou no `.env`
   - **Campos**: `messages`, `message_status_updates`
5. Alternativamente, configure direto no painel do sistema: `/chat/settings`

---

## 🔐 Acesso Inicial

Após importar o schema, o sistema já possui um admin padrão:

| Campo | Valor |
|-------|-------|
| E-mail | `admin@nucleofix.cloud` |
| Senha | `nucleofix@2025` |

> ⚠️ **Troque a senha imediatamente** após o primeiro login!

A senha está com hash bcrypt no banco. Para resetar manualmente, rode:
```bash
node -e "const b=require('bcrypt');b.hash('NOVA_SENHA',12).then(h=>console.log(h))"
# Copie o hash e execute no phpMyAdmin:
# UPDATE users SET password='HASH_AQUI' WHERE email='admin@nucleofix.cloud';
```

---

## 🌐 Configuração no Servidor (Hostinger)

O servidor precisa rodar Node.js. Configure via **PM2**:

```bash
# Instalar PM2 globalmente
npm install -g pm2

# Na pasta backend/
pm2 start src/app.js --name nucleofix-chat
pm2 save
pm2 startup
```

Configure o **Nginx** (ou painel Hostinger) para fazer proxy reverso:

```nginx
location /chat/ {
    proxy_pass http://localhost:3001;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
}
```

---

## 📁 Storage de Mídias

Os arquivos recebidos/enviados são salvos em:
```
backend/storage/atendentes/{ID_AGENTE}/{ID_CONTATO}/
```

Exemple: `storage/atendentes/2/45/1718123456789_audio.ogg`

---

## 🚀 Funcionalidades

- ✅ Login JWT com refresh token automático
- ✅ Níveis: Agente (vê só suas conversas) e Admin (vê tudo)
- ✅ Status do agente: Online / Ausente / Offline
- ✅ Fila de espera com distribuição automática por carga
- ✅ Mensagens: Texto, Imagem, Vídeo, Áudio (player), Documentos
- ✅ Emoji picker integrado
- ✅ Respostas rápidas com atalho `/`
- ✅ Etiquetas coloridas por conversa
- ✅ Tempo real via Socket.io
- ✅ Status de entrega (enviado/entregue/lido)
- ✅ Painel Admin: métricas, agentes, histórico
- ✅ Configurações do webhook pelo painel
