/**
 * Infini Finance - Core JS Engine
 */

const API_BASE = '/api/index.php?action=';

const app = {
    user: null,
    currentView: 'projects',
    currentProjectId: null,
    data: {
        projects: [],
        categories: [],
        partners: []
    },

    init: async function () {
        await this.checkAuth();
        await this.fetchInitialData();
        this.navigate('projects');
    },

    // ------------------------------------------------------------------------
    // Roteamento
    // ------------------------------------------------------------------------
    navigate: function (view) {
        this.currentView = view;
        
        // Ativar CSS do menu ativo
        document.querySelectorAll('.sidebar-link').forEach(el => el.classList.remove('active', 'text-brand-green'));
        const activeLink = document.getElementById(`menu-${view}`);
        if(activeLink) activeLink.classList.add('active', 'text-brand-green');

        // Mostra menu dinâmico de projeto se um projeto estiver selecionado
        const ctxMenu = document.getElementById('projectContextLinks');
        if (this.currentProjectId && (view === 'entries' || view === 'reports')) {
            ctxMenu.classList.remove('hidden');
        } else if (view === 'projects' || view === 'categories') {
            ctxMenu.classList.add('hidden');
            this.currentProjectId = null; // reseta
        }

        const container = document.getElementById('mainContainer');
        container.innerHTML = '<div class="flex justify-center p-20"><i class="fa-solid fa-circle-notch fa-spin text-4xl text-brand-green"></i></div>';

        // Carrega View
        setTimeout(() => {
            switch (view) {
                case 'projects': this.renderProjects(); break;
                case 'categories': this.renderCategories(); break;
                case 'entries': this.renderEntries(); break;
                case 'reports': this.renderReports(); break;
            }
        }, 150);

        // Esconde menu mobile após clique
        document.getElementById('mobileMenu').classList.add('hidden');
    },

    selectProject: function(id, name) {
        this.currentProjectId = id;
        document.getElementById('navProjectTitle').innerText = name.substring(0,25) + (name.length > 25 ? '...' : '');
        this.navigate('entries'); // Vai pra visão interna do projeto
    },

    // ------------------------------------------------------------------------
    // API Wrappers
    // ------------------------------------------------------------------------
    fetch: async function (action, method = 'GET', body = null) {
        const options = { method, headers: { 'Content-Type': 'application/json' } };
        if (body) options.body = JSON.stringify(body);

        try {
            const r = await fetch(API_BASE + action, options);
            if (r.status === 401) {
                window.location.href = '/index.html';
                return null;
            }
            return await r.json();
        } catch (e) {
            this.toast('Erro de comunicação com o servidor.', true);
            return null;
        }
    },

    checkAuth: async function () {
        const res = await this.fetch('check_auth');
        if (!res || !res.authenticated) {
            window.location.href = '/index.html';
        } else {
            this.user = res.user;
            document.getElementById('userNameLabel').innerText = this.user.username;
            document.getElementById('userInitial').innerText = this.user.username.charAt(0).toUpperCase();
        }
    },

    logout: async function () {
        await this.fetch('logout');
        window.location.href = '/index.html';
    },

    fetchInitialData: async function() {
        // Carrega parceiros e categorias para formulários
        const p = await this.fetch('partners');
        if(p && p.success) this.data.partners = p.data;

        const c = await this.fetch('categories');
        if(c && c.success) this.data.categories = c.data;
    },

    // ------------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------------
    toast: function (msg, isError = false) {
        const t = document.getElementById("toast");
        document.getElementById("toastMsg").innerText = msg;
        const icon = document.getElementById("toastIcon");
        icon.className = isError ? "fa-solid fa-circle-exclamation" : "fa-solid fa-check-circle";
        t.style.backgroundColor = isError ? '#ef4444' : '#22c55e';
        t.className = "toast shadow-2xl show";
        setTimeout(() => { t.className = t.className.replace("show", ""); }, 3000);
    },

    formatBRL: function(value) {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
    },

    formatDate: function(dateStr) {
        if(!dateStr) return '';
        const [y,m,d] = dateStr.split(' ')[0].split('-');
        return `${d}/${m}/${y}`;
    },

    buildModal: function(id, title, formHtml, submitAction) {
        const container = document.getElementById('modalContainer');
        container.innerHTML = `
            <div id="${id}" class="fixed inset-0 modal-bg z-[100] flex items-center justify-center p-4">
                <div class="glass-panel p-6 md:p-8 rounded-2xl shadow-2xl w-full max-w-lg relative border border-slate-600">
                    <button onclick="document.getElementById('${id}').remove()" class="absolute top-4 right-4 text-slate-400 hover:text-white transition">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                    <h2 class="text-xl font-bold text-white mb-6 border-b border-slate-700 pb-3">${title}</h2>
                    <form id="${id}Form" class="space-y-4">
                        ${formHtml}
                        <div class="pt-4 flex justify-end gap-3 border-t border-slate-700 mt-6">
                            <button type="button" onclick="document.getElementById('${id}').remove()" class="px-4 py-2 text-sm text-slate-300 hover:text-white transition">Cancelar</button>
                            <button type="submit" class="bg-brand-green hover:bg-green-600 text-white px-6 py-2 rounded-lg font-medium transition shadow-lg">Salvar</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        document.getElementById(`${id}Form`).addEventListener('submit', (e) => {
            e.preventDefault();
            submitAction();
        });
    },

    // ------------------------------------------------------------------------
    // View: PROJETOS
    // ------------------------------------------------------------------------
    renderProjects: async function() {
        const res = await this.fetch('projects');
        if(!res || !res.success) return;
        this.data.projects = res.data;

        let html = `
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h2 class="text-3xl font-bold text-white">Projetos</h2>
                    <p class="text-slate-400">Gerencie todos os seus hubs financeiros.</p>
                </div>
                <button onclick="app.openProjectModal()" class="bg-brand-green hover:bg-green-600 text-white px-5 py-2.5 rounded-lg shadow-lg font-medium flex items-center gap-2 transition">
                    <i class="fa-solid fa-plus"></i> Novo Projeto
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        `;

        if (this.data.projects.length === 0) {
            html += `<div class="col-span-full text-center py-20 text-slate-500 glass-panel rounded-xl border border-slate-700/50"><i class="fa-solid fa-folder-open text-4xl mb-3"></i><p>Nenhum projeto cadastrado.</p></div>`;
        } else {
            this.data.projects.forEach(p => {
                const statusColor = p.status === 'open' ? 'text-brand-green bg-green-900/30 border-brand-green/30' : 'text-slate-400 bg-slate-800 border-slate-600';
                const statusText = p.status === 'open' ? 'Em andamento' : 'Fechado';
                
                html += `
                <div class="glass-panel rounded-xl p-6 border border-slate-700/50 hover:border-slate-500 transition cursor-pointer group flex flex-col h-full" onclick="app.selectProject(${p.id}, '${p.name}')">
                    <div class="flex justify-between items-start mb-4">
                        <span class="text-xs font-semibold px-2 py-1 rounded-md border ${statusColor}">${statusText}</span>
                        <span class="text-slate-500 text-sm group-hover:text-brand-green transition"><i class="fa-solid fa-arrow-right"></i></span>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-1 truncate" title="${p.name}">${p.name}</h3>
                    <p class="text-sm text-slate-400 mb-4 truncate"><i class="fa-solid fa-building text-xs mr-1 opacity-70"></i> ${p.client || 'Sem cliente'}</p>
                    
                    <div class="mt-auto pt-4 border-t border-slate-700/50 flex justify-between text-sm text-slate-400">
                        <div title="Sócio A / Sócio B split %"><i class="fa-solid fa-chart-pie mr-1 opacity-70"></i> ${p.split_a}/${p.split_b}%</div>
                        <div title="Criado em"><i class="fa-regular fa-calendar mr-1 opacity-70"></i> ${this.formatDate(p.created_at)}</div>
                    </div>
                </div>`;
            });
        }
        
        html += `</div>`;
        document.getElementById('mainContainer').innerHTML = html;
    },

    openProjectModal: function() {
        const content = `
            <div>
                <label class="block text-sm text-slate-300 mb-1">Nome do Projeto *</label>
                <input type="text" id="p_name" required class="w-full bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white focus:border-brand-green outline-none">
            </div>
            <div>
                <label class="block text-sm text-slate-300 mb-1">Cliente</label>
                <input type="text" id="p_client" class="w-full bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white focus:border-brand-green outline-none">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-slate-300 mb-1">Lucro Sócio A (%)</label>
                    <input type="number" id="p_split_a" value="50" min="0" max="100" class="w-full bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white outline-none">
                </div>
                <div>
                    <label class="block text-sm text-slate-300 mb-1">Lucro Sócio B (%)</label>
                    <input type="number" id="p_split_b" value="50" readOnly class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-400 outline-none cursor-not-allowed">
                </div>
            </div>
            <div>
                <label class="block text-sm text-slate-300 mb-1">Notas (Opcional)</label>
                <textarea id="p_notes" rows="3" class="w-full bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white focus:border-brand-green outline-none"></textarea>
            </div>
        `;

        this.buildModal('newProjectModal', 'Novo Projeto', content, async () => {
            const splitA = parseInt(document.getElementById('p_split_a').value);
            const body = {
                name: document.getElementById('p_name').value,
                client: document.getElementById('p_client').value,
                split_a: splitA,
                split_b: 100 - splitA,
                notes: document.getElementById('p_notes').value
            };
            
            const res = await this.fetch('projects', 'POST', body);
            if(res && res.success) {
                this.toast('Projeto criado com sucesso!');
                document.getElementById('newProjectModal').remove();
                this.navigate('projects');
            } else {
                this.toast(res?.message || 'Erro', true);
            }
        });

        // Auto-calcula Split B
        document.getElementById('p_split_a').addEventListener('input', (e) => {
            let val = parseInt(e.target.value);
            if(isNaN(val)) val = 0;
            if(val > 100) val = 100;
            if(val < 0) val = 0;
            e.target.value = val;
            document.getElementById('p_split_b').value = 100 - val;
        });
    },

    // ------------------------------------------------------------------------
    // View: LANÇAMENTOS (Internal Project Vision)
    // ------------------------------------------------------------------------
    renderEntries: async function() {
        if(!this.currentProjectId) return this.navigate('projects');
        
        // Pega os dados brutos da visão do reports.php para ter os totais em real time no topo
        const res = await this.fetch(`reports&project_id=${this.currentProjectId}`);
        if(!res || !res.success) return this.navigate('projects');
        
        const sum = res.data;
        const p = sum.project;

        let html = `
            <div class="flex justify-between items-start mb-8">
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <button onclick="app.navigate('projects')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-arrow-left"></i></button>
                        <h2 class="text-3xl font-bold text-white">${p.name}</h2>
                        ${p.status === 'open' 
                            ? '<span class="px-2 font-mono py-0.5 text-xs bg-green-900/50 text-brand-green border border-brand-green/30 rounded">OPEN</span>'
                            : '<span class="px-2 font-mono py-0.5 text-xs bg-slate-800 text-slate-400 border border-slate-600 rounded">CLOSED</span>'
                        }
                    </div>
                    <p class="text-slate-400 pl-8"><i class="fa-solid fa-building text-xs mr-1 opacity-70"></i> ${p.client || 'Sem cliente'} — Split: Sócio A ${p.split_a}% / Sócio B ${p.split_b}%</p>
                </div>
                ${p.status === 'open' 
                    ? `<div class="flex gap-2">
                        <button onclick="app.openEntryModal()" class="bg-brand-green hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium shadow-lg transition text-sm flex items-center gap-2"><i class="fa-solid fa-plus"></i> Lançamento</button>
                        <button onclick="app.closeProject(${p.id})" class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-lg font-medium border border-slate-600 transition text-sm flex items-center gap-2" title="Encerrar Projeto"><i class="fa-solid fa-lock"></i></button>
                       </div>`
                    : ''
                }
            </div>

            <!-- Resumo Rápido -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="glass-panel p-5 rounded-xl border border-slate-700/50">
                    <p class="text-sm font-medium text-slate-400 uppercase tracking-wider mb-1">Despesas</p>
                    <p class="text-2xl font-bold text-brand-red">${this.formatBRL(sum.total_expenses)}</p>
                </div>
                <div class="glass-panel p-5 rounded-xl border border-slate-700/50">
                    <p class="text-sm font-medium text-slate-400 uppercase tracking-wider mb-1">Receitas</p>
                    <p class="text-2xl font-bold text-brand-green">${this.formatBRL(sum.total_income)}</p>
                </div>
                <div class="glass-panel p-5 rounded-xl border border-brand-green/30 bg-[linear-gradient(45deg,rgba(34,197,94,0.05)_0%,rgba(15,23,42,0)_100%)]">
                    <p class="text-sm font-medium text-slate-400 uppercase tracking-wider mb-1">Lucro Bruto</p>
                    <p class="text-2xl font-bold text-white">${this.formatBRL(sum.gross_profit)}</p>
                </div>
            </div>

            <div class="glass-panel rounded-xl border border-slate-700/50 overflow-hidden">
                <div class="flex justify-between items-center p-4 border-b border-slate-700/50 bg-slate-800/20">
                    <h3 class="font-bold text-white">Últimos Lançamentos</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-800/50 text-xs uppercase text-slate-400 font-semibold tracking-wider">
                                <th class="p-4 border-b border-slate-700">Data</th>
                                <th class="p-4 border-b border-slate-700">Tipo</th>
                                <th class="p-4 border-b border-slate-700">Descrição</th>
                                <th class="p-4 border-b border-slate-700">Categoria</th>
                                <th class="p-4 border-b border-slate-700">Pago Por</th>
                                <th class="p-4 border-b border-slate-700 text-right">Valor</th>
                                <th class="p-4 border-b border-slate-700 w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-slate-700/30">
        `;

        const allEntries = [...sum.income_entries, ...sum.expense_entries].sort((a,b) => new Date(b.entry_date) - new Date(a.entry_date) || b.id - a.id);

        if(allEntries.length === 0) {
            html += `<tr><td colspan="7" class="p-8 text-center text-slate-500">Nenhum lançamento registrado neste projeto.</td></tr>`;
        } else {
            allEntries.forEach(e => {
                const isInc = e.type === 'income';
                const typeIcon = isInc ? '<i class="fa-solid fa-arrow-trend-up text-brand-green"></i> Receita' : '<i class="fa-solid fa-arrow-trend-down text-brand-red"></i> Despesa';
                const valClass = isInc ? 'text-brand-green' : 'text-white';
                
                html += `
                    <tr class="hover:bg-slate-800/30 transition">
                        <td class="p-4 text-slate-300 whitespace-nowrap">${this.formatDate(e.entry_date)}</td>
                        <td class="p-4 font-medium whitespace-nowrap">${typeIcon}</td>
                        <td class="p-4 text-white">${e.description}</td>
                        <td class="p-4 text-slate-400">${isInc ? '-' : (e.category_name || '—')}</td>
                        <td class="p-4 text-slate-400">
                            <span class="bg-slate-800 border border-slate-600 px-2 py-1 rounded text-xs"><i class="${e.partner_type==='pf'?'fa-solid fa-user':'fa-solid fa-building'} mr-1 opacity-50"></i> ${e.partner_name}</span>
                        </td>
                        <td class="p-4 text-right font-medium ${valClass}">${isInc ? '+ ' : '- '}${this.formatBRL(e.amount)}</td>
                        <td class="p-4 text-center">
                            ${p.status === 'open' ? `<button onclick="app.deleteEntry(${e.id})" class="text-slate-500 hover:text-red-400 transition" title="Excluir"><i class="fa-solid fa-trash"></i></button>` : ''}
                        </td>
                    </tr>
                `;
            });
        }

        html += `</tbody></table></div></div>`;
        document.getElementById('mainContainer').innerHTML = html;
    },

    openEntryModal: function() {
        if(!this.data.categories.length) this.fetchInitialData();

        const catOpts = this.data.categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
        const partnerOpts = this.data.partners.map(p => `<option value="${p.id}">${p.name} (${p.type.toUpperCase()})</option>`).join('');

        const content = `
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-slate-300 mb-1">Tipo *</label>
                    <select id="e_type" onchange="app.toggleCategoryField()" class="w-full bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white outline-none">
                        <option value="expense">Despesa</option>
                        <option value="income">Receita</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-slate-300 mb-1">Data *</label>
                    <input type="date" id="e_date" value="${new Date().toISOString().split('T')[0]}" required class="w-full bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white outline-none" style="color-scheme: dark;">
                </div>
            </div>

            <div>
                <label class="block text-sm text-slate-300 mb-1">Descrição do Lançamento *</label>
                <input type="text" id="e_desc" required placeholder="Ex: Pagamento Freelancer Dev" class="w-full bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white outline-none">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-slate-300 mb-1">Valor (R$) *</label>
                    <input type="number" step="0.01" id="e_amount" required placeholder="0.00" class="w-full bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white outline-none">
                </div>
                <div>
                    <label class="block text-sm text-slate-300 mb-1">Pago Por (Origem) *</label>
                    <select id="e_paidby" class="w-full bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white outline-none">
                        ${partnerOpts}
                    </select>
                </div>
            </div>

            <div id="wrapper_cat">
                <label class="block text-sm text-slate-300 mb-1">Categoria</label>
                <select id="e_cat" class="w-full bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white outline-none">
                    <option value="">Selecione...</option>
                    ${catOpts}
                </select>
            </div>
        `;

        this.buildModal('newEntryModal', 'Novo Lançamento', content, async () => {
            const body = {
                project_id: this.currentProjectId,
                type: document.getElementById('e_type').value,
                description: document.getElementById('e_desc').value,
                amount: document.getElementById('e_amount').value,
                paid_by: document.getElementById('e_paidby').value,
                category_id: document.getElementById('e_type').value === 'income' ? null : document.getElementById('e_cat').value,
                entry_date: document.getElementById('e_date').value
            };
            
            const res = await this.fetch('entries', 'POST', body);
            if(res && res.success) {
                this.toast('Lançamento registrado!');
                document.getElementById('newEntryModal').remove();
                this.renderEntries();
            } else {
                this.toast(res?.message || 'Erro', true);
            }
        });
    },

    toggleCategoryField: function() {
        const type = document.getElementById('e_type').value;
        const w = document.getElementById('wrapper_cat');
        if(type === 'income') w.classList.add('hidden');
        else w.classList.remove('hidden');
    },

    deleteEntry: async function(id) {
        if(!confirm('Deseja realmente excluir este lançamento? Alterará os saldos permanentemente.')) return;
        const res = await this.fetch(`entries&id=${id}`, 'DELETE');
        if(res && res.success) {
            this.toast('Lançamento removido.');
            this.renderEntries();
        } else {
            this.toast(res?.message || 'Erro', true);
        }
    },

    closeProject: async function(id) {
        if(!confirm('Tem certeza? Uma vez fechado não será possível lançar novas receitas ou despesas, e ele representará o consolidado financeiro real da sociedade.')) return;
        const res = await this.fetch('projects', 'PUT', {id: id});
        if(res && res.success) {
            this.toast('Projeto encerrado e arquivado.');
            this.renderEntries();
        } else {
            this.toast('Erro ao fechar.', true);
        }
    },

    // ------------------------------------------------------------------------
    // View: REPORTS (Fechamento e Divisão)
    // ------------------------------------------------------------------------
    renderReports: async function() {
        if(!this.currentProjectId) return this.navigate('projects');
        
        const res = await this.fetch(`reports&project_id=${this.currentProjectId}`);
        if(!res || !res.success) return this.navigate('projects');
        const s = res.data;
        const p = s.project;

        let html = `
            <div class="flex justify-between items-start mb-8 no-print">
                <div>
                    <h2 class="text-3xl font-bold text-white">Relatório de Fechamento</h2>
                    <p class="text-slate-400">${p.name} • ${p.status === 'open' ? 'Parcial' : 'Consolidado Final'}</p>
                </div>
                <button onclick="window.print()" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-lg shadow-lg font-medium flex items-center gap-2 transition"><i class="fa-solid fa-print"></i> Imprimir</button>
            </div>

            <!-- Print Header só visível na impressão -->
            <div class="hidden print:block mb-8 border-b pb-4 border-black">
                <h1 class="text-3xl font-bold text-black uppercase">Fechamento: ${p.name}</h1>
                <p class="text-gray-600 mt-1">Sócio A: ${p.split_a}% | Sócio B: ${p.split_b}% • Data do relatório: ${new Date().toLocaleDateString('pt-BR')}</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Summary Card -->
                <div class="glass-panel p-6 rounded-2xl border border-slate-700/50 h-full flex flex-col justify-center">
                    <h3 class="text-lg font-bold text-white mb-6 uppercase tracking-wider text-center"><i class="fa-solid fa-scale-balanced mr-2"></i> Caixa e DRE</h3>
                    
                    <div class="flex justify-between items-center py-3 border-b border-slate-700/50">
                        <span class="text-slate-400">Total Receitas (+)</span>
                        <span class="font-bold text-brand-green">${this.formatBRL(s.total_income)}</span>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-slate-700/50">
                        <span class="text-slate-400">Custos & Despesas (-)</span>
                        <span class="font-bold text-brand-red">${this.formatBRL(s.total_expenses)}</span>
                    </div>
                    <div class="flex justify-between items-center py-4 mt-2">
                        <span class="text-white font-bold text-xl uppercase">Lucro Bruto (=)</span>
                        <span class="font-black text-2xl text-white">${this.formatBRL(s.gross_profit)}</span>
                    </div>
                </div>

                <!-- Reembolsos de PF Card -->
                <div class="glass-panel p-6 rounded-2xl border border-amber-900/30 bg-amber-900/10 h-full">
                    <h3 class="text-lg font-bold text-amber-500 mb-6 uppercase tracking-wider"><i class="fa-solid fa-hand-holding-dollar mr-2"></i> Reembolsos Devidos (Sócio PF)</h3>
                    <p class="text-sm text-slate-400 mb-4">Sócios retiraram do próprio bolso para bancar as despesas abaixo e devem ser integralmente ressarcidos antes de fruir o split de lucro liquidado da operação.</p>
        `;

        if (s.reimbursements.length === 0) {
            html += `<div class="p-4 bg-slate-800/50 rounded-lg text-center text-slate-500">Nenhum reembolso devido a pessoa física neste projeto. As contas foram arcadas integralmente pelo caixa PJ.</div>`;
        } else {
            html += `<div class="space-y-3">`;
            s.reimbursements.forEach(r => {
                html += `
                <div class="flex justify-between items-center bg-amber-900/20 border border-amber-500/20 px-4 py-3 rounded-lg">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-user-shield text-amber-500"></i>
                        <span class="text-white font-medium">${r.partner_name} antecipou</span>
                    </div>
                    <span class="font-bold text-amber-400">${this.formatBRL(r.amount_to_reimburse)}</span>
                </div>`;
            });
            html += `</div>`;
        }

        html += `</div></div>`; // End Grid DRE e Reembolsos

        // LÍQUIDO A RECEBER (THE REAL DEAL)
        html += `
            <h3 class="text-xl font-bold text-white mb-4 uppercase tracking-wider mt-12"><i class="fa-solid fa-money-bill-wave text-brand-green mr-2"></i> Split & Faturamento Líquido Final</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12">
        `;

        // Partner A
        const grossA = s.split.partner_a;
        const netA = s.net_to_receive.partner_a;
        const reimbA = s.reimbursements.find(x => x.partner_id === 1)?.amount_to_reimburse || 0;
        
        html += this.buildPartnerNetCard('Sócio A', p.split_a, grossA, reimbA, netA);

        // Partner B
        const grossB = s.split.partner_b;
        const netB = s.net_to_receive.partner_b;
        const reimbB = s.reimbursements.find(x => x.partner_id === 2)?.amount_to_reimburse || 0;
        
        html += this.buildPartnerNetCard('Sócio B', p.split_b, grossB, reimbB, netB);

        html += `</div>`;
        document.getElementById('mainContainer').innerHTML = html;
    },

    buildPartnerNetCard: function(name, percent, splitVal, reimb, netVal) {
        return `
            <div class="glass-panel p-6 rounded-2xl border-2 border-brand-green/30 relative overflow-hidden">
                <div class="absolute top-0 right-0 bg-brand-green/20 text-brand-green px-4 py-1 font-bold text-sm rounded-bl-lg">${percent}% DO LUCRO</div>
                <h4 class="text-2xl font-bold text-white mb-4">${name}</h4>
                
                <div class="space-y-2 text-sm text-slate-300 mb-6">
                    <div class="flex justify-between border-b border-slate-700/30 pb-1">
                        <span>Fatia no Lucro Líquido:</span>
                        <span class="font-mono text-white">${this.formatBRL(splitVal)}</span>
                    </div>
                    ${reimb > 0 ? `
                    <div class="flex justify-between border-b border-slate-700/30 pb-1 pt-1 text-amber-400">
                        <span>( - ) Adiantou do Bolso PF:</span>
                        <span class="font-mono"> - ${this.formatBRL(reimb)}</span>
                    </div>
                    ` : ''}
                </div>
                
                <div class="bg-brand-green/10 p-4 rounded-xl border border-brand-green/20 flex flex-col items-center">
                    <span class="text-xs uppercase text-brand-green font-bold tracking-wider mb-1">Cair limpo na conta</span>
                    <span class="text-3xl font-black text-white">${this.formatBRL(netVal)}</span>
                </div>
            </div>
        `;
    },

    // ------------------------------------------------------------------------
    // View: CATEGORIAS (Setup)
    // ------------------------------------------------------------------------
    renderCategories: async function() {
        const res = await this.fetch('categories');
        if(!res || !res.success) return;
        this.data.categories = res.data;

        let html = `
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h2 class="text-3xl font-bold text-white">Categorias de Despesa</h2>
                    <p class="text-slate-400">Classificação para controle de gastos.</p>
                </div>
                <button onclick="app.openCategoryModal()" class="bg-slate-700 hover:bg-slate-600 text-white px-5 py-2.5 rounded-lg shadow-lg font-medium flex items-center gap-2 transition">
                    <i class="fa-solid fa-plus"></i> Nova Categoria
                </button>
            </div>
            
            <div class="glass-panel rounded-xl border border-slate-700/50 overflow-hidden max-w-2xl mx-auto">
                <table class="w-full text-left border-collapse">
                    <tbody class="divide-y divide-slate-700/30">
        `;

        this.data.categories.forEach(c => {
            html += `
                <tr class="hover:bg-slate-800/30 transition">
                    <td class="p-4 text-white font-medium"><i class="fa-solid fa-tag text-slate-500 mr-2"></i> ${c.name}</td>
                    <td class="p-4 text-right">
                        <button onclick="app.deleteCategory(${c.id})" class="text-slate-500 hover:text-red-400 transition ml-4" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                    </td>
                </tr>`;
        });
        
        html += `</tbody></table></div>`;
        document.getElementById('mainContainer').innerHTML = html;
    },

    openCategoryModal: function() {
        const content = `
            <div>
                <label class="block text-sm text-slate-300 mb-1">Nome da Categoria *</label>
                <input type="text" id="c_name" required class="w-full bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white focus:border-brand-green outline-none">
            </div>
        `;

        this.buildModal('newCatModal', 'Nova Categoria', content, async () => {
            const body = { name: document.getElementById('c_name').value };
            const res = await this.fetch('categories', 'POST', body);
            if(res && res.success) {
                this.toast('Salvo!');
                document.getElementById('newCatModal').remove();
                this.renderCategories();
            } else {
                this.toast(res?.message || 'Erro', true);
            }
        });
    },

    deleteCategory: async function(id) {
        if(!confirm('Deseja excluir esta categoria? Ela não pode estar em uso por despesas.')) return;
        const res = await this.fetch(`categories&id=${id}`, 'DELETE');
        if(res && res.success) {
            this.toast('Categoria removida.');
            this.renderCategories();
        } else {
            this.toast(res?.message || 'Erro', true);
        }
    }
};

window.addEventListener('load', () => app.init());
