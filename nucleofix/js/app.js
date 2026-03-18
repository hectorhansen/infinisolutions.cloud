/**
 * app.js - Configurações base, auth e helpers globais
 */

const API_BASE = 'https://infinisolutions.cloud/api/index.php?action=';

// Interceptor simples do Fetch para sempre mandar o Token e tratar o 401
const api = {
    async request(action, options = {}) {
        const token = localStorage.getItem('wab_token');
        if (!token) {
            window.location.href = 'index.html'; // Tchau
            return null;
        }

        const headers = {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            ...(options.headers || {})
        };

        try {
            const res = await fetch(`${API_BASE}${action}`, { ...options, headers });
            
            if (res.status === 401) {
                localStorage.clear();
                window.location.href = 'index.html';
                return null;
            }

            const data = await res.json();
            
            if (!res.ok) {
                throw new Error(data.error || 'Erro na API');
            }
            return data;
            
        } catch (error) {
            console.error('[API Error]', error);
            throw error;
        }
    },
    
    get(action, params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`${action}${query ? '&'+query : ''}`, { method: 'GET' });
    },
    
    post(action, body) {
        return this.request(action, { method: 'POST', body: JSON.stringify(body) });
    }
};

// Se não tiver na tela de login, e não tiver token, chuta pra fora
if (window.location.pathname.includes('dashboard') && !localStorage.getItem('wab_token')) {
    window.location.href = 'index.html';
}

async function logout() {
    try {
        await api.post('logout');
    } catch(e) {} finally {
        localStorage.clear();
        window.location.href = 'index.html';
    }
}

// Inicializa Dados do Operador no Dashboard
async function loadMe() {
    if (!document.getElementById('myAvatar')) return;
    try {
        const me = await api.get('me');
        if (me.avatar_url) {
            document.getElementById('myAvatar').src = me.avatar_url;
        } else {
            document.getElementById('myAvatar').src = `https://ui-avatars.com/api/?name=${encodeURIComponent(me.name)}&background=random`;
        }
        
        if (me.role === 'admin' || me.role === 'supervisor') {
            document.getElementById('adminMenuBtn').classList.remove('hidden');
        }
    } catch(e) {}
}

// Troca de abas visuais na nav esquerda
function switchTab(tab) {
    // Apenas visual pro protótipo
    alert(`Aba ${tab} clicada. Futuramente carregará a sub-view correspondente.`);
}

function toggleRightPanel() {
    const rp = document.getElementById('rightPanel');
    if (rp.classList.contains('hidden')) {
        rp.classList.remove('hidden');
        rp.classList.add('flex', 'flex-col');
    } else {
        rp.classList.add('hidden');
        rp.classList.remove('flex', 'flex-col');
    }
}

// Utils
function formatTime(dateString) {
    if (!dateString) return '';
    const d = new Date(dateString);
    return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

// Boot inicial
document.addEventListener('DOMContentLoaded', () => {
    loadMe();
});
