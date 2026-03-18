// ============================================================
// InfiniCloud - app.js
// Lógica frontend: autenticação, upload, listagem e links
// ============================================================

const API = {
    auth:   'api/auth.php',
    upload: 'api/upload.php',
    files:  'api/files.php',
    links:  'api/links.php',
};

// ---- Estado Global ----
let currentUser   = null;
let currentFileId = null; // arquivo selecionado para gerar link

// ---- Elementos DOM ----
const loginScreen   = document.getElementById('login-screen');
const appScreen     = document.getElementById('app-screen');
const loginForm     = document.getElementById('login-form');
const loginError    = document.getElementById('login-error');
const fileInput     = document.getElementById('file-input');
const uploadZone    = document.getElementById('upload-zone');
const uploadProgress= document.getElementById('upload-progress');
const progressFill  = document.getElementById('progress-fill');
const progressLabel = document.getElementById('progress-label');
const filesBody     = document.getElementById('files-body');
const filesCount    = document.getElementById('files-count');
const modalOverlay  = document.getElementById('modal-overlay');
const modalTitle    = document.getElementById('modal-title');
const expiresInput  = document.getElementById('expires-at');
const linkResult    = document.getElementById('link-result');
const linkUrl       = document.getElementById('link-url');
const copyBtn       = document.getElementById('copy-btn');
const toastContainer= document.getElementById('toast-container');

// ============================================================
// AUTENTICAÇÃO
// ============================================================

async function checkSession() {
    try {
        const res = await fetch(`${API.auth}?action=check`);
        const data = await res.json();
        if (data.authenticated) {
            currentUser = data.user;
            showApp();
        } else {
            showLogin();
        }
    } catch {
        showLogin();
    }
}

function showLogin() {
    loginScreen.style.display = 'flex';
    appScreen.style.display   = 'none';
}

function showApp() {
    loginScreen.style.display = 'none';
    appScreen.style.display   = 'block';
    document.getElementById('user-name-display').textContent = currentUser.name;
    loadFiles();
}

// Submit login
loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    loginError.style.display = 'none';

    const btn = loginForm.querySelector('.btn-primary');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Entrando...';

    try {
        const res = await fetch(`${API.auth}?action=login`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email:    document.getElementById('email').value.trim(),
                password: document.getElementById('password').value,
            }),
        });
        const data = await res.json();

        if (data.ok) {
            currentUser = data.user;
            showApp();
        } else {
            loginError.textContent = data.error || 'Erro ao fazer login.';
            loginError.style.display = 'block';
        }
    } catch {
        loginError.textContent = 'Erro de conexão. Tente novamente.';
        loginError.style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Entrar';
    }
});

// Logout
document.getElementById('logout-btn').addEventListener('click', async () => {
    await fetch(`${API.auth}?action=logout`, { method: 'POST' });
    currentUser = null;
    showLogin();
});

// ============================================================
// UPLOAD DE ARQUIVOS
// ============================================================

// Drag and Drop
uploadZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadZone.classList.add('drag-over');
});

uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('drag-over'));

uploadZone.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadZone.classList.remove('drag-over');
    const files = e.dataTransfer.files;
    if (files.length) uploadFile(files[0]);
});

// Clique na zona → abre seletor de arquivo
uploadZone.addEventListener('click', (e) => {
    if (!e.target.classList.contains('browse-btn')) fileInput.click();
});

document.querySelector('.browse-btn').addEventListener('click', (e) => {
    e.stopPropagation();
    fileInput.click();
});

fileInput.addEventListener('change', () => {
    if (fileInput.files.length) uploadFile(fileInput.files[0]);
    fileInput.value = '';
});

function uploadFile(file) {
    const formData = new FormData();
    formData.append('file', file);

    // Exibe progress bar
    uploadProgress.style.display = 'block';
    progressFill.style.width = '0%';
    progressLabel.textContent = `Enviando ${file.name}...`;

    const xhr = new XMLHttpRequest();

    xhr.upload.onprogress = (e) => {
        if (e.lengthComputable) {
            const pct = Math.round((e.loaded / e.total) * 100);
            progressFill.style.width = pct + '%';
            progressLabel.textContent = `${pct}% — ${file.name}`;
        }
    };

    xhr.onload = () => {
        uploadProgress.style.display = 'none';
        try {
            const data = JSON.parse(xhr.responseText);
            if (data.ok) {
                toast(`✓ ${data.file.original_name} enviado com sucesso!`, 'success');
                loadFiles();
            } else {
                toast(data.error || 'Erro no upload.', 'error');
            }
        } catch {
            toast('Erro inesperado no servidor.', 'error');
        }
    };

    xhr.onerror = () => {
        uploadProgress.style.display = 'none';
        toast('Falha na conexão durante o upload.', 'error');
    };

    xhr.open('POST', API.upload);
    xhr.send(formData);
}

// ============================================================
// LISTAGEM DE ARQUIVOS
// ============================================================

async function loadFiles() {
    try {
        const res  = await fetch(API.files);
        const data = await res.json();

        if (!data.ok) { toast(data.error, 'error'); return; }

        const files = data.files;
        filesCount.textContent = files.length;

        if (!files.length) {
            filesBody.innerHTML = `
                <tr><td colspan="5">
                    <div class="empty-state">
                        <i class="fa fa-cloud-arrow-up"></i>
                        <p>Nenhum arquivo enviado ainda.<br>Arraste um arquivo acima para começar.</p>
                    </div>
                </td></tr>`;
            return;
        }

        filesBody.innerHTML = files.map(file => `
            <tr data-id="${file.id}">
                <td>
                    <div class="file-cell">
                        <div class="file-icon-wrap ${iconClass(file.icon)}">
                            <i class="fa ${file.icon}"></i>
                        </div>
                        <div>
                            <div class="file-name" title="${escapeHtml(file.original_name)}">${escapeHtml(truncate(file.original_name, 50))}</div>
                            <div class="file-size-badge">${file.size_human} · ${file.mime_type}</div>
                        </div>
                    </div>
                </td>
                <td style="color:var(--text-muted);font-size:.82rem;" class="hide-mobile">${formatDate(file.created_at)}</td>
                <td>
                    <span class="links-badge ${file.active_links > 0 ? 'has-active' : 'no-active'}">
                        <i class="fa fa-link"></i>
                        ${file.active_links > 0 ? `${file.active_links} ativo${file.active_links > 1 ? 's' : ''}` : 'Sem links'}
                    </span>
                </td>
                <td>
                    <div class="actions-cell">
                        <button class="btn-action" onclick="openLinkModal(${file.id}, '${escapeHtml(file.original_name)}')">
                            <i class="fa fa-link"></i> Gerar Link
                        </button>
                        <button class="btn-danger" onclick="deleteFile(${file.id}, '${escapeHtml(file.original_name)}')">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

    } catch {
        toast('Erro ao carregar arquivos.', 'error');
    }
}

async function deleteFile(id, name) {
    if (!confirm(`Deseja excluir permanentemente "${name}"?\nTodos os links associados serão desativados.`)) return;

    try {
        const res  = await fetch(`${API.files}?id=${id}`, { method: 'DELETE' });
        const data = await res.json();
        if (data.ok) {
            toast('Arquivo excluído com sucesso.', 'success');
            loadFiles();
        } else {
            toast(data.error || 'Erro ao excluir.', 'error');
        }
    } catch {
        toast('Erro de conexão.', 'error');
    }
}

// ============================================================
// MODAL DE LINK
// ============================================================

function openLinkModal(fileId, fileName) {
    currentFileId  = fileId;
    linkResult.style.display = 'none';
    linkUrl.value  = '';
    expiresInput.value = '';
    document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));

    modalTitle.textContent = `Gerar link — ${truncate(fileName, 40)}`;
    modalOverlay.classList.add('open');

    // Data mínima = amanhã
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    expiresInput.min = tomorrow.toISOString().split('T')[0];
}

function closeModal() {
    modalOverlay.classList.remove('open');
    currentFileId = null;
}

modalOverlay.addEventListener('click', (e) => {
    if (e.target === modalOverlay) closeModal();
});

document.getElementById('modal-close-btn').addEventListener('click', closeModal);

// Presets de duração
document.querySelectorAll('.preset-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const days = parseInt(btn.dataset.days);
        const d = new Date();
        d.setDate(d.getDate() + days);
        expiresInput.value = d.toISOString().split('T')[0];
    });
});

// Gerar Link
document.getElementById('generate-link-btn').addEventListener('click', async () => {
    if (!expiresInput.value) {
        toast('Selecione uma data de expiração.', 'error');
        return;
    }

    const btn = document.getElementById('generate-link-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Gerando...';

    try {
        const res  = await fetch(API.links, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ file_id: currentFileId, expires_at: expiresInput.value }),
        });
        const data = await res.json();

        if (data.ok) {
            linkUrl.value = data.link.url;
            linkResult.style.display = 'block';
            loadFiles();
        } else {
            toast(data.error || 'Erro ao gerar link.', 'error');
        }
    } catch {
        toast('Erro de conexão.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-link"></i> Gerar Link';
    }
});

// Copiar URL
copyBtn.addEventListener('click', () => {
    if (!linkUrl.value) return;
    navigator.clipboard.writeText(linkUrl.value).then(() => {
        copyBtn.textContent = '✓ Copiado!';
        copyBtn.classList.add('copied');
        setTimeout(() => {
            copyBtn.innerHTML = '<i class="fa fa-copy"></i> Copiar';
            copyBtn.classList.remove('copied');
        }, 2000);
    });
});

// ============================================================
// UTILITÁRIOS
// ============================================================

function toast(msg, type = 'info') {
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.textContent = msg;
    toastContainer.appendChild(el);
    setTimeout(() => el.remove(), 4000);
}

function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function truncate(str, max) {
    return str.length > max ? str.substring(0, max) + '…' : str;
}

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('pt-BR', { day:'2-digit', month: '2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
}

function iconClass(faClass) {
    if (faClass.includes('pdf'))    return 'icon-pdf';
    if (faClass.includes('image'))  return 'icon-image';
    if (faClass.includes('zipper')) return 'icon-zip';
    if (faClass.includes('word'))   return 'icon-word';
    if (faClass.includes('excel'))  return 'icon-excel';
    return 'icon-other';
}

// ============================================================
// INIT
// ============================================================
checkSession();
