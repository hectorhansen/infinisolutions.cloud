'use strict';
require('dotenv').config();
const express = require('express');
const http = require('http');
const cors = require('cors');
const helmet = require('helmet');
const morgan = require('morgan');
const path = require('path');
const { Server } = require('socket.io');

const db = require('./config/db');
const logger = require('./config/logger');
const socketService = require('./services/socket.service');

// ── Rotas ──────────────────────────────────────────────────────
const authRoutes = require('./routes/auth.routes');
const userRoutes = require('./routes/user.routes');
const conversationRoutes = require('./routes/conversation.routes');
const messageRoutes = require('./routes/message.routes');
const tagRoutes = require('./routes/tag.routes');
const quickReplyRoutes = require('./routes/quickReply.routes');
const settingsRoutes = require('./routes/settings.routes');
const webhookRoutes = require('./routes/webhook.routes');

const app = express();
const server = http.createServer(app);

// ── Socket.io ──────────────────────────────────────────────────
const io = new Server(server, {
  cors: {
    origin: process.env.FRONTEND_URL || '*',
    methods: ['GET', 'POST'],
    credentials: true,
  },
  path: '/chat/socket.io',
});
socketService.init(io);

// ── Middlewares globais ────────────────────────────────────────
app.use(helmet());
app.use(cors({
  origin: process.env.FRONTEND_URL || '*',
  credentials: true,
}));
app.use(morgan('combined', { stream: { write: (msg) => logger.info(msg.trim()) } }));

// Webhook do WhatsApp PRECISA de raw body — vem antes do json()
app.use('/chat/api/webhook', express.raw({ type: '*/*' }));
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true }));

// Storage de arquivos acessível publicamente
app.use('/chat/storage', express.static(path.join(__dirname, '..', 'storage')));

// ── Rotas da API ───────────────────────────────────────────────
app.use('/chat/api/auth', authRoutes);
app.use('/chat/api/users', userRoutes);
app.use('/chat/api/conversations', conversationRoutes);
app.use('/chat/api/messages', messageRoutes);
app.use('/chat/api/tags', tagRoutes);
app.use('/chat/api/quick-replies', quickReplyRoutes);
app.use('/chat/api/settings', settingsRoutes);
app.use('/chat/api/webhook', webhookRoutes);

// Health check
app.get('/chat/api/health', (_req, res) => res.json({ status: 'ok', ts: new Date() }));

// ── Servidor do Frontend (React SPA) ───────────────────────────
// Serve os arquivos estáticos do React no caminho /chat
app.use('/chat', express.static(path.join(__dirname, '..', 'public')));

// Qualquer outra rota dentro de /chat que não for API, devolve o React (para React Router funcionar)
app.get('/chat/*', (req, res) => {
  res.sendFile(path.join(__dirname, '..', 'public', 'index.html'));
});

// Redireciona a raiz principal para /chat
app.get('/', (req, res) => {
  res.redirect('/chat');
});

// ── Tratamento de erros global ─────────────────────────────────
// eslint-disable-next-line no-unused-vars
app.use((err, _req, res, _next) => {
  logger.error(err.stack || err.message);
  const status = err.statusCode || err.status || 500;
  res.status(status).json({ error: err.message || 'Erro interno' });
});

// ── Inicialização ──────────────────────────────────────────────
async function bootstrap() {
  try {
    await db.raw('SELECT 1');
    logger.info('✅ MySQL conectado');
  } catch (e) {
    logger.error('❌ Falha ao conectar ao MySQL:', e.message);
    process.exit(1);
  }

  const PORT = process.env.PORT || 3001;
  server.listen(PORT, () => {
    logger.info(`🚀 Servidor rodando na porta ${PORT}`);
    logger.info(`   Webhook: POST https://infinisolutions.cloud/chat/api/webhook`);
  });
}

bootstrap();
module.exports = { app, io };
