<?php
// ============================================
// GRANFINO - Relatórios
// relatorios.php
// ============================================
require_once 'config.php';
auth();

$tipo = $_GET['tipo'] ?? 'semanal'; // semanal | mensal

// Período
if ($tipo === 'mensal') {
    $mes  = (int)($_GET['mes']  ?? date('n'));
    $ano  = (int)($_GET['ano']  ?? date('Y'));
    $de   = sprintf('%04d-%02d-01', $ano, $mes);
    $ate  = date('Y-m-t', strtotime($de));
    $label_periodo = date('F/Y', strtotime($de));
} else {
    // Semana: segunda a domingo
    $semana = (int)($_GET['semana'] ?? 0);
    $seg    = strtotime("monday this week") + ($semana * 7 * 86400);
    $dom    = $seg + (6 * 86400);
    $de     = date('Y-m-d', $seg);
    $ate    = date('Y-m-d', $dom);
    $label_periodo = date('d/m', $seg) . ' a ' . date('d/m/Y', $dom);
}

$pdo = db();

// KPIs
$kpi = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status='aberta')       AS abertas,
        SUM(status='em_andamento') AS em_andamento,
        SUM(status='fechada')      AS fechadas
    FROM chamadas
    WHERE DATE(criado_em) BETWEEN ? AND ?
");
$kpi->execute([$de, $ate]);
$kpi = $kpi->fetch();

// Por motivo
$por_motivo = $pdo->prepare("
    SELECT motivo, COUNT(*) AS qtd
    FROM chamadas
    WHERE DATE(criado_em) BETWEEN ? AND ? AND motivo != ''
    GROUP BY motivo ORDER BY qtd DESC
");
$por_motivo->execute([$de, $ate]);
$por_motivo = $por_motivo->fetchAll();

// Por produto
$por_produto = $pdo->prepare("
    SELECT cp.produto, COUNT(*) AS qtd
    FROM chamada_produtos cp
    JOIN chamadas c ON c.id = cp.chamada_id
    WHERE DATE(c.criado_em) BETWEEN ? AND ? AND cp.produto != ''
    GROUP BY cp.produto ORDER BY qtd DESC
    LIMIT 10
");
$por_produto->execute([$de, $ate]);
$por_produto = $por_produto->fetchAll();

// Por dia (para gráfico)
$por_dia = $pdo->prepare("
    SELECT DATE(criado_em) AS dia, COUNT(*) AS qtd
    FROM chamadas
    WHERE DATE(criado_em) BETWEEN ? AND ?
    GROUP BY dia ORDER BY dia
");
$por_dia->execute([$de, $ate]);
$por_dia = $por_dia->fetchAll();

// Por município top 5
$por_cidade = $pdo->prepare("
    SELECT municipio, estado, COUNT(*) AS qtd
    FROM chamadas
    WHERE DATE(criado_em) BETWEEN ? AND ? AND municipio != ''
    GROUP BY municipio, estado ORDER BY qtd DESC LIMIT 5
");
$por_cidade->execute([$de, $ate]);
$por_cidade = $por_cidade->fetchAll();

$pagina_atual  = $tipo === 'mensal' ? 'relatorio_mensal' : 'relatorio_semanal';
$titulo_pagina = 'Relatório ' . ucfirst($tipo);
require '_header.php';
?>

<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
  <div>
    <h2>Relatório <?= ucfirst($tipo) ?></h2>
    <p>Período: <strong><?= $label_periodo ?></strong></p>
  </div>

  <!-- Navegação de período -->
  <div style="display:flex;gap:.5rem;align-items:center;">
    <?php if ($tipo === 'semanal'): ?>
    <a href="?tipo=semanal&semana=<?= $semana-1 ?>" class="btn btn-outline btn-sm">← Semana anterior</a>
    <?php if ($semana < 0): ?>
    <a href="?tipo=semanal&semana=<?= $semana+1 ?>" class="btn btn-outline btn-sm">Próxima →</a>
    <?php endif; ?>
    <?php else: ?>
    <?php
    $mes_ant = mktime(0,0,0,$mes-1,1,$ano);
    $mes_prx = mktime(0,0,0,$mes+1,1,$ano);
    ?>
    <a href="?tipo=mensal&mes=<?= date('n',$mes_ant) ?>&ano=<?= date('Y',$mes_ant) ?>" class="btn btn-outline btn-sm">← Mês anterior</a>
    <?php if (mktime(0,0,0,$mes,1,$ano) < mktime(0,0,0,date('n'),1,date('Y'))): ?>
    <a href="?tipo=mensal&mes=<?= date('n',$mes_prx) ?>&ano=<?= date('Y',$mes_prx) ?>" class="btn btn-outline btn-sm">Próximo →</a>
    <?php endif; ?>
    <?php endif; ?>

    <a href="?tipo=<?= $tipo === 'mensal' ? 'semanal' : 'mensal' ?>" class="btn btn-primary btn-sm">
      Mudar para <?= $tipo === 'mensal' ? 'semanal' : 'mensal' ?>
    </a>
  </div>
</div>

<!-- KPIs -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;">
  <?php
  $kpis = [
    ['Total',        $kpi['total'],        '#1A1A1A', '#FAF7F2'],
    ['Abertas',      $kpi['abertas'],      '#854d0e', '#fef9c3'],
    ['Em andamento', $kpi['em_andamento'], '#1e40af', '#dbeafe'],
    ['Fechadas',     $kpi['fechadas'],     '#166534', '#dcfce7'],
  ];
  foreach ($kpis as [$label, $val, $cor, $bg]): ?>
  <div class="card" style="background:<?= $bg ?>;border-color:transparent;">
    <div class="card-body" style="text-align:center;padding:1.25rem;">
      <div style="font-size:2rem;font-family:'Syne',sans-serif;font-weight:800;color:<?= $cor ?>;"><?= (int)$val ?></div>
      <div style="font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:<?= $cor ?>;opacity:.8;margin-top:.2rem;"><?= $label ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">

  <!-- Chamadas por dia (gráfico de barras simples) -->
  <div class="card">
    <div class="card-header"><h3>📊 Chamadas por dia</h3></div>
    <div class="card-body">
      <?php
      $max_dia = max(1, max(array_column($por_dia, 'qtd') ?: [1]));
      if (empty($por_dia)): ?>
        <p style="color:var(--mid);font-size:.88rem;">Sem dados no período.</p>
      <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:.5rem;">
        <?php foreach ($por_dia as $d): ?>
        <div style="display:flex;align-items:center;gap:.75rem;font-size:.82rem;">
          <div style="width:70px;color:var(--mid);flex-shrink:0;"><?= date('d/m', strtotime($d['dia'])) ?></div>
          <div style="flex:1;background:var(--warm);border-radius:4px;height:22px;overflow:hidden;">
            <div style="height:100%;background:var(--red);width:<?= round($d['qtd']/$max_dia*100) ?>%;border-radius:4px;"></div>
          </div>
          <div style="width:24px;text-align:right;font-weight:600;color:var(--charcoal);"><?= $d['qtd'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Por motivo -->
  <div class="card">
    <div class="card-header"><h3>📋 Por motivo</h3></div>
    <div class="card-body">
      <?php
      $max_mot = max(1, max(array_column($por_motivo, 'qtd') ?: [1]));
      if (empty($por_motivo)): ?>
        <p style="color:var(--mid);font-size:.88rem;">Sem dados no período.</p>
      <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:.5rem;">
        <?php foreach ($por_motivo as $m): ?>
        <div style="display:flex;align-items:center;gap:.75rem;font-size:.82rem;">
          <div style="width:160px;color:var(--mid);flex-shrink:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
               title="<?= htmlspecialchars($m['motivo']) ?>">
            <?= htmlspecialchars($m['motivo']) ?>
          </div>
          <div style="flex:1;background:var(--warm);border-radius:4px;height:22px;overflow:hidden;">
            <div style="height:100%;background:var(--red);width:<?= round($m['qtd']/$max_mot*100) ?>%;border-radius:4px;"></div>
          </div>
          <div style="width:24px;text-align:right;font-weight:600;color:var(--charcoal);"><?= $m['qtd'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Por produto -->
  <div class="card">
    <div class="card-header"><h3>📦 Produtos mais reclamados</h3></div>
    <div class="card-body">
      <?php
      $max_pr = max(1, max(array_column($por_produto, 'qtd') ?: [1]));
      if (empty($por_produto)): ?>
        <p style="color:var(--mid);font-size:.88rem;">Sem dados no período.</p>
      <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:.5rem;">
        <?php foreach ($por_produto as $p): ?>
        <div style="display:flex;align-items:center;gap:.75rem;font-size:.82rem;">
          <div style="width:160px;color:var(--mid);flex-shrink:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
               title="<?= htmlspecialchars($p['produto']) ?>">
            <?= htmlspecialchars($p['produto']) ?>
          </div>
          <div style="flex:1;background:var(--warm);border-radius:4px;height:22px;overflow:hidden;">
            <div style="height:100%;background:#1e40af;width:<?= round($p['qtd']/$max_pr*100) ?>%;border-radius:4px;"></div>
          </div>
          <div style="width:24px;text-align:right;font-weight:600;color:var(--charcoal);"><?= $p['qtd'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Por cidade -->
  <div class="card">
    <div class="card-header"><h3>📍 Top cidades</h3></div>
    <div class="card-body">
      <?php if (empty($por_cidade)): ?>
        <p style="color:var(--mid);font-size:.88rem;">Sem dados no período.</p>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>#</th><th>Cidade</th><th>UF</th><th>Chamadas</th></tr>
          </thead>
          <tbody>
            <?php foreach ($por_cidade as $i => $ci): ?>
            <tr>
              <td style="color:var(--mid);"><?= $i+1 ?></td>
              <td><?= htmlspecialchars($ci['municipio']) ?></td>
              <td><?= htmlspecialchars($ci['estado']) ?></td>
              <td><strong><?= $ci['qtd'] ?></strong></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<?php require '_footer.php'; ?>
