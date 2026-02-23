import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { useAuthStore } from './store/authStore'
import LoginPage from './pages/Login'
import ChatPage from './pages/Chat'
import AdminPage from './pages/Admin'
import SettingsPage from './pages/Settings'

function PrivateRoute({ children }: { children: React.ReactNode }) {
    const token = useAuthStore((s) => s.token)
    return token ? <>{children}</> : <Navigate to="/chat/login" replace />
}

function AdminRoute({ children }: { children: React.ReactNode }) {
    const user = useAuthStore((s) => s.user)
    const token = useAuthStore((s) => s.token)
    if (!token) return <Navigate to="/chat/login" replace />
    if (user?.role !== 'admin') return <Navigate to="/chat" replace />
    return <>{children}</>
}

export default function App() {
    return (
        <BrowserRouter>
            <Routes>
                <Route path="/chat/login" element={<LoginPage />} />
                <Route path="/chat" element={<PrivateRoute><ChatPage /></PrivateRoute>} />
                <Route path="/chat/admin" element={<AdminRoute><AdminPage /></AdminRoute>} />
                <Route path="/chat/settings" element={<AdminRoute><SettingsPage /></AdminRoute>} />
                <Route path="*" element={<Navigate to="/chat" replace />} />
            </Routes>
        </BrowserRouter>
    )
}
