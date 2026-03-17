/**
 * chat.js - Lida com as trocas de mensagens e tela central
 */

async function openChat(convId) {
    activeConversationId = convId;
    loadConversations(); // Força re-render para marcar o item como ativo (azul) na lista
    
    // UI states
    document.getElementById('emptyChat').classList.add('hidden');
    document.getElementById('chatHeader').classList.remove('hidden');
    document.getElementById('chatHeader').classList.add('flex');
    document.getElementById('chatBg').classList.remove('hidden');
    document.getElementById('chatHistory').classList.remove('hidden');
    document.getElementById('chatFooter').classList.remove('hidden');
    
    // Carrega info topo
    const cv = conversations.find(c => c.id === convId);
    if(cv) {
        const name = cv.contact_name || cv.contact_phone;
        document.getElementById('activeContactName').textContent = name;
        document.getElementById('activeContactPhone').textContent = cv.contact_phone;
        
        const avatar = `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=random`;
        document.getElementById('activeContactPic').src = avatar;
        
        document.getElementById('infoName').textContent = name;
        document.getElementById('infoPhone').textContent = cv.contact_phone;
        document.getElementById('infoPic').src = avatar;
    }
    
    // Limpa chat
    const hist = document.getElementById('chatHistory');
    hist.innerHTML = '<div class="text-center py-6 text-gray-400"><i class="fa-solid fa-circle-notch fa-spin"></i> Carregando mensagens...</div>';
    
    // Da o fetch
    try {
        const msgs = await api.get('messages', { conversation_id: convId });
        hist.innerHTML = '';
        if(msgs.length === 0) {
            hist.innerHTML = '<div class="text-center py-6 text-xs bg-yellow-100/50 text-yellow-800 rounded-lg max-w-sm mx-auto shadow-sm">Nenhuma mensagem anterior.</div>';
        } else {
            // Data group (hoje, ontem...) omitido por escopo
            msgs.forEach(m => appendMessage(m, false));
            scrollToBottom();
        }
    } catch(e) {
        hist.innerHTML = '<div class="text-center text-red-500 py-4">Erro ao carregar histórico.</div>';
    }
}

function appendMessage(msg, scroll = true) {
    const hist = document.getElementById('chatHistory');
    
    // Remove empty state if exists
    if(hist.innerHTML.includes('Nenhuma mensagem')) hist.innerHTML = '';
    
    const isOut = msg.direction === 'outbound';
    
    // Icons de status (só pra outbound)
    let statusIcon = '';
    if (isOut) {
        if (msg.status === 'pending') statusIcon = '<i class="fa-regular fa-clock text-gray-400"></i>';
        else if (msg.status === 'sent') statusIcon = '<i class="fa-solid fa-check text-gray-400"></i>';
        else if (msg.status === 'delivered') statusIcon = '<i class="fa-solid fa-check-double text-gray-400"></i>';
        else if (msg.status === 'read') statusIcon = '<i class="fa-solid fa-check-double text-blue-500"></i>';
        else if (msg.status === 'failed') statusIcon = '<i class="fa-solid fa-circle-exclamation text-red-500" title="Falha ao enviar"></i>';
    }
    
    const time = formatTime(msg.created_at);
    
    let contentHtml = escapeHtml(msg.body || '');
    // Se for imagem (placeholder logica)
    if(msg.type === 'image') {
        contentHtml = `<div class="rounded-lg overflow-hidden bg-gray-200 mb-1 flex items-center justify-center p-4">
            <i class="fa-regular fa-image text-3xl text-gray-400"></i>
        </div>` + (msg.body ? escapeHtml(msg.body) : '');
    }
    
    const html = `
        <div class="flex ${isOut ? 'justify-end' : 'justify-start'}" id="msg-${msg.id}">
            <div class="max-w-[75%] px-3 py-1.5 rounded-lg shadow-sm relative text-[15px] leading-relaxed group ${
                isOut ? 'bg-[#dcf8c6] rounded-tr-none text-gray-800' : 'bg-white rounded-tl-none border border-gray-100 text-gray-800'
            }">
                <!-- Menu dropdown invisivel q aparece no hover -->
                <button class="absolute top-1 ${isOut ? 'left-[-24px]' : 'right-[-24px]'} w-6 h-6 rounded-full bg-white shadow-sm border border-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-700 opacity-0 group-hover:opacity-100 transition-opacity z-10"><i class="fa-solid fa-chevron-down text-[10px]"></i></button>

                <div class="break-words px-1">${contentHtml.replace(/\\n/g, '<br>')}</div>
                
                <div class="flex items-center justify-end gap-1 mt-1 px-1">
                    <span class="text-[11px] text-gray-500/80 mr-0.5">${time}</span>
                    <span class="text-[10px]" id="status-${msg.id}">${statusIcon}</span>
                </div>
            </div>
        </div>
    `;
    
    hist.insertAdjacentHTML('beforeend', html);
    if(scroll) scrollToBottom();
}

function updateMsgStatus(msgId, statusStr) {
    const el = document.getElementById(`status-${msgId}`);
    if(!el) return;
    
    let icon = '';
    if (statusStr === 'sent') icon = '<i class="fa-solid fa-check text-gray-400"></i>';
    if (statusStr === 'delivered') icon = '<i class="fa-solid fa-check-double text-gray-400"></i>';
    if (statusStr === 'read') icon = '<i class="fa-solid fa-check-double text-blue-500"></i>';
    if (statusStr === 'failed') icon = '<i class="fa-solid fa-circle-exclamation text-red-500"></i>';
    
    el.innerHTML = icon;
}

// Auto Resize Input
const input = document.getElementById('messageInput');
if(input) {
    input.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
        if(this.value.trim() !== '') {
            document.getElementById('sendBtn').innerHTML = '<i class="fa-solid fa-paper-plane"></i>';
        } else {
            // Em branco = microfone
            // document.getElementById('sendBtn').innerHTML = '<i class="fa-solid fa-microphone"></i>';
        }
    });

    // Enviar no Enter (sem shift)
    input.addEventListener('keydown', function(e) {
        if(e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendTextMessage();
        }
    });
}

async function sendTextMessage() {
    if(!activeConversationId) return;
    const text = input.value.trim();
    if(!text) return;
    
    input.value = '';
    input.style.height = 'auto'; // reset resize
    
    try {
        const res = await api.post('messages', {
            conversation_id: activeConversationId,
            type: 'text',
            text: text
        });
        
        // Optimistic UI Append usando o local_id
        appendMessage({
            id: res.local_id,
            conversation_id: activeConversationId,
            direction: 'outbound',
            type: 'text',
            body: text,
            status: 'pending',
            created_at: new Date().toISOString()
        }, true);
        
        // Atualiza inbox list lateral instantaneamente simulando o last_message
        const cv = conversations.find(c => c.id === activeConversationId);
        if(cv) {
            cv.last_message_preview = text;
            cv.last_message_at = new Date().toISOString();
            renderConversations();
        }
        
    } catch(e) {
        alert("Erro ao enviar: " + e.message);
    }
}

async function resolveChat() {
    if(!activeConversationId) return;
    if(!confirm('Deseja encerrar e resolver esta conversa?')) return;
    
    try {
        await api.post('conversations', { 
            do: 'resolve', 
            conversation_id: activeConversationId 
        });
        
        // Remove da minha lista e reseta UI
        activeConversationId = null;
        loadConversations();
        document.getElementById('emptyChat').classList.remove('hidden');
        document.getElementById('chatHeader').classList.add('hidden');
        document.getElementById('chatFooter').classList.add('hidden');
        document.getElementById('chatHistory').innerHTML = '';
        
    } catch(e) {
        alert(e.message);
    }
}

function scrollToBottom() {
    const hist = document.getElementById('chatHistory');
    hist.scrollTop = hist.scrollHeight;
}

function escapeHtml(unsafe) {
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}
