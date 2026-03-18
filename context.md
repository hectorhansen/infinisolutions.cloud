# Infini Solutions - Portal Intranet Multi-App (Context & Docs)

Este documento serve como o Ponto de Verdade Central do projeto, descrevendo a arquitetura, tecnologias e histórico de desenvolvimento da Intranet da Infini Solutions, que atua como hub centralizador de múltiplas aplicações corporativas (NucleoFix e Granfino).

## 1. Visão Geral do Sistema
O sistema é um hub centralizador de mensagens do WhatsApp Cloud API. Ele permite que múltiplos operadores (atendentes) façam login via interface web e respondam a contatos em tempo real. A plataforma recebe nativamente os webhooks da Meta, salva no banco e direciona as conversas para operadores disponíveis.

## 2. Tecnologias Utilizadas (Stack)
A solução foi pivotada de uma stack inicial (Node.js/NestJS/Rediss) para ser 100% amigável a hospedagens compartilhadas convencionais (cPanel/Hostinger) sem custos adicionais de infraestrutura em nuvem.

### Backend (API & Jobs)
- **PHP 8 (Puro)**: Sem frameworks, garantindo máxima performance, controle e facilidade de implantação.
- **MySQL (via PDO)**: Usado para persistência de dados geral (usuários, mensagens) e também como substituto do Redis (Filas e Cache de Polling).
- **cURL e ext-json**: Para comunicação síncrona/assíncrona com a Graph API da Meta.

### Frontend
- **HTML5 e Vanilla JS**: Aplicação Single-Page (SPA) simples, sem necessidade de Node.js/Vite no servidor.
- **Tailwind CSS**: Carregado dinamicamente via CDN para estilização em tempo real.
- **FontAwesome 6**: Para ícones modernos e leves (UI).

## 3. Arquitetura e Estrutura de Diretórios
O repositório é segmentado em quatro frentes principais:

- `/api/` - Controladores das requisições via JavaScript (Endpoints REST). Contém o roteador principal `index.php`.
- `/lib/` - Classes focadas em Regra de Negócios pura (Auth, Assign, Queue, WhatsApp, Hmac).
- `/nucleofix/` - Frontend (HTML, JS, CSS) do aplicativo original de gestão do WhatsApp (antigo public/).
- `/granfino/` - Frontend reservado para a futura aplicação "Granfino".
- `/infinicloud/` - Aplicação SPA do InfiniCloud (Compartilhamento de Arquivos) com API independente.
- `/uploads/` - Onde mídias recebidas do WhatsApp (Imagens, Áudios) serão armazenadas.
- `/` (Raiz) - Receptáculo da docroot. Contém o `index.html` estilo Netflix da Intranet, scripts autônomos como o Webhook receptor (`webhook.php`), o Worker assíncrono (`cron.php`), a inicialização (`seed.php`) e os arquivos de conexão base (`config.php`, `db.php`).

## 4. O Fluxo de Eventos (Event Loop)
As hospedagens compartilhadas limitam processos contínuos (Daemons). Foi desenhada uma arquitetura engenhosa:
1. **Webhook Receptivo (`webhook.php`)**: Apenas processa o pacote da Meta, verifica assinatura HMAC e salva rapidamente na `webhook_events`. Retorna 200 OK em milissegundos.
2. **Cron Job (`cron.php`)**: Um arquivo rodando a cada minuto no servidor ler as tabelas de fila, atualiza conversas, insere a base em `contacts/messages` e envia as mensagens pendentes (via cURL) para o WhatsApp.
3. **Simulador de Websocket (`Polling`)**: O Painel JavaScript pergunta de 3 em 3 segundos se há alguma novidade (`/api/events.php`). Se a tabela `polling_cache` tiver `unseen=0`, ele envia o payload pro JS que renderiza a mensagem imediatamente na tela.

## 5. Observações & Known Issues (Limitações Aceitas)
- Como usamos Polling, pode haver até 3 segundos de "delay" entre a mensagem bater no banco e aparecer na tela, mas é imperceptível na prática humana.
- O Cron é o disparador, ou seja, as mensagens agendadas e recebimentos demoram aproximadamente ~1 a 2 minutos para fazer o ciclo completo no melhor caso (conforme configuração na hospedagem). O ideal é cronjob de `* * * * *`.
- Para o Upload e tratamento de Mídias nativo recebidas e enviadas seria aconselhado habilitar o modo debug ou checar os mime_types no futuro. A expiração padrão de URL das mídias pela Meta é de 5 dias.
- O `.htaccess` e o `index.php` na raiz protegem leitura bruta de arquivos importantes.

---

## 6. Módulo: Granfino (Gestão de Qualidade & SAC)
O **Granfino** é o segundo sistema integrado à Intranet, construído para substituir um sistema legado em Microsoft Access.

### Objetivo e Escopo
Centralizar o registro, acompanhamento e análise de chamadas do SAC, suportando importação de mais de 31 mil registros históricos do Access. Permite múltiplos atendentes, extração de relatórios automatizados de KPIs e maior segurança contra perda de dados.

### Stack e Arquitetura do Módulo
- **Backend:** Monólito em PHP 8.x puro, com PDO para prevenção de SQL Injection. Autenticação baseada em Sessions nativas (`auth()`).
- **Frontend:** Server-side renderizado com HTML5/CSS3 nativos e Javascript puro para endpoints AJAX locais (ex: busca dinâmica de municípios).
- **Banco de Dados Independente:** Roda num schema isolado (`u752688765_granfino`), composto essencialmente pelas tabelas `atendentes`, `chamadas` e `chamada_produtos`.
- **Hospedagem:** Reside na sub-pasta `/granfino/` e é servido nativamente pelo subdomínio `granfino.infinisolutions.cloud`.

---

## 7. Módulo: InfiniCloud (Compartilhamento de Arquivos)
O **InfiniCloud** é o terceiro sistema integrado à Intranet, construído para fornecer um hub corporativo de compartilhamento de arquivos sem limites rígidos de tamanho de upload, seguro e expirável.

### Objetivo e Escopo
Permitir que usuários autenticados façam upload de arquivos corporativos (PDF, ZIP, imagens, docs) e gerem um link público (`share.infinisolutions.cloud/{hash}`) com data de expiração. Após o prazo, um job automático exclui os arquivos do servidor físico para economizar espaço e encerra o link.

### Stack e Arquitetura do Módulo
- **Backend:** API REST em PHP 8 puro localizada em `/infinicloud/api/`. Implementa validação real de MIME types via `finfo` nativo, streaming em chunks com `readfile` para download limpo e hash SHA1 irrevogável gerado no banco. O painel é protegido por sessões e cookies padrão.
- **Frontend:** Single-Page Application (SPA) assíncrona com vanilla JS em `/infinicloud/app.js`, consumindo a própria API. Interface premium glassmorphism, contando com área de drop de upload e progress bar usando XMLHttpRequest. Uma página secundária estática em PHP (`share.php`) lida publicamente com visitantes realizando requests GET.
- **Banco de Dados Independente:** Roda num schema isolado (`u752688765_infinicloud`), abrigando `ic_users`, `ic_files` e `ic_share_links`. Soft-deletes mantêm a integridade visual, removendo apenas do storage físico.
- **Hospedagem:** Subordinada à pasta `/infinicloud/` servida nativamente pelo subdomínio `share.infinisolutions.cloud`. Um `.user.ini` local estende os limitadores nativos do Apache/PHP para uploads em 512MB e `max_execution_time` elástico.

---

## 8. Changelog & Histórico de Fases

### [v1.0.0] - Março de 2026

#### Fase 1: Webhook Gateway e Estrutura Backend
- [x] Pivotamento completo do framework original (NestJS) para PHP Puro.
- [x] Criação do schema MySQL definitivo `database.sql` simulando as constraints Prisma.
- [x] Criação de chaves Globais (`config.php`) e Singleton de BD (`db.php`).
- [x] Setup do Webhook de entrada Seguro (`lib/Hmac.php` e `webhook.php`).
- [x] Proteção anti Directory Indexing com `.htaccess`.

#### Fase 2: Autenticação e Endpoints Rest
- [x] Implementação JSON API (`helpers.php` e Routing Switch).
- [x] Construção da Lógica de Autenticação stateful/token (`lib/Auth.php` e `/api/auth.php`).
- [x] Criação do CRUD de Operadores (`/api/operators.php`).
- [x] Criação do Buscador e Inbox do chat (`/api/conversations.php` e `/api/messages.php`).
- [x] Endpoint central de Short-Polling Realtime (`/api/events.php`).

#### Fase 3: Dashboard Web Frontend
- [x] HTML de Login estilizado com Tailwind (`public/index.html`).
- [x] Interface Principal Clone WebChat responsiva, lista contatos à esquerda e chat central.
- [x] Desenvolvimento de Core JS `app.js` encapsulando Fetch com Bearer Tokens.
- [x] Motor "Optimistic UI" que projeta balõezinhos e relógios de checagem.
- [x] Integração da Tela central de Chat com Auto Resizer de campo textarea.

#### Fase 4: Cron Job Worker / Gerência da Fila
- [x] Desenho e programação do laço de leitura em Fila MySQL `lib/Queue.php`.
- [x] Script autônomo backend (`cron.php?job=queue`) atuando como substituto do BullMQ/Redis.
- [x] Parsing complexo dos Payloads da Meta integrados com atualização UI (Polling Push) e gravação de Contatos em Upsert no DB.

#### Fase 5 e 6: Métricas, Admin e Seeders.
- [x] Motor de algoritmo automático de Atribuição aleatória (`Assignment.php` e endpoint router).
- [x] Extratos numéricos consolidados via Endpoint de Supervisão `api/metrics.php`.
- [x] Utilidade global de Deploy Inicial `seed.php` para povoar as tabelas.

#### Fase 7: Portal Intranet e Extensibilidade Multi-App
- [x] Criação da interface central (estilo Netflix) servindo como Hub (`index.html` na raiz).
- [x] Atualização de design com branding da Infini Solutions e painel visual de aplicativos.
- [x] Fim da redundância de diretórios removendo a antiga pasta `/public/` e expondo o core na `public_html`.
- [x] Migração do painel de Whatsapp Cloud original para um subdomínio (pasta `/nucleofix/`), renomeado formalmente para NucleoFix.
- [x] Configuração de CORS Absoluto na API para acesso externo dos subdomínios.
- [x] Incorporação do sistema Granfino (Serviço de Atendimento ao Consumidor) no subdomínio próprio em pasta independente `/granfino/` usando a infraestrutura do mesmo servidor PHP Apache.

#### Fase 8: Módulo InfiniCloud (Compartilhamento de Arquivos Corporativo)
- [x] Construção da API PHP 8 Puro (`upload.php`, `files.php`, `links.php`, `download.php`) baseada em restrição por sessão.
- [x] Criação de Painel SPA Frontend (Dark mode glassmorphism) interconectado com a nova API para manipulação com Javascript puro e interatividade moderna (Progress bar/Toast).
- [x] Configuração da pipeline de banco de dados nativa em schema próprio (`u752688765_infinicloud`) com cascata nas Foreign Keys, e Soft-deletes controlados por trigger backend.
- [x] Módulo cron (`cron_cleanup.php`) protegido por chave para escaneamento e expurgo diário de mídias mortas, desocupando espaço real na VPS/Hostinger.
- [x] Ajustes locais nos limitadores de upload da porta Apache com `.user.ini` próprio submetendo uploads em 512MB de ram.
- [x] Adição do endpoint dinâmico `/share.php?hash=` (via `.htaccess`) processando links customizados.
- [x] Inclusão do cartão interativo na tela central `/index.html` redirecionando para `share.infinisolutions.cloud`.
