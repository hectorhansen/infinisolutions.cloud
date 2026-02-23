import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import {
    Users, MessageSquare, Clock, BarChart2, ArrowLeft, UserPlus,
    Trash2, Edit2, X, Check, Loader2
} from 'lucide-react'

interface Stats {
    total: number; open: number; waiting: number
    agents: Array<{ id: number; name: string; status: string; open_count: number }>
}
interface User { id: number; name: string; email: string; role: string; status: string }
interface Conversation {
    id: number; status: string; contact_name?: string; phone: string
    agent_name?: string; last_message?: string; last_msg_at?: string
}

export default function AdminPage() {
    const navigate = useNavigate()
    const currentUser = useAuthStore((s) => s.user)

    const [tab, setTab] = useState<'overview' | 'agents' | 'conversations'>('overview')
    const [stats, setStats] = useState<Stats | null>(null)
    const [users, setUsers] = useState<User[]>([])
    const [conversations, setConversations] = useState<Conversation[]>([])
    const [showForm, setShowForm] = useState(false)
    const [editUser, setEditUser] = useState<User | null>(null)
    const [form, setForm] = useState({ name: '', email: '', password: '', role: 'agent' })
    const [saving, setSaving] = useState(false)

    useEffect(() => {
        api.get('/conversations/stats').then(({ data }) => setStats(data))
        api.get('/users').then(({ data }) => setUsers(data))
        api.get('/conversations?limit=100').then(({ data }) => setConversations(data))
    }, [])

    async function saveUser() {
        setSaving(true)
        try {
            if (editUser) {
                await api.put(`/users/${editUser.id}`, form)
            } else {
                await api.post('/users', form)
            }
            const { data } = await api.get('/users')
            setUsers(data)
            setShowForm(false); setEditUser(null); setForm({ name: '', email: '', password: '', role: 'agent' })
        } finally { setSaving(false) }
    }

    async function deleteUser(id: number) {
        if (!confirm('Remover este agente?')) return
        await api.delete(`/users/${id}`)
        setUsers(users.filter((u) => u.id !== id))
    }

    const statusColor = (s: string): string => {
        const map: Record<string, string> = { online: 'bg-green-400', away: 'bg-yellow-400', offline: 'bg-gray-500' }
        return map[s] ?? 'bg-gray-500'
    }

    return (
        <div className="min-h-screen bg-chat-bg">
            {/* Header */}
            <header className="bg-chat-panel border-b border-chat-border px-6 py-4 flex items-center gap-4">
                <button onClick={() => navigate('/chat')} className="btn-ghost p-2">
                    <ArrowLeft className="w-5 h-5" />
                </button>
                <div>
                    <h1 className="text-lg font-bold text-white">Painel Administrativo</h1>
                    <p className="text-xs text-gray-400">Nucleofix Chat · {currentUser?.name}</p>
                </div>
            </header>

            <div className="max-w-6xl mx-auto px-6 py-6">
                {/* Cards de métricas */}
                {stats && (
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        {[
                            { label: 'Total', value: stats.total, icon: MessageSquare, color: 'text-blue-400' },
                            { label: 'Abertos', value: stats.open, icon: BarChart2, color: 'text-green-400' },
                            { label: 'Na Fila', value: stats.waiting, icon: Clock, color: 'text-yellow-400' },
                            { label: 'Agentes', value: stats.agents?.length || 0, icon: Users, color: 'text-brand-400' },
                        ].map((card) => (
                            <div key={card.label} className="card p-4">
                                <div className="flex items-center gap-3">
                                    <card.icon className={`w-8 h-8 ${card.color}`} />
                                    <div>
                                        <p className="text-2xl font-bold text-white">{card.value}</p>
                                        <p className="text-xs text-gray-400">{card.label}</p>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* Tabs */}
                <div className="flex gap-1 mb-6 border-b border-chat-border">
                    {(['overview', 'agents', 'conversations'] as const).map((t) => (
                        <button key={t} onClick={() => setTab(t)}
                            className={`px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px ${tab === t ? 'border-brand-500 text-white' : 'border-transparent text-gray-400 hover:text-white'}`}>
                            {t === 'overview' ? 'Visão Geral' : t === 'agents' ? 'Agentes' : 'Conversas'}
                        </button>
                    ))}
                </div>

                {/* Overview Tab */}
                {tab === 'overview' && stats && (
                    <div className="grid md:grid-cols-2 gap-4">
                        <div className="card p-5">
                            <h3 className="text-sm font-semibold text-white mb-3 flex items-center gap-2"><Users className="w-4 h-4 text-brand-400" />Agentes Online</h3>
                            <div className="space-y-2">
                                {stats.agents?.map((a) => (
                                    <div key={a.id} className="flex items-center gap-3 py-1">
                                        <div className="w-8 h-8 rounded-full bg-brand-800 flex items-center justify-center text-xs text-white font-bold">{a.name.charAt(0)}</div>
                                        <div className="flex-1">
                                            <p className="text-sm text-white">{a.name}</p>
                                            <p className="text-xs text-gray-500">{a.open_count} conversa(s) ativa(s)</p>
                                        </div>
                                        <div className={`w-2.5 h-2.5 rounded-full ${statusColor(a.status)}`} />
                                    </div>
                                ))}
                            </div>
                        </div>
                        <div className="card p-5">
                            <h3 className="text-sm font-semibold text-white mb-3 flex items-center gap-2"><Clock className="w-4 h-4 text-yellow-400" />Fila de Espera ({stats.waiting})</h3>
                            <div className="space-y-2">
                                {conversations.filter((c) => c.status === 'waiting').slice(0, 8).map((c) => (
                                    <div key={c.id} className="flex items-center gap-2 py-1">
                                        <div className="w-1 h-8 bg-yellow-400 rounded-full" />
                                        <div>
                                            <p className="text-sm text-white">{c.contact_name || c.phone}</p>
                                            <p className="text-xs text-gray-500">{c.last_message?.slice(0, 40) || '—'}</p>
                                        </div>
                                    </div>
                                ))}
                                {conversations.filter((c) => c.status === 'waiting').length === 0 && (
                                    <p className="text-sm text-gray-500">Nenhuma conversa aguardando</p>
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {/* Agents Tab */}
                {tab === 'agents' && (
                    <div>
                        <div className="flex justify-end mb-4">
                            <button onClick={() => { setShowForm(true); setEditUser(null); setForm({ name: '', email: '', password: '', role: 'agent' }) }}
                                className="btn-primary flex items-center gap-2">
                                <UserPlus className="w-4 h-4" />Novo Agente
                            </button>
                        </div>

                        {showForm && (
                            <div className="card p-5 mb-4 animate-slide-up">
                                <h3 className="text-sm font-semibold text-white mb-4">{editUser ? 'Editar Agente' : 'Novo Agente'}</h3>
                                <div className="grid grid-cols-2 gap-3">
                                    <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Nome" className="input-base" />
                                    <input value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} placeholder="E-mail" type="email" className="input-base" />
                                    <input value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} placeholder={editUser ? 'Nova senha (opcional)' : 'Senha'} type="password" className="input-base" />
                                    <select value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value })} className="input-base">
                                        <option value="agent">Agente</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                                <div className="flex gap-2 mt-3 justify-end">
                                    <button onClick={() => setShowForm(false)} className="btn-ghost flex items-center gap-1"><X className="w-4 h-4" />Cancelar</button>
                                    <button onClick={saveUser} disabled={saving} className="btn-primary flex items-center gap-1">
                                        {saving ? <Loader2 className="w-4 h-4 animate-spin" /> : <Check className="w-4 h-4" />}Salvar
                                    </button>
                                </div>
                            </div>
                        )}

                        <div className="card overflow-hidden">
                            <table className="w-full text-sm">
                                <thead><tr className="border-b border-chat-border">{['Nome', 'E-mail', 'Papel', 'Status', 'Ações'].map((h) => <th key={h} className="text-left px-4 py-3 text-xs text-gray-400 font-medium">{h}</th>)}</tr></thead>
                                <tbody>
                                    {users.map((u) => (
                                        <tr key={u.id} className="border-b border-chat-border/50 hover:bg-chat-hover transition-colors">
                                            <td className="px-4 py-3 text-white font-medium">{u.name}</td>
                                            <td className="px-4 py-3 text-gray-400">{u.email}</td>
                                            <td className="px-4 py-3"><span className={`text-xs px-2 py-0.5 rounded-full ${u.role === 'admin' ? 'bg-brand-900/60 text-brand-300' : 'bg-chat-hover text-gray-300'}`}>{u.role}</span></td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-1.5">
                                                    <div className={`w-2 h-2 rounded-full ${statusColor(u.status)}`} />
                                                    <span className="text-gray-400 text-xs">{u.status}</span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex gap-1">
                                                    <button onClick={() => { setEditUser(u); setForm({ name: u.name, email: u.email, password: '', role: u.role }); setShowForm(true) }} className="btn-ghost p-1.5"><Edit2 className="w-3.5 h-3.5" /></button>
                                                    {u.id !== currentUser?.id && <button onClick={() => deleteUser(u.id)} className="btn-ghost p-1.5 text-red-400 hover:text-red-300"><Trash2 className="w-3.5 h-3.5" /></button>}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Conversations Tab */}
                {tab === 'conversations' && (
                    <div className="card overflow-hidden">
                        <table className="w-full text-sm">
                            <thead><tr className="border-b border-chat-border">{['Contato', 'Agente', 'Status', 'Última mensagem', 'Data'].map((h) => <th key={h} className="text-left px-4 py-3 text-xs text-gray-400 font-medium">{h}</th>)}</tr></thead>
                            <tbody>
                                {conversations.map((c) => (
                                    <tr key={c.id} className="border-b border-chat-border/50 hover:bg-chat-hover transition-colors cursor-pointer"
                                        onClick={() => navigate('/chat')}>
                                        <td className="px-4 py-3 text-white">{c.contact_name || c.phone}</td>
                                        <td className="px-4 py-3 text-gray-400">{c.agent_name || <span className="text-yellow-500 text-xs">na fila</span>}</td>
                                        <td className="px-4 py-3"><span className={`text-xs px-2 py-0.5 rounded-full ${c.status === 'open' ? 'bg-green-900/40 text-green-400' : c.status === 'waiting' ? 'bg-yellow-900/40 text-yellow-400' : 'bg-gray-800 text-gray-400'}`}>{c.status}</span></td>
                                        <td className="px-4 py-3 text-gray-400 max-w-[200px] truncate">{c.last_message || '—'}</td>
                                        <td className="px-4 py-3 text-gray-500 text-xs">{c.last_msg_at ? new Date(c.last_msg_at).toLocaleDateString('pt-BR') : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    )
}
