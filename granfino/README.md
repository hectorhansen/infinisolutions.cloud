# GRANFINO · Sistema de Gestão de Qualidade (SAC)
Sistema web em PHP + MySQL para registro e acompanhamento de chamadas SAC.

---

## 📁 Estrutura de arquivos

```
granfino/
├── config.php          ← Configuração DB + funções base
├── schema.sql          ← Criação das tabelas MySQL
├── login.php           ← Autenticação de atendentes
├── logout.php          ← Encerrar sessão
├── index.php           ← Nova chamada (formulário principal)
├── chamadas.php        ← Listagem + busca de chamadas
├── ver_chamada.php     ← Detalhes de uma chamada
├── relatorios.php      ← Relatórios semanal/mensal
├── _header.php         ← Layout base (topbar + sidebar + CSS)
└── _footer.php         ← Fechamento do layout
```

---

## ⚙️ Configuração inicial

### 1. Banco de dados
Execute o arquivo `schema.sql` no MySQL da Hostinger (via phpMyAdmin ou SSH):
```sql
source schema.sql;
```
Isso cria o banco `granfino`, as tabelas e um usuário admin padrão.

### 2. Credenciais do banco
Edite o arquivo `config.php` e altere as linhas:
```php
define('DB_USER', 'seu_usuario');   // usuário MySQL da Hostinger
define('DB_PASS', 'sua_senha');     // senha MySQL da Hostinger
```

### 3. Upload para o subdomínio
No painel Hostinger, crie o subdomínio `granfino.infinisolutions.cloud`
apontando para uma pasta (ex: `public_html/granfino/`).

Faça o upload de todos os arquivos `.php` para essa pasta via FTP ou Gerenciador de Arquivos.

---

## 🔐 Acesso padrão

| Campo | Valor                   |
|-------|-------------------------|
| Email | admin@granfino.com.br   |
| Senha | password                |

> **Importante**: Troque a senha após o primeiro acesso.
> Para gerar hash: `echo password_hash('nova_senha', PASSWORD_DEFAULT);`
> e atualize diretamente no banco na tabela `atendentes`.

---

## 🗂️ Funcionalidades

| Módulo | Descrição |
|--------|-----------|
| Login | Autenticação com sessão + timeout de 1h |
| Nova chamada | Formulário completo: consumidor, 4 produtos, motivo, descrição |
| Listagem | Busca por nome/telefone/nº/motivo + filtros status e período |
| Ver chamada | Detalhes completos + alterar status |
| Relatórios | Semanal/mensal: KPIs, gráfico por dia, por motivo, por produto, top cidades |

---

## 🔧 Próximos passos sugeridos

- [ ] Exportar relatório para PDF
- [ ] Cadastro de atendentes (CRUD)
- [ ] E-mail de notificação ao registrar chamada
- [ ] Campo de retorno ao consumidor
- [ ] Upload de foto do produto com problema
