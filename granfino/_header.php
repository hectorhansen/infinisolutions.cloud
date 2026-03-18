<?php
// ============================================
// GRANFINO - Header / Layout base
// _header.php  (include em todas as páginas)
// ============================================
// Espera que $pagina_atual esteja definida antes do include
// Ex: $pagina_atual = 'nova_chamada';
$pagina_atual = $pagina_atual ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($titulo_pagina ?? 'Sistema') ?> · <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">
<style>
/* =========================================
   BASE RESET & VARIABLES
   ========================================= */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --red:      #D4001A;
  --red-dk:   #A80015;
  --red-lt:   #fff0f2;
  --cream:    #FAF7F2;
  --warm:     #F0EAE0;
  --charcoal: #1A1A1A;
  --mid:      #6B6560;
  --lite:     #9E9690;
  --border:   #DDD5C8;
  --white:    #FFFFFF;

  --sidebar-w: 220px;
  --topbar-h:  60px;

  --radius-sm: 6px;
  --radius-md: 10px;
  --radius-lg: 16px;

  --shadow-sm: 0 1px 4px rgba(0,0,0,.06);
  --shadow-md: 0 4px 20px rgba(0,0,0,.08);
}

body {
  font-family: 'DM Sans', sans-serif;
  background: var(--cream);
  color: var(--charcoal);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

/* =========================================
   TOPBAR
   ========================================= */
.topbar {
  position: fixed;
  top: 0; left: 0; right: 0;
  height: var(--topbar-h);
  background: #fff;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 1.5rem;
  z-index: 100;
  box-shadow: var(--shadow-sm);
}

.topbar-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
}

.topbar-brand .dot {
  width: 32px; height: 32px;
  background: var(--red);
  border-radius: 7px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}

.topbar-brand .dot span {
  font-family: 'Syne', sans-serif;
  font-weight: 800;
  font-size: 16px;
  color: #fff;
}

.topbar-brand h1 {
  font-family: 'Syne', sans-serif;
  font-weight: 800;
  font-size: 1.1rem;
  color: var(--charcoal);
  letter-spacing: -0.3px;
}

.topbar-brand .sub {
  font-size: .7rem;
  color: var(--mid);
  letter-spacing: .04em;
  text-transform: uppercase;
  margin-left: 2px;
  display: block;
  line-height: 1;
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.topbar-right .atendente {
  font-size: .85rem;
  color: var(--mid);
}

.topbar-right .atendente strong {
  color: var(--charcoal);
  font-weight: 500;
}

.topbar-right a.logout {
  font-size: .8rem;
  color: var(--red);
  text-decoration: none;
  font-weight: 500;
  padding: .3rem .7rem;
  border: 1px solid rgba(212,0,26,.25);
  border-radius: var(--radius-sm);
  transition: background .2s;
}

.topbar-right a.logout:hover { background: var(--red-lt); }

.topbar-date {
  font-size: .8rem;
  color: var(--mid);
  background: var(--warm);
  padding: .3rem .7rem;
  border-radius: var(--radius-sm);
  font-variant-numeric: tabular-nums;
}

/* =========================================
   SIDEBAR
   ========================================= */
.sidebar {
  position: fixed;
  top: var(--topbar-h);
  left: 0;
  width: var(--sidebar-w);
  bottom: 0;
  background: #fff;
  border-right: 1px solid var(--border);
  padding: 1.5rem 0;
  overflow-y: auto;
  z-index: 90;
}

.sidebar-section {
  padding: 0 1rem .3rem 1.25rem;
  font-size: .68rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--lite);
  margin-top: 1rem;
}

.sidebar a {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: .55rem 1.25rem;
  font-size: .88rem;
  color: var(--mid);
  text-decoration: none;
  border-left: 3px solid transparent;
  transition: all .15s;
}

.sidebar a:hover {
  background: var(--warm);
  color: var(--charcoal);
}

.sidebar a.active {
  background: var(--red-lt);
  color: var(--red);
  border-left-color: var(--red);
  font-weight: 500;
}

.sidebar a .ico { font-size: 1rem; width: 18px; text-align: center; }

/* =========================================
   MAIN CONTENT
   ========================================= */
.main {
  margin-top: var(--topbar-h);
  margin-left: var(--sidebar-w);
  padding: 2rem;
  flex: 1;
}

/* =========================================
   PAGE HEADER
   ========================================= */
.page-header {
  margin-bottom: 1.75rem;
}

.page-header h2 {
  font-family: 'Syne', sans-serif;
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--charcoal);
  letter-spacing: -0.3px;
}

.page-header p {
  font-size: .88rem;
  color: var(--mid);
  margin-top: .2rem;
}

/* =========================================
   CARDS
   ========================================= */
.card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
}

.card-header {
  padding: 1.1rem 1.5rem;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.card-header h3 {
  font-family: 'Syne', sans-serif;
  font-size: .95rem;
  font-weight: 700;
  color: var(--charcoal);
  letter-spacing: -.2px;
}

.card-body { padding: 1.5rem; }

/* =========================================
   FORM ELEMENTS
   ========================================= */
.form-grid {
  display: grid;
  gap: 1rem;
}

.form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 1rem;
}

.form-group { display: flex; flex-direction: column; gap: .35rem; }

.form-group label {
  font-size: .75rem;
  font-weight: 500;
  letter-spacing: .05em;
  text-transform: uppercase;
  color: var(--mid);
}

.form-group input,
.form-group select,
.form-group textarea {
  padding: .6rem .85rem;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  font-family: 'DM Sans', sans-serif;
  font-size: .9rem;
  color: var(--charcoal);
  background: var(--cream);
  transition: border-color .15s, box-shadow .15s;
  outline: none;
  width: 100%;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  border-color: var(--red);
  box-shadow: 0 0 0 3px rgba(212,0,26,.09);
  background: #fff;
}

.form-group textarea { resize: vertical; min-height: 80px; }

/* =========================================
   PRODUTO BLOCK
   ========================================= */
.produtos-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
}

.produto-bloco {
  border: 1.5px solid var(--border);
  border-radius: var(--radius-md);
  padding: 1rem;
  background: var(--cream);
}

.produto-bloco .prod-title {
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--lite);
  margin-bottom: .75rem;
}

.produto-bloco .form-group { margin-bottom: .6rem; }
.produto-bloco .form-group:last-child { margin-bottom: 0; }

/* =========================================
   BUTTONS
   ========================================= */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: .6rem 1.25rem;
  border-radius: var(--radius-sm);
  font-family: 'Syne', sans-serif;
  font-size: .88rem;
  font-weight: 700;
  cursor: pointer;
  text-decoration: none;
  border: none;
  transition: all .15s;
}

.btn-primary  { background: var(--red); color: #fff; }
.btn-primary:hover  { background: var(--red-dk); }

.btn-outline  { background: transparent; color: var(--charcoal); border: 1.5px solid var(--border); }
.btn-outline:hover  { background: var(--warm); }

.btn-ghost    { background: transparent; color: var(--mid); }
.btn-ghost:hover    { background: var(--warm); color: var(--charcoal); }

.btn-sm { padding: .4rem .8rem; font-size: .8rem; }

/* =========================================
   BADGE / STATUS
   ========================================= */
.badge {
  display: inline-block;
  padding: .2rem .6rem;
  border-radius: 99px;
  font-size: .75rem;
  font-weight: 500;
}

.badge-aberta       { background: #fef9c3; color: #854d0e; }
.badge-em_andamento { background: #dbeafe; color: #1e40af; }
.badge-fechada      { background: #dcfce7; color: #166534; }

/* =========================================
   TABLE
   ========================================= */
.table-wrap { overflow-x: auto; }

table {
  width: 100%;
  border-collapse: collapse;
  font-size: .88rem;
}

thead th {
  text-align: left;
  padding: .7rem 1rem;
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .07em;
  text-transform: uppercase;
  color: var(--mid);
  border-bottom: 1.5px solid var(--border);
  background: var(--warm);
}

tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .12s;
}

tbody tr:hover { background: var(--warm); }

tbody td {
  padding: .75rem 1rem;
  color: var(--charcoal);
  vertical-align: middle;
}

/* =========================================
   ALERTS
   ========================================= */
.alert {
  padding: .75rem 1rem;
  border-radius: var(--radius-sm);
  font-size: .88rem;
  margin-bottom: 1rem;
}
.alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.alert-error   { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

/* =========================================
   NUMERO CHAMADA badge
   ========================================= */
.num-chamada {
  display: inline-block;
  background: var(--red);
  color: #fff;
  font-family: 'Syne', sans-serif;
  font-weight: 800;
  font-size: .9rem;
  padding: .25rem .7rem;
  border-radius: 6px;
  letter-spacing: .02em;
}

/* =========================================
   RESPONSIVE
   ========================================= */
@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); }
  .main { margin-left: 0; padding: 1rem; }
}
</style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
  <a href="index.php" class="topbar-brand">
    <img src="granfino_logo.png" alt="Granfino" style="height:42px;">
    <div>
      <span class="sub" style="font-size:.7rem;color:var(--mid);letter-spacing:.04em;text-transform:uppercase;">Gestão de Qualidade</span>
    </div>
  </a>
  <div class="topbar-right">
    <span class="topbar-date"><?= date('d/m/Y') ?> · <?= date('H:i') ?></span>
    <span class="atendente">Olá, <strong><?= htmlspecialchars($_SESSION['atendente_nome']) ?></strong></span>
    <a href="logout.php" class="logout">Sair</a>
  </div>
</div>

<!-- SIDEBAR -->
<nav class="sidebar">
  <div class="sidebar-section">Chamadas</div>
  <a href="index.php"    class="<?= $pagina_atual === 'nova_chamada' ? 'active' : '' ?>">
    <span class="ico">➕</span> Nova chamada
  </a>
  <a href="chamadas.php" class="<?= $pagina_atual === 'chamadas' ? 'active' : '' ?>">
    <span class="ico">📋</span> Todas as chamadas
  </a>

  <div class="sidebar-section">Relatórios</div>
  <a href="relatorios.php?tipo=semanal" class="<?= ($pagina_atual === 'relatorio_semanal') ? 'active' : '' ?>">
    <span class="ico">📊</span> Relatório semanal
  </a>
  <a href="relatorios.php?tipo=mensal" class="<?= ($pagina_atual === 'relatorio_mensal') ? 'active' : '' ?>">
    <span class="ico">📅</span> Relatório mensal
  </a>
</nav>

<!-- MAIN (conteúdo inicia após este include) -->
<main class="main">
