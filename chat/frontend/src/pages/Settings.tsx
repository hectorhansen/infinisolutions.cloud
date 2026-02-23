import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../services/api'
import { ArrowLeft, Save, Loader2, CheckCircle, AlertCircle, ExternalLink, Tag, Zap, Plus, Trash2 } from 'lucide-react'

export default function SettingsPage() {
    const navigate = useNavigate()
    const [settings, setSettings] = useState({
        whatsapp_phone_number_id: '',
        whatsapp_token: '',
        whatsapp_verify_token: 'nucleofix_verify_2025',
        whatsapp_phone_display: '',
        system_name: 'Nucleofix Chat',
    })
    const [saving, setSaving] = useState(false)
    const [saved, setSaved] = useState(false)
    const [error, setError] = useState('')

    // Tags
    const [tags, setTags] = useState<any[]>([])
    const [tagName, setTagName] = useState('')
    const [tagColor, setTagColor] = useState('#10B981')

    // Quick Replies
    const [replies, setReplies] = useState<any[]>([])
    const [replyShortcut, setReplyShortcut] = useState('')
    const [replyTitle, setReplyTitle] = useState('')
    const [replyBody, setReplyBody] = useState('')

    useEffect(() => {
        api.get('/settings').then(({ data }) => setSettings((s) => ({ ...s, ...data })))
        api.get('/tags').then(({ data }) => setTags(data))
        api.get('/quick-replies').then(({ data }) => setReplies(data))
    }, [])

    async function saveSettings() {
        setSaving(true); setError('')
        try {
            await api.put('/settings', settings)
            setSaved(true)
            setTimeout(() => setSaved(false), 3000)
        } catch (e: any) {
            setError(e.response?.data?.error || 'Erro ao salvar')
        } finally { setSaving(false) }
    }

    async function addTag() {
        if (!tagName) return
        await api.post('/tags', { name: tagName, color: tagColor })
        const { data } = await api.get('/tags')
        setTags(data); setTagName(''); setTagColor('#10B981')
    }

    async function deleteTag(id: number) {
        await api.delete(`/tags/${id}`)
        setTags(tags.filter((t) => t.id !== id))
    }

    async function addReply() {
        if (!replyShortcut || !replyBody) return
        await api.post('/quick-replies', { shortcut: replyShortcut, title: replyTitle || replyShortcut, body: replyBody })
        const { data } = await api.get('/quick-replies')
        setReplies(data); setReplyShortcut(''); setReplyTitle(''); setReplyBody('')
    }

    async function deleteReply(id: number) {
        await api.delete(`/quick-replies/${id}`)
        setReplies(replies.filter((r) => r.id !== id))
    }

    return (
        <div className="min-h-screen bg-chat-bg">
            <header className="bg-chat-panel border-b border-chat-border px-6 py-4 flex items-center gap-4">
                <button onClick={() => navigate('/chat')} className="btn-ghost p-2"><ArrowLeft className="w-5 h-5" /></button>
                <div>
                    <h1 className="text-lg font-bold text-white">Configurações</h1>
                    <p className="text-xs text-gray-400">WhatsApp Business API · Etiquetas · Respostas rápidas</p>
                </div>
            </header>

            <div className="max-w-3xl mx-auto px-6 py-6 space-y-6">

                {/* WhatsApp API */}
                <div className="card p-6">
                    <h2 className="text-base font-semibold text-white mb-1 flex items-center gap-2">
                        <span className="w-2 h-2 rounded-full bg-green-400" />WhatsApp Business API
                    </h2>
                    <p className="text-xs text-gray-400 mb-4">
                        Configure o token e ID do número no{' '}
                        <a href="https://developers.facebook.com/apps/" target="_blank" rel="noreferrer" className="text-brand-400 hover:underline inline-flex items-center gap-0.5">
                            Meta for Developers <ExternalLink className="w-3 h-3" />
                        </a>
                    </p>

                    <div className="grid gap-3">
                        <div>
                            <label className="block text-xs font-medium text-gray-300 mb-1">Phone Number ID</label>
                            <input value={settings.whatsapp_phone_number_id}
                                onChange={(e) => setSettings({ ...settings, whatsapp_phone_number_id: e.target.value })}
                                placeholder="123456789012345"
                                className="input-base" />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-300 mb-1">Token Permanente</label>
                            <input value={settings.whatsapp_token}
                                onChange={(e) => setSettings({ ...settings, whatsapp_token: e.target.value })}
                                placeholder="EAAxxxxx..."
                                type="password"
                                className="input-base" />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-300 mb-1">Verify Token (webhook)</label>
                            <input value={settings.whatsapp_verify_token}
                                onChange={(e) => setSettings({ ...settings, whatsapp_verify_token: e.target.value })}
                                className="input-base" />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-300 mb-1">Número exibido</label>
                            <input value={settings.whatsapp_phone_display}
                                onChange={(e) => setSettings({ ...settings, whatsapp_phone_display: e.target.value })}
                                placeholder="+55 11 99999-9999"
                                className="input-base" />
                        </div>
                    </div>

                    <div className="mt-4 p-3 bg-brand-900/30 border border-brand-800/50 rounded-lg">
                        <p className="text-xs text-brand-300 font-medium">URL do Webhook para configurar na Meta:</p>
                        <code className="text-xs text-brand-200 font-mono mt-1 block break-all">
                            https://infinisolutions.cloud/chat/api/webhook
                        </code>
                    </div>

                    {error && <p className="text-red-400 text-xs mt-3 flex items-center gap-1"><AlertCircle className="w-3.5 h-3.5" />{error}</p>}

                    <div className="mt-4 flex justify-end">
                        <button onClick={saveSettings} disabled={saving} className="btn-primary flex items-center gap-2">
                            {saving ? <Loader2 className="w-4 h-4 animate-spin" /> : saved ? <CheckCircle className="w-4 h-4 text-green-300" /> : <Save className="w-4 h-4" />}
                            {saved ? 'Salvo!' : 'Salvar configurações'}
                        </button>
                    </div>
                </div>

                {/* Tags */}
                <div className="card p-6">
                    <h2 className="text-base font-semibold text-white mb-4 flex items-center gap-2"><Tag className="w-4 h-4 text-brand-400" />Etiquetas</h2>
                    <div className="flex gap-2 mb-4">
                        <input value={tagName} onChange={(e) => setTagName(e.target.value)} placeholder="Nome da etiqueta" className="input-base flex-1" />
                        <input type="color" value={tagColor} onChange={(e) => setTagColor(e.target.value)} className="w-12 h-10 rounded-lg border border-chat-border bg-chat-input cursor-pointer p-1" />
                        <button onClick={addTag} className="btn-primary px-3"><Plus className="w-4 h-4" /></button>
                    </div>
                    <div className="space-y-2">
                        {tags.map((tag) => (
                            <div key={tag.id} className="flex items-center gap-3 px-3 py-2 bg-chat-hover rounded-lg">
                                <span className="w-3 h-3 rounded-full" style={{ backgroundColor: tag.color }} />
                                <span className="text-sm text-white flex-1">{tag.name}</span>
                                <button onClick={() => deleteTag(tag.id)} className="text-gray-500 hover:text-red-400 transition-colors"><Trash2 className="w-3.5 h-3.5" /></button>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Quick Replies */}
                <div className="card p-6">
                    <h2 className="text-base font-semibold text-white mb-4 flex items-center gap-2"><Zap className="w-4 h-4 text-brand-400" />Respostas Rápidas</h2>
                    <div className="grid gap-2 mb-4">
                        <div className="grid grid-cols-2 gap-2">
                            <input value={replyShortcut} onChange={(e) => setReplyShortcut(e.target.value)} placeholder="Atalho (ex: ola)" className="input-base" />
                            <input value={replyTitle} onChange={(e) => setReplyTitle(e.target.value)} placeholder="Título (opcional)" className="input-base" />
                        </div>
                        <div className="flex gap-2">
                            <textarea value={replyBody} onChange={(e) => setReplyBody(e.target.value)} placeholder="Texto completo da mensagem..." rows={2} className="input-base flex-1 resize-none" />
                            <button onClick={addReply} className="btn-primary px-3 self-start mt-0"><Plus className="w-4 h-4" /></button>
                        </div>
                    </div>
                    <div className="space-y-2">
                        {replies.map((r) => (
                            <div key={r.id} className="flex items-start gap-3 px-3 py-2.5 bg-chat-hover rounded-lg">
                                <code className="text-xs text-brand-400 font-mono mt-0.5 flex-shrink-0">/{r.shortcut}</code>
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm text-white font-medium">{r.title}</p>
                                    <p className="text-xs text-gray-400 truncate">{r.body}</p>
                                </div>
                                <button onClick={() => deleteReply(r.id)} className="text-gray-500 hover:text-red-400 transition-colors flex-shrink-0"><Trash2 className="w-3.5 h-3.5" /></button>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    )
}
