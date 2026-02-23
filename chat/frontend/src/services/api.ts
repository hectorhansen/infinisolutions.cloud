import axios from 'axios'
import { useAuthStore } from '../store/authStore'

const api = axios.create({
    baseURL: '/chat/api',
    timeout: 30_000,
})

// Injeta Bearer token em cada request
api.interceptors.request.use((config) => {
    const token = useAuthStore.getState().token
    if (token) config.headers.Authorization = `Bearer ${token}`
    return config
})

// Refresh automático quando 401 TOKEN_EXPIRED
let isRefreshing = false
let failedQueue: Array<{ resolve: (t: string) => void; reject: (e: unknown) => void }> = []

function processQueue(error: unknown, token: string | null = null) {
    failedQueue.forEach((prom) => (token ? prom.resolve(token) : prom.reject(error)))
    failedQueue = []
}

api.interceptors.response.use(
    (res) => res,
    async (error) => {
        const original = error.config
        const data = error.response?.data

        if (error.response?.status === 401 && data?.code === 'TOKEN_EXPIRED' && !original._retry) {
            if (isRefreshing) {
                return new Promise((resolve, reject) => {
                    failedQueue.push({
                        resolve: (token: string) => { original.headers.Authorization = `Bearer ${token}`; resolve(api(original)) },
                        reject,
                    })
                })
            }
            original._retry = true
            isRefreshing = true

            const refreshToken = useAuthStore.getState().refreshToken
            try {
                const { data: tokens } = await axios.post('/chat/api/auth/refresh', { refreshToken })
                useAuthStore.getState().setTokens(tokens.token, tokens.refreshToken)
                processQueue(null, tokens.token)
                original.headers.Authorization = `Bearer ${tokens.token}`
                return api(original)
            } catch (err) {
                processQueue(err, null)
                useAuthStore.getState().logout()
                window.location.href = '/chat/login'
                return Promise.reject(err)
            } finally {
                isRefreshing = false
            }
        }

        return Promise.reject(error)
    }
)

export default api
