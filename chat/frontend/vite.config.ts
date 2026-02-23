import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
    plugins: [react()],
    base: '/chat/',
    server: {
        port: 5173,
        proxy: {
            '/chat/api': {
                target: 'http://localhost:3001',
                changeOrigin: true,
            },
            '/chat/socket.io': {
                target: 'http://localhost:3001',
                ws: true,
                changeOrigin: true,
            },
        },
    },
    build: {
        outDir: '../backend/public',
        emptyOutDir: true,
    },
})
