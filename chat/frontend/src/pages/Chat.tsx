import { useEffect, useState, useRef, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuthStore } from '../store/authStore'
import { useChatStore, type Conversation, type Message } from '../store/chatStore'
import { connectSocket, joinConversation, leaveConversation } from '../services/socket'
import api from '../services/api'
import { format, isToday, isYesterday } from 'date-fns'
import { ptBR } from 'date-fns/locale'
import {
    MessageSquare, Search, Send, Paperclip, Smile,
    Tag, Zap, LogOut, Settings, Users, Phone, X, Check, CheckCheck,
    FileText, Play, Pause
} from 'lucide-react'
import data from '@emoji-mart/data'
import Picker from '@emoji-mart/react'

/* ─── Helpers ──────────────────────────────────────────────── */
function formatDate(ds?: string) {
    if (!ds) return ''
    const d = new Date(ds)
    if (isToday(d)) return format(d, 'HH:mm')
    if (isYesterday(d)) return 'Ontem'
    return format(d, 'dd/MM', { locale: ptBR })
}

function TagBadge({ name, color }: { name: string; color: string }) {
    return (
        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium text-white" style={{ backgroundColor: color + 'cc' }}>
            {name}
        </span>
    )
}

/* ─── AudioPlayer ────────────────────────────────────────────── */
function AudioPlayer({ src }: { src: string }) {
    const [playing, setPlaying] = useState(false)
    const [progress, setProgress] = useState(0)
    const [duration, setDuration] = useState(0)
    const audioRef = useRef<HTMLAudioElement>(null)

    const toggle = () => {
        if (!audioRef.current) return
        if (playing) { audioRef.current.pause(); setPlaying(false) }
        else { audioRef.current.play(); setPlaying(true) }
    }
    const onTime = () => {
        const a = audioRef.current!
        setProgress(a.duration ? (a.currentTime / a.duration) * 100 : 0)
    }
    const onEnded = () => { setPlaying(false); setProgress(0) }

    return (
        <div className="flex items-center gap-2 min-w-[180px]">
            <audio ref={audioRef} src={src} onTimeUpdate={onTime} onLoadedMetadata={() => setDuration(audioRef.current!.duration)} onEnded={onEnded} />
            <button onClick={toggle} className="w-8 h-8 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 transition-colors flex-shrink-0">
                {playing ? <Pause className="w-4 h-4" /> : <Play className="w-4 h-4" />}
            </button>
            <div className="flex-1 h-1 bg-white/20 rounded-full overflow-hidden">
                <div className="h-full bg-white rounded-full transition-all" style={{ width: `${progress}%` }} />
            </div>
            <span className="text-xs text-white/60 w-10 text-right flex-shrink-0">
                {duration ? `${Math.ceil(duration)}s` : ''}
            </span>
        </div>
    )
}

/* ─── MessageBubble ──────────────────────────────────────────── */
function MessageBubble({ msg }: { msg: Message }) {
    const isAgent = msg.sender_type === 'agent'
    const isSystem = msg.sender_type === 'system'

    if (isSystem) {
        return (
            <div className="flex justify-center my-2">
                <span className="bg-chat-hover text-gray-400 text-xs px-3 py-1 rounded-full">{msg.body}</span>
            </div>
        )
    }

    const statusIcon = () => {
        if (!isAgent) return null
        if (msg.status === 'read') return <CheckCheck className="w-3 h-3 text-blue-400" />
        if (msg.status === 'delivered') return <CheckCheck className="w-3 h-3 text-gray-400" />
        if (msg.status === 'sent') return <Check className="w-3 h-3 text-gray-400" />
        return null
    }

    const renderContent = () => {
        const { type, media_url, body } = msg
        if (type === 'image' && media_url) return (
            <div>
                <img src={media_url} alt="imagem" className="rounded-lg max-w-[220px] max-h-[220px] object-cover cursor-pointer hover:opacity-90 transition-opacity" onClick={() => window.open(media_url, '_blank')} />
                {body && <p className="text-sm mt-1">{body}</p>}
            </div>
        )
        if (type === 'video' && media_url) return (
            <video src={media_url} controls className="rounded-lg max-w-[220px] max-h-[220px]" />
        )
        if (type === 'audio' && media_url) return <AudioPlayer src={media_url} />
        if (type === 'document' && media_url) return (
            <a href={media_url} target="_blank" rel="noreferrer" className="flex items-center gap-2 hover:opacity-80 transition-opacity">
                <FileText className="w-8 h-8 text-white/70 flex-shrink-0" />
                <span className="text-sm underline truncate max-w-[160px]">{body || 'Documento'}</span>
            </a>
        )
        return <p className="text-sm whitespace-pre-wrap">{body}</p>
    }

    return (
        <div className={`flex ${isAgent ? 'justify-end' : 'justify-start'} mb-1.5 animate-fade-in`}>
            <div className={isAgent ? 'bubble-out' : 'bubble-in'}>
                {renderContent()}
                <div className={`flex items-center gap-1 mt-1 ${isAgent ? 'justify-end' : 'justify-start'}`}>
                    <span className="text-[10px] text-white/50">{format(new Date(msg.created_at), 'HH:mm')}</span>
                    {statusIcon()}
                </div>
            </div>
        </div>
    )
}

/* ─── ConversationItem ───────────────────────────────────────── */
function ConversationItem({ conv, active, onClick }: { conv: Conversation; active: boolean; onClick: () => void }) {
    const initials = (conv.contact_name || conv.phone).charAt(0).toUpperCase()
    const tagNames = conv.tags?.split(',').filter(Boolean) || []
    const tagColors = conv.tag_colors?.split(',').filter(Boolean) || []

    return (
        <button
            onClick={onClick}
            className={`w-full flex items-center gap-3 px-4 py-3 transition-colors text-left ${active ? 'bg-brand-800/40 border-l-2 border-brand-500' : 'hover:bg-chat-hover border-l-2 border-transparent'}`}
        >
            {/* Avatar */}
            <div className="relative flex-shrink-0">
                <div className="w-11 h-11 rounded-full bg-brand-700 flex items-center justify-center text-white font-semibold text-base">
                    {initials}
                </div>
                {conv.status === 'waiting' && (
                    <span className="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-yellow-400 rounded-full border-2 border-chat-panel" />
                )}
            </div>

            {/* Info */}
            <div className="flex-1 min-w-0">
                <div className="flex items-center justify-between gap-1">
                    <span className="text-sm font-medium text-white truncate">{conv.contact_name || conv.phone}</span>
                    <span className="text-[10px] text-gray-500 flex-shrink-0">{formatDate(conv.last_msg_at)}</span>
                </div>
                <div className="flex items-center justify-between gap-1 mt-0.5">
                    <span className="text-xs text-gray-400 truncate">{conv.last_message || 'Nova conversa'}</span>
                    {conv.unread_count > 0 && (
                        <span className="flex-shrink-0 bg-brand-600 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center">
                            {conv.unread_count > 9 ? '9+' : conv.unread_count}
                        </span>
                    )}
                </div>
                {tagNames.length > 0 && (
                    <div className="flex gap-1 mt-1 flex-wrap">
                        {tagNames.slice(0, 2).map((tag, i) => (
                            <TagBadge key={i} name={tag} color={tagColors[i] || '#6B7280'} />
                        ))}
                    </div>
                )}
            </div>
        </button>
    )
}

/* ─── QuickReplyPanel ────────────────────────────────────────── */
function QuickReplyPanel({ onSelect, onClose }: { onSelect: (body: string) => void; onClose: () => void }) {
    const [replies, setReplies] = useState<any[]>([])
    const [search, setSearch] = useState('')

    useEffect(() => {
        api.get('/quick-replies').then(({ data }) => setReplies(data))
    }, [])

    const filtered = replies.filter((r) =>
        r.shortcut.includes(search) || r.title.toLowerCase().includes(search.toLowerCase())
    )

    return (
        <div className="absolute bottom-full left-0 right-0 mb-2 bg-chat-panel border border-chat-border rounded-xl shadow-2xl overflow-hidden z-50 animate-slide-up">
            <div className="flex items-center justify-between px-4 py-2 border-b border-chat-border">
                <span className="text-sm font-medium text-white flex items-center gap-2"><Zap className="w-4 h-4 text-brand-400" />Respostas Rápidas</span>
                <button onClick={onClose} className="text-gray-400 hover:text-white"><X className="w-4 h-4" /></button>
            </div>
            <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Buscar atalho..." className="input-base rounded-none border-0 border-b border-chat-border text-sm" />
            <div className="max-h-48 overflow-y-auto">
                {filtered.map((r) => (
                    <button key={r.id} onClick={() => onSelect(r.body)} className="w-full text-left px-4 py-2.5 hover:bg-chat-hover transition-colors">
                        <span className="text-brand-400 font-mono text-xs">/{r.shortcut} </span>
                        <span className="text-gray-300 text-sm">{r.title}</span>
                        <p className="text-gray-500 text-xs truncate mt-0.5">{r.body}</p>
                    </button>
                ))}
                {!filtered.length && <p className="text-gray-500 text-sm text-center py-4">Nenhum atalho encontrado</p>}
            </div>
        </div>
    )
}

/* ─── TagPanel ───────────────────────────────────────────────── */
function TagPanel({ convId, currentTags, onClose }: { convId: number; currentTags: string; onClose: () => void }) {
    const [allTags, setAllTags] = useState<any[]>([])
    const refreshConv = useChatStore((s) => s.refreshConversation)
    const currentTagNames = currentTags?.split(',').filter(Boolean) || []

    useEffect(() => { api.get('/tags').then(({ data }) => setAllTags(data)) }, [])

    const toggle = async (tag: any) => {
        if (currentTagNames.includes(tag.name)) {
            await api.delete(`/conversations/${convId}/tags/${tag.id}`)
        } else {
            await api.post(`/conversations/${convId}/tags`, { tagId: tag.id })
        }
        refreshConv(convId)
    }

    return (
        <div className="absolute top-full right-0 mt-2 w-64 bg-chat-panel border border-chat-border rounded-xl shadow-2xl overflow-hidden z-50 animate-fade-in">
            <div className="flex items-center justify-between px-4 py-2 border-b border-chat-border">
                <span className="text-sm font-medium text-white flex items-center gap-2"><Tag className="w-4 h-4 text-brand-400" />Etiquetas</span>
                <button onClick={onClose}><X className="w-4 h-4 text-gray-400 hover:text-white" /></button>
            </div>
            <div className="p-2 max-h-56 overflow-y-auto">
                {allTags.map((tag) => {
                    const active = currentTagNames.includes(tag.name)
                    return (
                        <button key={tag.id} onClick={() => toggle(tag)}
                            className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg transition-colors ${active ? 'bg-brand-900/40' : 'hover:bg-chat-hover'}`}>
                            <span className="w-3 h-3 rounded-full flex-shrink-0" style={{ backgroundColor: tag.color }} />
                            <span className="text-sm text-white flex-1 text-left">{tag.name}</span>
                            {active && <Check className="w-3.5 h-3.5 text-brand-400" />}
                        </button>
                    )
                })}
            </div>
        </div>
    )
}

/* ─── Chat Page (Principal) ──────────────────────────────────── */
export default function ChatPage() {
    const user = useAuthStore((s) => s.user)
    const setStatus = useAuthStore((s) => s.setStatus)
    const logout = useAuthStore((s) => s.logout)
    const navigate = useNavigate()

    const { conversations, activeConversationId, messages, fetchMessages, setConversations, setActive } = useChatStore()

    const [search, setSearch] = useState('')
    const [statusFilter, setStatusFilter] = useState<string>('open')
    const [text, setText] = useState('')
    const [sending, setSending] = useState(false)
    const [showEmoji, setShowEmoji] = useState(false)
    const [showQuick, setShowQuick] = useState(false)
    const [showTag, setShowTag] = useState(false)
    const fileInputRef = useRef<HTMLInputElement>(null)
    const messagesEndRef = useRef<HTMLDivElement>(null)

    const activeConv = conversations.find((c) => c.id === activeConversationId)
    const activeMessages = activeConversationId ? (messages[activeConversationId] || []) : []

    // Carrega conversas
    const loadConversations = useCallback(async () => {
        const params: any = {}
        if (statusFilter) params.status = statusFilter
        if (search) params.search = search
        const { data } = await api.get('/conversations', { params })
        setConversations(data)
    }, [statusFilter, search])

    useEffect(() => { loadConversations() }, [loadConversations])

    // Socket
    useEffect(() => { connectSocket() }, [])

    // Carrega mensagens quando muda a conversa ativa
    useEffect(() => {
        if (!activeConversationId) return
        fetchMessages(activeConversationId)
        joinConversation(activeConversationId)
        // Marca como lido
        api.put(`/conversations/${activeConversationId}/read`).catch(() => { })
        return () => leaveConversation(activeConversationId)
    }, [activeConversationId])

    // Scroll automático
    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
    }, [activeMessages.length])

    // Detecta /atalho no input
    useEffect(() => {
        if (text.startsWith('/') && text.length > 1) setShowQuick(true)
        else setShowQuick(false)
    }, [text])

    async function sendText() {
        if (!text.trim() || !activeConversationId || sending) return
        setSending(true)
        try {
            await api.post(`/messages/${activeConversationId}/text`, { text: text.trim() })
            setText('')
        } finally { setSending(false) }
    }

    async function sendFile(file: File) {
        if (!activeConversationId) return
        const fd = new FormData()
        fd.append('file', file)
        await api.post(`/messages/${activeConversationId}/media`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    }

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendText() }
    }

    return (
        <div className="flex h-screen bg-chat-bg overflow-hidden">
            {/* ── Sidebar ── */}
            <aside className="w-[340px] flex-shrink-0 bg-chat-panel border-r border-chat-border flex flex-col">
                {/* Header */}
                <div className="px-4 py-3 border-b border-chat-border flex items-center gap-3">
                    <div className="flex-1">
                        <div className="flex items-center gap-2">
                            <MessageSquare className="w-5 h-5 text-brand-400" />
                            <span className="font-semibold text-white text-sm">Nucleofix Chat</span>
                        </div>
                        <p className="text-xs text-gray-500">{user?.name}</p>
                    </div>
                    {/* Actions */}
                    <div className="flex items-center gap-1">
                        {user?.role === 'admin' && (
                            <>
                                <button onClick={() => navigate('/chat/admin')} className="btn-ghost p-2" title="Painel Admin">
                                    <Users className="w-4 h-4" />
                                </button>
                                <button onClick={() => navigate('/chat/settings')} className="btn-ghost p-2" title="Configurações">
                                    <Settings className="w-4 h-4" />
                                </button>
                            </>
                        )}
                        <button onClick={async () => { await logout(); navigate('/chat/login') }} className="btn-ghost p-2 text-red-400 hover:text-red-300" title="Sair">
                            <LogOut className="w-4 h-4" />
                        </button>
                    </div>
                </div>

                {/* Status do agente */}
                <div className="px-4 py-2 border-b border-chat-border flex items-center gap-2">
                    <div className={`w-2 h-2 rounded-full ${user?.status === 'online' ? 'bg-green-400' : user?.status === 'away' ? 'bg-yellow-400' : 'bg-gray-500'}`} />
                    <select
                        value={user?.status || 'offline'}
                        onChange={(e) => setStatus(e.target.value as any)}
                        className="text-xs text-gray-300 bg-transparent border-0 outline-none cursor-pointer flex-1"
                    >
                        <option value="online">Online</option>
                        <option value="away">Ausente</option>
                        <option value="offline">Offline</option>
                    </select>
                </div>

                {/* Busca */}
                <div className="px-3 py-2 border-b border-chat-border">
                    <div className="relative">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-500" />
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar por nome ou telefone..."
                            className="input-base pl-8 text-xs py-1.5"
                        />
                    </div>
                </div>

                {/* Filtros de status */}
                <div className="px-3 py-2 flex gap-1 border-b border-chat-border">
                    {['open', 'waiting', 'closed'].map((s) => (
                        <button key={s}
                            onClick={() => setStatusFilter(s === statusFilter ? '' : s)}
                            className={`flex-1 text-xs py-1 rounded-md font-medium transition-colors ${statusFilter === s ? 'bg-brand-700 text-white' : 'text-gray-400 hover:text-white hover:bg-chat-hover'}`}>
                            {s === 'open' ? 'Abertos' : s === 'waiting' ? 'Fila' : 'Fechados'}
                        </button>
                    ))}
                </div>

                {/* Lista de conversas */}
                <div className="flex-1 overflow-y-auto">
                    {conversations.length === 0 && (
                        <div className="flex flex-col items-center justify-center h-full text-gray-600 gap-2">
                            <MessageSquare className="w-8 h-8" />
                            <p className="text-sm">Nenhuma conversa</p>
                        </div>
                    )}
                    {conversations.map((conv) => (
                        <ConversationItem
                            key={conv.id}
                            conv={conv}
                            active={conv.id === activeConversationId}
                            onClick={() => setActive(conv.id)}
                        />
                    ))}
                </div>
            </aside>

            {/* ── Main Chat Area ── */}
            <main className="flex-1 flex flex-col min-w-0">
                {!activeConv ? (
                    <div className="flex-1 flex flex-col items-center justify-center text-gray-600 gap-3">
                        <div className="w-16 h-16 rounded-2xl bg-chat-card flex items-center justify-center">
                            <MessageSquare className="w-8 h-8 text-gray-600" />
                        </div>
                        <p className="text-lg font-medium">Selecione uma conversa</p>
                        <p className="text-sm">Escolha na lista à esquerda para começar</p>
                    </div>
                ) : (
                    <>
                        {/* Chat Header */}
                        <header className="flex items-center gap-3 px-5 py-3 border-b border-chat-border bg-chat-panel">
                            <div className="w-9 h-9 rounded-full bg-brand-700 flex items-center justify-center text-white font-semibold">
                                {(activeConv.contact_name || activeConv.phone).charAt(0).toUpperCase()}
                            </div>
                            <div className="flex-1 min-w-0">
                                <p className="font-semibold text-white text-sm">{activeConv.contact_name || activeConv.phone}</p>
                                <div className="flex items-center gap-2">
                                    <Phone className="w-3 h-3 text-gray-500" />
                                    <span className="text-xs text-gray-500">{activeConv.phone}</span>
                                    {activeConv.agent_name && (
                                        <span className="text-xs text-brand-400">· {activeConv.agent_name}</span>
                                    )}
                                </div>
                            </div>

                            {/* Tags da conversa */}
                            <div className="flex items-center gap-1.5 flex-wrap max-w-[200px]">
                                {activeConv.tags?.split(',').filter(Boolean).map((t, i) => (
                                    <TagBadge key={i} name={t} color={activeConv.tag_colors?.split(',')[i] || '#6B7280'} />
                                ))}
                            </div>

                            {/* Ações do header */}
                            <div className="relative flex items-center gap-1">
                                <button onClick={() => { setShowTag(!showTag); setShowEmoji(false) }} className="btn-ghost p-2" title="Etiquetas">
                                    <Tag className="w-4 h-4" />
                                </button>
                                <button
                                    onClick={async () => {
                                        const next = activeConv.status === 'open' ? 'closed' : 'open'
                                        await api.patch(`/conversations/${activeConv.id}/status`, { status: next })
                                        loadConversations()
                                    }}
                                    className={`text-xs px-3 py-1.5 rounded-lg font-medium transition-colors ${activeConv.status === 'open' ? 'bg-red-500/20 text-red-400 hover:bg-red-500/30' : 'bg-brand-600/20 text-brand-400 hover:bg-brand-600/30'}`}>
                                    {activeConv.status === 'open' ? 'Fechar' : 'Reabrir'}
                                </button>

                                {showTag && (
                                    <TagPanel convId={activeConv.id} currentTags={activeConv.tags || ''} onClose={() => setShowTag(false)} />
                                )}
                            </div>
                        </header>

                        {/* Messages */}
                        <div className="flex-1 overflow-y-auto px-5 py-4 space-y-0.5" style={{ backgroundImage: 'radial-gradient(circle at 1px 1px, #30363d14 1px, transparent 0)', backgroundSize: '24px 24px' }}>
                            {activeMessages.map((msg) => (
                                <MessageBubble key={msg.id} msg={msg} />
                            ))}
                            <div ref={messagesEndRef} />
                        </div>

                        {/* Input Area */}
                        <div className="border-t border-chat-border bg-chat-panel px-4 py-3">
                            <div className="relative">
                                {showQuick && (
                                    <QuickReplyPanel
                                        onSelect={(body) => { setText(body); setShowQuick(false) }}
                                        onClose={() => setShowQuick(false)}
                                    />
                                )}
                                {showEmoji && (
                                    <div className="absolute bottom-full left-0 mb-2 z-50">
                                        <Picker data={data} locale="pt" theme="dark" onEmojiSelect={(e: any) => { setText((t) => t + e.native); setShowEmoji(false) }} />
                                    </div>
                                )}
                            </div>

                            <div className="flex items-end gap-2">
                                {/* Emoji */}
                                <button onClick={() => setShowEmoji(!showEmoji)} className="btn-ghost p-2 flex-shrink-0">
                                    <Smile className="w-5 h-5" />
                                </button>

                                {/* Upload */}
                                <button onClick={() => fileInputRef.current?.click()} className="btn-ghost p-2 flex-shrink-0">
                                    <Paperclip className="w-5 h-5" />
                                </button>
                                <input ref={fileInputRef} type="file" hidden accept="image/*,video/*,audio/*,.pdf,.doc,.docx"
                                    onChange={(e) => { const f = e.target.files?.[0]; if (f) sendFile(f) }} />

                                {/* Respostas rápidas */}
                                <button onClick={() => setShowQuick(!showQuick)} className="btn-ghost p-2 flex-shrink-0" title="Respostas rápidas (ou digite /)">
                                    <Zap className="w-5 h-5" />
                                </button>

                                {/* Text input */}
                                <textarea
                                    value={text}
                                    onChange={(e) => setText(e.target.value)}
                                    onKeyDown={handleKeyDown}
                                    placeholder="Digite uma mensagem... (/ para atalhos)"
                                    rows={1}
                                    className="input-base flex-1 resize-none max-h-32 py-2.5 leading-relaxed"
                                    style={{ minHeight: '42px' }}
                                />

                                {/* Send */}
                                <button
                                    onClick={sendText}
                                    disabled={!text.trim() || sending}
                                    className="btn-primary p-2.5 flex-shrink-0 rounded-xl"
                                >
                                    <Send className="w-5 h-5" />
                                </button>
                            </div>
                        </div>
                    </>
                )}
            </main>
        </div>
    )
}
