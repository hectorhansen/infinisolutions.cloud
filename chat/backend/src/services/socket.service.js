'use strict';
/**
 * Socket.io Service — gerencia emissão de eventos em tempo real.
 * Inicializado em app.js com io.init(io).
 * Usado pelos controllers e pelo webhook para notificar clientes.
 */

let _io = null;

// Mapa userId → socketId (para envio direcionado)
const userSockets = new Map();

function init(io) {
    _io = io;

    io.use((socket, next) => {
        // Autenticação básica via handshake query: ?userId=X
        const userId = socket.handshake.auth?.userId || socket.handshake.query?.userId;
        if (!userId) return next(new Error('userId obrigatório'));
        socket.userId = Number(userId);
        next();
    });

    io.on('connection', (socket) => {
        const { userId } = socket;
        userSockets.set(userId, socket.id);

        socket.on('disconnect', () => {
            userSockets.delete(userId);
        });

        // Entrar em sala de conversa para receber msgs em tempo real
        socket.on('join:conversation', (conversationId) => {
            socket.join(`conv:${conversationId}`);
        });

        socket.on('leave:conversation', (conversationId) => {
            socket.leave(`conv:${conversationId}`);
        });
    });
}

/** Emite evento para todos os clientes conectados. */
function broadcast(event, data) {
    if (!_io) return;
    _io.emit(event, data);
}

/** Emite evento para sala de uma conversa específica. */
function toConversation(conversationId, event, data) {
    if (!_io) return;
    _io.to(`conv:${conversationId}`).emit(event, data);
}

/** Emite evento para um usuário específico. */
function toUser(userId, event, data) {
    if (!_io) return;
    const socketId = userSockets.get(Number(userId));
    if (socketId) _io.to(socketId).emit(event, data);
}

/** Emite nova mensagem para a sala da conversa e para o agente. */
function emitNewMessage(message, agentId) {
    toConversation(message.conversation_id, 'message:new', message);
    if (agentId) toUser(agentId, 'message:new', message);
}

/** Notifica que uma conversa foi atribuída a um agente. */
function emitConversationAssigned(conversation) {
    if (conversation.agent_id) {
        toUser(conversation.agent_id, 'conversation:assigned', conversation);
    }
    broadcast('conversation:updated', conversation);
}

module.exports = { init, broadcast, toConversation, toUser, emitNewMessage, emitConversationAssigned };
