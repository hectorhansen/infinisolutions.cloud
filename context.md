# WhatsApp Business Manager - Context & Documentation

Este documento serve como o Ponto de Verdade Central do projeto, descrevendo a arquitetura, tecnologias e histórico de desenvolvimento da plataforma de gestão de atendimento via WhatsApp API Oficial.

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
- `/public/` - Todo o HTML, JS e CSS do Client-side. Esta deve ser a raiz docroot exposta ao público para maior segurança.
- `/uploads/` - Onde mídias recebidas do WhatsApp (Imagens, Áudios) serão armazenadas.
- `/` (Raiz) - Scripts autônomos como o Webhook receptor (`webhook.php`), o Worker assíncrono (`cron.php`), a inicialização (`seed.php`) e os arquivos de conexão base (`config.php`, `db.php`).

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

## 6. Changelog & Histórico de Fases

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
