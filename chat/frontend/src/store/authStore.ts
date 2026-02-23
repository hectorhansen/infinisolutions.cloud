import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import api from '../services/api'
import { connectSocket, disconnectSocket } from '../services/socket'

interface User {
    id: number
    name: string
    email: string
    role: 'admin' | 'agent'
    status: 'online' | 'offline' | 'away'
    avatar?: string
}

interface AuthState {
    user: User | null
    token: string | null
    refreshToken: string | null
    login: (email: string, password: string) => Promise<void>
    logout: () => void
    setTokens: (token: string, refreshToken: string) => void
    setStatus: (status: User['status']) => Promise<void>
}

export const useAuthStore = create<AuthState>()(
    persist(
        (set, get) => ({
            user: null,
            token: null,
            refreshToken: null,

            login: async (email, password) => {
                const { data } = await api.post('/auth/login', { email, password })
                set({ user: data.user, token: data.token, refreshToken: data.refreshToken })
                connectSocket()
            },

            logout: async () => {
                try {
                    await api.post('/auth/logout', { refreshToken: get().refreshToken })
                } catch { }
                disconnectSocket()
                set({ user: null, token: null, refreshToken: null })
            },

            setTokens: (token, refreshToken) => {
                set({ token, refreshToken })
            },

            setStatus: async (status) => {
                await api.patch('/auth/status', { status })
                set((s) => ({ user: s.user ? { ...s.user, status } : null }))
            },
        }),
        { name: 'nucleofix-auth', partialize: (s) => ({ token: s.token, refreshToken: s.refreshToken, user: s.user }) }
    )
)
