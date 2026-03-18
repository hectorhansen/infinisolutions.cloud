/**
 * inbox.js - Gerencia a lista esquerda de conversas e o Polling (Ping)
 */

let currentFilter = 'mine'; // mine | unassigned
let conversations = [];
let activeConversationId = null; 

async function loadConversations() {
    try {
        const spinner = document.getElementById('conversationList');
        // spinner.innerHTML = '<div class="p-4 text-center text-gray-400"><i class="fa-solid fa-spinner fa-spin"></i></div>';
        
        const data = await api.get('conversations', { filter: currentFilter });
        conversations = data;
        renderConversations();
    } catch(e) {
        console.error("Falha ao carregar inbox", e);
        alert("Erro ao carregar conversas: " + e.message);
    }
}

function renderConversations() {
    const list = document.getElementById('conversationList');
    list.innerHTML = '';

    if (conversations.length === 0) {
        list.innerHTML = `<div class="p-6 text-center text-gray-400 text-sm">Nenhuma conversa encontrada.</div>`;
        return;
    }

    conversations.forEach(c => {
        const isActive = c.id === activeConversationId;
        const timeStr = formatTime(c.last_message_at);
        const unreadBadge = c.unread_count > 0 ? 
            `<span class="bg-wa-light text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[20px] text-center">${c.unread_count}</span>` 
            : '';

        const name = c.contact_name || c.contact_phone || 'Desconhecido';
        
        // Se a conversa for da FILA adiciona um botão para Puxar (Assign)
        const actionHtml = (!c.operator_id && currentFilter === 'unassigned') ? 
            `<button onclick="assignToMe('${c.id}', event)" class="mt-1 text-xs bg-wa text-white px-2 py-1 rounded hover:bg-wa-dark transition shadow-sm">Atender</button>` : '';

        const html = `
            <div class="px-3 py-2 cursor-pointer border-b border-gray-100 transition-colors ${isActive ? 'bg-blue-50' : 'hover:bg-gray-50'}"
                 onclick="openChat('${c.id}')">
                <div class="flex items-center gap-3">
                    <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=random" class="w-12 h-12 rounded-full object-cover">
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-baseline mb-0.5">
                            <h4 class="font-medium text-gray-800 text-sm truncate pr-2">${name}</h4>
                            <span class="text-xs ${c.unread_count > 0 ? 'text-wa-light font-bold' : 'text-gray-400'}">${timeStr}</span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <p class="${c.unread_count > 0 ? 'text-gray-900 font-medium' : 'text-gray-500'} truncate mr-2 w-full text-[13px] leading-tight">
                                ${c.last_message_preview || '<i>Mídia</i>'}
                            </p>
                            ${unreadBadge}
                        </div>
                        ${actionHtml}
                    </div>
                </div>
            </div>
        `;
        list.insertAdjacentHTML('beforeend', html);
    });
}

function filterList(type, btnElement) {
    currentFilter = type;
    
    // UI update tabs
    const buttons = btnElement.parentElement.querySelectorAll('button');
    buttons.forEach(b => {
        b.classList.remove('text-wa', 'border-wa');
        b.classList.add('text-gray-500', 'border-transparent');
    });
    btnElement.classList.add('text-wa', 'border-wa');
    btnElement.classList.remove('text-gray-500', 'border-transparent');

    loadConversations();
}

async function assignToMe(convId, event) {
    event.stopPropagation(); // previne openChat disparar
    const opId = localStorage.getItem('wab_op_id');
    try {
        await api.post('conversations', { do: 'reassign', conversation_id: convId, operator_id: opId });
        openChat(convId); // abre ja puxando ela
        filterList('mine', document.querySelector('[onclick="filterList(\'mine\', this)"]')); // volta pra aba minhas
    } catch(e) {
        alert(e.message);
    }
}

// ==========================================
// POLLING ENGINE (Substituto de WebSockets)
// ==========================================
async function pollEvents() {
    try {
        const res = await api.get('events');
        if (res && res.events && res.events.length > 0) {
            handleEvents(res.events);
        }
    } catch(e) {
        console.warn("Polling error (ignorando e tentando no proximo tick)", e);
    } finally {
        setTimeout(pollEvents, 3000); // 3 segundos de intervalo = 20 reqs/min = safe pra cPanel
    }
}

function handleEvents(events) {
    let shouldReloadInbox = false;
    
    events.forEach(ev => {
        console.log("Recebeu Evento RT:", ev.event_type, ev);

        if (ev.event_type === 'new_message') {
            shouldReloadInbox = true;
            // Se o chat atual for o do evento, append na tela!
            if (activeConversationId === ev.conversation_id && typeof appendMessage === 'function') {
                appendMessage(ev.payload.message); // Payload formatado do backend
            } else {
                // Toca som notificação
            }
        }
        
        if (ev.event_type === 'new_conversation' || ev.event_type === 'assigned') {
            shouldReloadInbox = true;
        }
        
        if (ev.event_type === 'status_update') {
            // Se estiver com o chat aberto, atualiza o ✓✓ lá (Implementado no chat.js)
            if (activeConversationId === ev.conversation_id && typeof updateMsgStatus === 'function') {
                updateMsgStatus(ev.message_id, ev.payload.status);
            }
        }
    });

    if (shouldReloadInbox) {
        loadConversations(); // Recarrega lateral atualizada com badges etc (optimistic seria melhor, mas fetch rapido resolve pra v1)
    }
}

// Inicia
document.addEventListener('DOMContentLoaded', () => {
    if(window.location.pathname.includes('dashboard')) {
        loadConversations();
        pollEvents();
    }
});
