import { create } from 'zustand'
import api from '../services/api'

interface Contact { id: number; phone: string; name?: string; avatar?: string }
interface Tag { id: number; name: string; color: string }

export interface Conversation {
    id: number
    status: 'waiting' | 'open' | 'closed' | 'archived'
    unread_count: number
    last_message?: string
    last_msg_at?: string
    agent_id?: number
    agent_name?: string
    contact_id: number
    phone: string
    contact_name?: string
    avatar?: string
    tags?: string
    tag_colors?: string
}

export interface Message {
    id: number
    conversation_id: number
    sender_type: 'contact' | 'agent' | 'system'
    sender_id?: number
    type: string
    body?: string
    media_url?: string
    media_mime?: string
    media_duration?: number
    wa_message_id?: string
    status: string
    created_at: string
}

interface ChatState {
    conversations: Conversation[]
    activeConversationId: number | null
    messages: Record<number, Message[]>
    loadingMessages: boolean
    setConversations: (convs: Conversation[]) => void
    upsertConversation: (conv: Conversation) => void
    setActive: (id: number | null) => void
    fetchMessages: (convId: number, before?: number) => Promise<void>
    addMessage: (msg: Message) => void
    updateMessageStatus: (waId: string, status: string) => void
    refreshConversation: (id: number) => Promise<void>
}

export const useChatStore = create<ChatState>((set, get) => ({
    conversations: [],
    activeConversationId: null,
    messages: {},
    loadingMessages: false,

    setConversations: (conversations) => set({ conversations }),

    upsertConversation: (conv) =>
        set((s) => {
            const idx = s.conversations.findIndex((c) => c.id === conv.id)
            if (idx >= 0) {
                const updated = [...s.conversations]
                updated[idx] = { ...updated[idx], ...conv }
                return { conversations: updated }
            }
            return { conversations: [conv, ...s.conversations] }
        }),

    setActive: (id) => set({ activeConversationId: id }),

    fetchMessages: async (convId, before) => {
        set({ loadingMessages: true })
        try {
            const params = before ? { before, limit: 50 } : { limit: 50 }
            const { data } = await api.get<Message[]>(`/messages/${convId}`, { params })
            set((s) => ({
                messages: {
                    ...s.messages,
                    [convId]: before ? [...data, ...(s.messages[convId] || [])] : data,
                },
            }))
        } finally {
            set({ loadingMessages: false })
        }
    },

    addMessage: (msg) => {
        set((s) => {
            const list = s.messages[msg.conversation_id] || []
            // Evita duplicatas
            if (list.find((m) => m.id === msg.id)) return s
            return { messages: { ...s.messages, [msg.conversation_id]: [...list, msg] } }
        })
        // Atualiza last_message da conversa
        set((s) => {
            const convs = s.conversations.map((c) =>
                c.id === msg.conversation_id
                    ? {
                        ...c,
                        last_message: msg.body || `[${msg.type}]`,
                        last_msg_at: msg.created_at,
                        unread_count: msg.sender_type === 'contact' ? c.unread_count + 1 : c.unread_count,
                    }
                    : c
            )
            return { conversations: convs }
        })
    },

    updateMessageStatus: (waId, status) => {
        set((s) => {
            const newMessages = { ...s.messages }
            for (const convId in newMessages) {
                newMessages[convId] = newMessages[convId].map((m) =>
                    m.wa_message_id === waId ? { ...m, status } : m
                )
            }
            return { messages: newMessages }
        })
    },

    refreshConversation: async (id) => {
        const { data } = await api.get<Conversation>(`/conversations/${id}`)
        get().upsertConversation(data)
    },
}))
