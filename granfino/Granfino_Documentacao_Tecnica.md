# GRANFINO · Sistema de Gestão de Qualidade & SAC
## Documentação Técnica Completa

**Versão:** 1.0.0  
**Data:** Março de 2026  
**Desenvolvido por:** Infini Solutions  
**URL:** http://granfino.infinisolutions.cloud

---

## 1. Visão Geral do Sistema

O Sistema de Gestão de Qualidade & SAC da Granfino é uma aplicação web desenvolvida em PHP + MySQL para substituir o sistema legado em Microsoft Access. A solução centraliza o registro, acompanhamento e análise de chamadas do Serviço de Atendimento ao Consumidor (SAC), permitindo acesso remoto, multi-usuário simultâneo e geração automática de relatórios.

### 1.1 Objetivos

- Migrar o histórico de 31.783 chamadas do Access para um banco MySQL na nuvem
- Permitir acesso simultâneo por múltiplos atendentes de qualquer dispositivo
- Automatizar relatórios semanais e mensais com indicadores de qualidade
- Garantir rastreabilidade por atendente com autenticação individual
- Eliminar risco de perda de dados por falha de hardware local

### 1.2 Informações de Acesso

| Campo | Valor |
|---|---|
| URL de produção | http://granfino.infinisolutions.cloud |
| Servidor | Hostinger – subdomínio granfino.infinisolutions.cloud |
| Diretório no servidor | /home/u752688765/domains/infinisolutions.cloud/public_html/granfino |
| Banco de dados | u752688765_granfino (MySQL na Hostinger) |
| Usuário DB | u752688765_granfino |
| Login padrão | admin@granfino.com.br / (senha configurada) |

---

## 2. Stack Tecnológica

### 2.1 Back-end

| Tecnologia | Versão | Finalidade |
|---|---|---|
| PHP | 8.x | Linguagem server-side principal. Processa formulários, gerencia sessões e executa queries. |
| PDO (PHP Data Objects) | Nativo PHP 8 | Camada de abstração para conexão com MySQL. Previne SQL Injection via prepared statements. |
| Sessions PHP | Nativo PHP 8 | Autenticação de atendentes com timeout automático de 1 hora. |
| MySQL | 8.x (Hostinger) | Banco de dados relacional. Armazena chamadas, produtos e atendentes. |

### 2.2 Front-end

| Tecnologia | Finalidade |
|---|---|
| HTML5 | Estrutura das páginas. Formulários com validação nativa. |
| CSS3 (inline + variáveis) | Estilização completa. Sistema de design com CSS custom properties (variáveis). |
| JavaScript (Vanilla) | AJAX para carregamento dinâmico de municípios por estado. Confirmações de ação. |
| Google Fonts | Tipografia: Syne (títulos) + DM Sans (corpo). Carregadas via CDN. |

### 2.3 Infraestrutura

| Componente | Detalhe |
|---|---|
| Hospedagem | Hostinger – Plano compartilhado com suporte a PHP e MySQL |
| Subdomínio | granfino.infinisolutions.cloud → public_html/granfino/ |
| SSL | Certificado Let's Encrypt (gerado automaticamente pela Hostinger) |
| Backup | Backup automático da Hostinger + exportação manual via phpMyAdmin |
| Gerenciador de arquivos | Painel Hostinger – Gerenciador de Arquivos / FTP |

---

## 3. Arquitetura do Sistema

### 3.1 Padrão Arquitetural

O sistema adota uma arquitetura monolítica simples (PHP puro, sem framework), adequada para a escala e equipe do projeto. Cada arquivo PHP representa uma página/rota, com lógica de negócio embutida e dois arquivos de layout reutilizados via `include()`.

### 3.2 Fluxo de Requisição

1. Usuário acessa a URL no navegador
2. Apache/PHP processa o arquivo `.php` correspondente
3. O arquivo chama `require_once 'config.php'` para conexão DB e funções base
4. A função `auth()` verifica se há sessão ativa — redireciona para `login.php` se não houver
5. Lógica de negócio é executada (consultas, inserções, atualizações)
6. `require '_header.php'` renderiza o HTML inicial (topbar + sidebar + CSS)
7. Conteúdo da página é renderizado
8. `require '_footer.php'` fecha o HTML

### 3.3 Autenticação e Sessão

A autenticação usa sessões PHP nativas. Ao fazer login, o ID e nome do atendente são armazenados em `$_SESSION`. A função `auth()` é chamada no início de cada página protegida e verifica dois critérios: presença da sessão e tempo de inatividade (timeout de 3600 segundos). As senhas são armazenadas com hash bcrypt usando `password_hash()` e verificadas com `password_verify()`.

---

## 4. Estrutura de Arquivos

Todos os arquivos estão na raiz de `public_html/granfino/` no servidor:

| Arquivo | Tipo | Descrição |
|---|---|---|
| `config.php` | Core | Configuração do banco de dados, constantes globais e funções utilitárias (`db()`, `auth()`, `municipiosPorEstado()`) |
| `_header.php` | Layout | Cabeçalho reutilizável: topbar com logo e data/hora, sidebar de navegação, todo o CSS do sistema |
| `_footer.php` | Layout | Fechamento do HTML: tag `</main>`, rodapé com copyright |
| `login.php` | Página | Tela de autenticação. Valida e-mail e senha, cria sessão PHP |
| `logout.php` | Utilitário | Destrói a sessão e redireciona para `login.php` |
| `index.php` | Página | Formulário de nova chamada. Registra consumidor, até 4 produtos e descrição. Endpoint AJAX para municípios |
| `chamadas.php` | Página | Listagem de chamadas com busca textual, filtros de status/período e paginação (20 por página) |
| `ver_chamada.php` | Página | Detalhes completos de uma chamada específica. Permite alterar status |
| `relatorios.php` | Página | Relatórios semanal e mensal com KPIs, gráfico de barras por dia, ranking de motivos, produtos e cidades |
| `granfino_logo.png` | Asset | Logo oficial da Granfino. Exibida na tela de login e na topbar do sistema |
| `schema.sql` | SQL | Script de criação das tabelas do banco de dados e inserção do usuário admin padrão |
| `README.md` | Docs | Instruções de instalação e configuração do sistema |

---

## 5. Banco de Dados

### 5.1 Informações Gerais

| Parâmetro | Valor |
|---|---|
| SGBD | MySQL 8.x |
| Nome do banco | u752688765_granfino |
| Charset | utf8mb4 |
| Collation | utf8mb4_unicode_ci |
| Host | localhost |
| Número de tabelas | 3 |

### 5.2 Tabela: `atendentes`

Armazena os usuários do sistema (atendentes do SAC).

| Coluna | Tipo | Nulo | Default | Descrição |
|---|---|---|---|---|
| `id` | INT AUTO_INCREMENT | NÃO | — | Chave primária |
| `nome` | VARCHAR(100) | NÃO | — | Nome completo do atendente |
| `email` | VARCHAR(100) | NÃO | — | E-mail de login (UNIQUE) |
| `senha` | VARCHAR(255) | NÃO | — | Hash bcrypt da senha |
| `ativo` | TINYINT(1) | SIM | 1 | 1 = ativo, 0 = desativado |
| `criado_em` | TIMESTAMP | NÃO | CURRENT_TIMESTAMP | Data/hora de criação |

### 5.3 Tabela: `chamadas`

Tabela principal. Cada registro representa uma chamada do consumidor ao SAC.

| Coluna | Tipo | Nulo | Descrição |
|---|---|---|---|
| `id` | INT AUTO_INCREMENT | NÃO | Chave primária / número da chamada |
| `atendente_id` | INT | SIM | FK → atendentes.id (SET NULL se atendente excluído) |
| `nome_consumidor` | VARCHAR(150) | SIM | Nome completo do consumidor |
| `telefone` | VARCHAR(30) | SIM | Telefone de contato |
| `endereco` | VARCHAR(200) | SIM | Endereço completo |
| `bairro` | VARCHAR(100) | SIM | Bairro |
| `estado` | CHAR(2) | SIM | UF (ex: RJ, SP) |
| `municipio` | VARCHAR(100) | SIM | Nome do município |
| `ponto_referencia` | VARCHAR(200) | SIM | Ponto de referência para agendamento |
| `motivo` | VARCHAR(100) | SIM | Motivo da chamada (ex: Solicitação de troca) |
| `descricao_geral` | TEXT | SIM | Descrição detalhada do problema |
| `observacoes_gerais` | TEXT | SIM | Observações adicionais do atendente |
| `horario_preferencial` | VARCHAR(50) | SIM | Horário preferido para agendamento |
| `status` | ENUM | NÃO | `aberta` \| `em_andamento` \| `fechada` (default: aberta) |
| `criado_em` | TIMESTAMP | NÃO | Data/hora de registro (CURRENT_TIMESTAMP) |
| `atualizado_em` | TIMESTAMP | NÃO | Atualizado automaticamente em cada UPDATE |

### 5.4 Tabela: `chamada_produtos`

Produtos associados a uma chamada. Cada chamada pode ter até 4 produtos.

| Coluna | Tipo | Nulo | Descrição |
|---|---|---|---|
| `id` | INT AUTO_INCREMENT | NÃO | Chave primária |
| `chamada_id` | INT | NÃO | FK → chamadas.id (CASCADE DELETE) |
| `produto` | VARCHAR(150) | SIM | Nome do produto (ex: Fubá Degerminado) |
| `quantidade` | VARCHAR(100) | SIM | Quantidade e embalagem (ex: 1 pacote 500g) |
| `lote` | VARCHAR(50) | SIM | Número do lote impresso na embalagem |
| `fabricacao` | DATE | SIM | Data de fabricação |
| `validade` | DATE | SIM | Data de validade |
| `local_compra` | VARCHAR(150) | SIM | Local onde o produto foi comprado |

### 5.5 Relacionamentos

```
atendentes (1) ──────── (N) chamadas
                              atendente_id → atendentes.id
                              ON DELETE SET NULL

chamadas (1) ──────── (N) chamada_produtos
                              chamada_id → chamadas.id
                              ON DELETE CASCADE
```

- **chamadas → atendentes** `(ON DELETE SET NULL)`: se um atendente for excluído, suas chamadas são mantidas com `atendente_id = NULL`
- **chamada_produtos → chamadas** `(ON DELETE CASCADE)`: se uma chamada for excluída, todos os seus produtos são excluídos automaticamente

---

## 6. Páginas do Sistema

### 6.1 `login.php` — Tela de Login

| Item | Detalhe |
|---|---|
| URL | `/login.php` |
| Acesso | Público (não requer autenticação) |
| Método HTTP | GET (exibição) + POST (autenticação) |
| Lógica | Verifica e-mail + senha com `password_verify()`. Em caso de sucesso, cria `$_SESSION['atendente_id']` e redireciona para `index.php` |
| Segurança | Redireciona para `index.php` se sessão já ativa. Exibe alerta de timeout se sessão expirou |

### 6.2 `index.php` — Nova Chamada

| Item | Detalhe |
|---|---|
| URL | `/index.php` |
| Acesso | Requer autenticação |
| Método HTTP | GET (formulário) + POST (salvar) + `GET?ajax=municipios` (AJAX) |
| Lógica | Formulário com 3 seções: Perfil do consumidor, Produtos (até 4) e Descrição. Ao salvar, usa transação PDO para inserir em `chamadas` e `chamada_produtos` atomicamente |
| Transação | `beginTransaction()` → INSERT chamadas → INSERT chamada_produtos (loop) → `commit()`. Em erro: `rollBack()` |

### 6.3 `chamadas.php` — Listagem de Chamadas

| Item | Detalhe |
|---|---|
| URL | `/chamadas.php` |
| Acesso | Requer autenticação |
| Filtros | Busca textual (nome, telefone, nº chamada, motivo) + Status + Período (hoje / 7 dias / 30 dias) |
| Paginação | 20 registros por página com links de navegação |
| Ações inline | POST para fechar chamada diretamente na listagem |
| Query | JOIN com tabela `atendentes` para exibir nome. WHERE dinâmico com prepared statements |

### 6.4 `ver_chamada.php` — Detalhes da Chamada

| Item | Detalhe |
|---|---|
| URL | `/ver_chamada.php?id={id}` |
| Acesso | Requer autenticação |
| Exibe | Todos os dados da chamada + lista de produtos + histórico de atualização |
| Ação | POST para alterar status (aberta → em_andamento → fechada) |
| Layout | Duas colunas: dados principais (esquerda) + status e info (direita) |

### 6.5 `relatorios.php` — Relatórios

| Item | Detalhe |
|---|---|
| URL | `/relatorios.php?tipo=semanal\|mensal` |
| Acesso | Requer autenticação |
| Tipos | Semanal (segunda a domingo, navegável) e Mensal (navegável mês a mês) |
| KPIs | Total de chamadas, Abertas, Em andamento, Fechadas |
| Gráficos | Chamadas por dia (barras HTML/CSS), Ranking por motivo, Ranking por produto, Top 5 cidades |
| Queries | 4 queries com filtro `DATE BETWEEN` para o período selecionado |

---

## 7. Lógicas e Funções Principais

### 7.1 `config.php` — Funções Globais

#### `db()` — Singleton de Conexão PDO

Implementa o padrão Singleton para a conexão com o banco de dados. A variável estática `$pdo` garante que apenas uma conexão seja aberta por requisição.

```php
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
```

#### `auth()` — Verificação de Autenticação

Chamada no início de cada página protegida. Verifica: (1) se a sessão PHP existe, (2) se `$_SESSION['atendente_id']` está definido, (3) se o tempo desde a última atividade é menor que `SESSION_TIMEOUT` (3600s).

```php
function auth(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['atendente_id'])) {
        header('Location: login.php');
        exit;
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}
```

#### `municipiosPorEstado(string $uf)` — Lista de Municípios

Retorna array com os principais municípios de cada UF. Utilizada pelo endpoint AJAX em `index.php` para popular dinamicamente o select de municípios. Cobre 16 estados: RJ, SP, MG, ES, BA, PE, CE, PA, AM, RS, SC, PR, GO, DF, MT, MS.

### 7.2 Segurança

| Ameaça | Mitigação Implementada |
|---|---|
| SQL Injection | 100% das queries usam PDO com prepared statements (`?`). Nenhuma interpolação de variáveis em SQL. |
| XSS | Toda saída de dados de usuário usa `htmlspecialchars()` antes de renderizar no HTML. |
| Acesso não autorizado | Função `auth()` no topo de cada página protegida. Redireciona para login se sem sessão. |
| Session Hijacking | Timeout de sessão de 1 hora. Sessão destruída no logout. |
| Senhas | Armazenadas com bcrypt via `password_hash(PASSWORD_DEFAULT)`. Nunca em texto puro. |
| CSRF | Formulários de ação crítica usam POST. **Pendente:** token CSRF explícito. |

---

## 8. Listas Pré-cadastradas

### 8.1 Produtos (select no formulário)

| # | Produto |
|---|---|
| 1 | Farinha Mandioca Torrada |
| 2 | Farinha de Mandioca Crua |
| 3 | Farinha de Trigo |
| 4 | Farinha de Milho |
| 5 | Fubá |
| 6 | Fubá Degerminado |
| 7 | Fubarina |
| 8 | Flocão |
| 9 | Amido de Milho |
| 10 | Creme de Milho |
| 11 | Polvilho Azedo |
| 12 | Polvilho Doce |
| 13 | Tapioca Granulada |
| 14 | Canjica Branca |
| 15 | Canjiquinha Vermelha |
| 16 | Ervilha |
| 17 | Feijão Carioquinha |
| 18 | Feijão Preto |
| 19 | Feijão Fradinho |
| 20 | Feijão Manteiga |
| 21 | Feijão Vermelho |
| 22 | Feijão Mulatinho |
| 23 | Feijão Branco |
| 24 | Arroz Integral |
| 25 | Grão de Bico |
| 26 | Trigo p/ Kibe |
| 27 | Milho Pipoca |

### 8.2 Motivos de Chamada (select no formulário)

| # | Motivo |
|---|---|
| 1 | Solicitação de troca |
| 2 | Reclamação de qualidade |
| 3 | Produto com defeito |
| 4 | Produto vencido |
| 5 | Embalagem danificada |
| 6 | Corpo estranho |
| 7 | Cor/odor/sabor alterado |
| 8 | Informação sobre produto |
| 9 | Elogio |
| 10 | Outro |

---

## 9. Dados Históricos

### 9.1 Origem dos Dados

O sistema foi criado originalmente em Microsoft Access em 2005. O banco original (arquivo `MAIN_DATA_BASE.xml`, exportado do Access) contém 31.783 registros de chamadas coletadas entre 2005 e 2026.

### 9.2 Análise do Banco Original

| Indicador | Valor |
|---|---|
| Total de chamadas | 31.783 |
| Principal motivo | Solicitação de troca (62% — 19.556 registros) |
| Segundo motivo | Reclamação (14% — 4.583 registros) |
| Produto mais reclamado | Fubá Degerminado (3.987 ocorrências) |
| Principal atendente histórica | Karina (6.869 atendimentos — 23% do total) |
| Principal cidade | Rio de Janeiro (18.664 registros — 63%) |
| Principal estado | RJ (29.758 registros — 94%) |
| Atendentes históricos | 40+ nomes identificados no banco original |

### 9.3 Base de Dados Fictícia para Testes

Foi gerado o arquivo `granfino_dados_2025.sql` com **3.000 chamadas** e **4.008 produtos** distribuídos ao longo de 2025, simulando distribuições realistas baseadas no banco original: motivos nas mesmas proporções, produtos nos mesmos rankings, atendentes reais e cidades do RJ.

---

## 10. Changelog

### v1.0.0 — Março de 2026 — Lançamento Inicial

#### Arquivos Criados

| Arquivo | Descrição |
|---|---|
| `schema.sql` | Criação das 3 tabelas e usuário admin padrão. Corrigido erro #1075 (dois AUTO_INCREMENT) removendo coluna `numero_chamada` redundante |
| `config.php` | Configuração PDO com singleton, função `auth()` com timeout, `municipiosPorEstado()` com 16 estados |
| `_header.php` | Layout base completo: topbar, sidebar, CSS com variáveis, cards, tabelas, badges, botões e responsividade |
| `_footer.php` | Fechamento do layout HTML |
| `login.php` | Tela de login com identidade visual Granfino. Tipografia Syne + DM Sans. Alerta de sessão expirada |
| `logout.php` | Destrói sessão e redireciona |
| `index.php` | Formulário de nova chamada com 3 seções, 4 blocos de produto, endpoint AJAX e transação PDO |
| `chamadas.php` | Listagem com busca textual, 3 filtros, paginação de 20 registros e fechar chamada inline |
| `ver_chamada.php` | Detalhe completo com layout de duas colunas e alteração de status |
| `relatorios.php` | Relatórios semanal e mensal com 4 KPIs, gráficos e top 5 cidades |
| `granfino_logo.png` | Logo oficial da Granfino integrada ao sistema |
| `README.md` | Documentação de instalação com passo a passo para Hostinger |

#### Problemas Resolvidos no Deploy

| Problema | Causa | Solução |
|---|---|---|
| `Cannot GET /granfino/` | Arquivos PHP servidos pelo Node.js (pasta errada) | Criação do subdomínio apontando para `public_html/granfino/` |
| `ERR_SSL_PROTOCOL_ERROR` | Certificado SSL ainda não gerado para o novo subdomínio | Aguardar geração automática (30 min) ou forçar via painel SSL |
| `#1075 Incorrect table definition` | Tabela `chamadas` com dois campos AUTO_INCREMENT | Removida coluna `numero_chamada`; `id` passa a ser o número da chamada |
| `Access denied (seu_usuario)` | Upload do zip antigo com credenciais placeholder | Edição direta do `config.php` no Gerenciador de Arquivos |
| `Access denied` (credenciais corretas) | Senha com caractere `#` rejeitada pelo MySQL | Troca da senha por `GranfinoSAC2026` + atualização no `config.php` |
| Logo quebrada | Arquivo PNG corrompido no upload | Re-download da logo e novo upload |

---

## 11. Próximos Passos Recomendados

### 11.1 Alta Prioridade

- [ ] Executar `schema.sql` no phpMyAdmin para criar as tabelas no banco de produção
- [ ] Cadastrar os atendentes reais com e-mails e senhas individuais
- [ ] Importar os 31.783 registros históricos do Access via script de migração XML→SQL

### 11.2 Curto Prazo

- [ ] Exportar relatório para PDF (biblioteca TCPDF ou mPDF)
- [ ] CRUD de atendentes: cadastrar, editar e desativar usuários pelo sistema
- [ ] Token CSRF nos formulários POST
- [ ] Campo de retorno ao consumidor: registrar o resultado do agendamento

### 11.3 Médio Prazo

- [ ] Upload de foto do produto com problema
- [ ] Notificação por e-mail ao registrar chamada (PHPMailer + SMTP)
- [ ] Dashboard com gráficos na página inicial
- [ ] Log de auditoria: todas as alterações com timestamp e usuário
- [ ] API REST para integração com sistemas de terceiros

---

## 12. Glossário

| Termo | Definição |
|---|---|
| SAC | Serviço de Atendimento ao Consumidor |
| Chamada | Registro de contato de um consumidor com o SAC da Granfino |
| Atendente | Funcionário da Granfino que registra e acompanha as chamadas |
| Lote | Número de identificação do lote de fabricação impresso na embalagem |
| Status aberta | Chamada recém registrada, ainda não iniciado o atendimento |
| Status em andamento | Chamada em processo de resolução (ex: agendamento marcado) |
| Status fechada | Chamada encerrada — problema resolvido ou caso encerrado |
| PDO | PHP Data Objects — extensão do PHP para acesso a bancos com prepared statements |
| Bcrypt | Algoritmo de hash para senhas, resistente a ataques de força bruta |
| Singleton | Padrão de projeto que garante uma única instância de objeto (aqui: conexão PDO) |
| AJAX | Requisições HTTP assíncronas sem recarregar a página (usado no carregamento de municípios) |
| Prepared Statement | Query SQL pré-compilada com parâmetros separados, protege contra SQL Injection |

---

*Granfino · Sistema de Gestão de Qualidade & SAC · v1.0.0 · Março 2026*  
*Desenvolvido por Infini Solutions · granfino.infinisolutions.cloud*
