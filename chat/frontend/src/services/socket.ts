import { io, Socket } from 'socket.io-client'
import { useAuthStore } from '../store/authStore'
import { useChatStore } from '../store/chatStore'

let socket: Socket | null = null

export function connectSocket() {
    if (socket?.connected) return socket
    const userId = useAuthStore.getState().user?.id
    if (!userId) return null

    socket = io('/', {
        path: '/chat/socket.io',
        auth: { userId },
        query: { userId },
        transports: ['websocket'],
        reconnectionDelay: 1000,
        reconnectionAttempts: 10,
    })

    socket.on('connect', () => console.log('[Socket] conectado'))
    socket.on('disconnect', (reason) => console.warn('[Socket] desconectado', reason))

    socket.on('message:new', (msg) => {
        useChatStore.getState().addMessage(msg)
    })

    socket.on('message:status', ({ wa_message_id, status }: { wa_message_id: string; status: string }) => {
        useChatStore.getState().updateMessageStatus(wa_message_id, status)
    })

    socket.on('conversation:assigned', (conv) => {
        useChatStore.getState().upsertConversation(conv)
    })

    socket.on('conversation:updated', (conv) => {
        useChatStore.getState().upsertConversation(conv)
    })

    socket.on('conversation:tag_added', ({ tagId }: { tagId: number }) => {
        // Reload conversa ativa se necessário
        const active = useChatStore.getState().activeConversationId
        if (active) useChatStore.getState().refreshConversation(active)
    })

    return socket
}

export function disconnectSocket() {
    socket?.disconnect()
    socket = null
}

export function joinConversation(id: number) {
    socket?.emit('join:conversation', id)
}

export function leaveConversation(id: number) {
    socket?.emit('leave:conversation', id)
}

export function getSocket() {
    return socket
}
