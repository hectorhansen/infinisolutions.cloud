<?php
// ============================================
// GRANFINO - Listagem de Chamadas
// chamadas.php
// ============================================
require_once 'config.php';
auth();

// Fechar chamada via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fechar_id'])) {
    $stmt = db()->prepare("UPDATE chamadas SET status='fechada' WHERE id=?");
    $stmt->execute([(int)$_POST['fechar_id']]);
    header('Location: chamadas.php?msg=fechada');
    exit;
}

// Parâmetros de busca
$busca   = trim($_GET['busca'] ?? '');
$status  = $_GET['status'] ?? '';
$periodo = $_GET['periodo'] ?? '';
$pagina  = max(1, (int)($_GET['p'] ?? 1));
$por_pag = 20;
$offset  = ($pagina - 1) * $por_pag;

// Query
$where = ['1=1'];
$params = [];

if ($busca) {
    $where[] = "(c.nome_consumidor LIKE ? OR c.telefone LIKE ? OR c.id = ? OR c.motivo LIKE ?)";
    $like = "%$busca%";
    $params[] = $like; $params[] = $like;
    $params[] = is_numeric($busca) ? (int)$busca : 0;
    $params[] = $like;
}
if ($status) {
    $where[] = "c.status = ?";
    $params[] = $status;
}
if ($periodo === 'hoje') {
    $where[] = "DATE(c.criado_em) = CURDATE()";
} elseif ($periodo === 'semana') {
    $where[] = "c.criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($periodo === 'mes') {
    $where[] = "c.criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$whereStr = implode(' AND ', $where);

$total = db()->prepare("SELECT COUNT(*) FROM chamadas c WHERE $whereStr");
$total->execute($params);
$total = (int)$total->fetchColumn();
$paginas = ceil($total / $por_pag);

$stmt = db()->prepare("
    SELECT c.id, c.nome_consumidor, c.telefone, c.municipio, c.estado,
           c.motivo, c.status, c.criado_em,
           a.nome AS atendente
    FROM chamadas c
    LEFT JOIN atendentes a ON a.id = c.atendente_id
    WHERE $whereStr
    ORDER BY c.criado_em DESC
    LIMIT $por_pag OFFSET $offset
");
$stmt->execute($params);
$chamadas = $stmt->fetchAll();

$pagina_atual  = 'chamadas';
$titulo_pagina = 'Chamadas';
require '_header.php';
?>

<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;">
  <div>
    <h2>Todas as chamadas</h2>
    <p><?= number_format($total, 0, ',', '.') ?> registro(s) encontrado(s)</p>
  </div>
  <a href="index.php" class="btn btn-primary">➕ Nova chamada</a>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'fechada'): ?>
<div class="alert alert-success">Chamada fechada com sucesso.</div>
<?php endif; ?>

<!-- FILTROS -->
<div class="card" style="margin-bottom:1.25rem;">
  <div class="card-body" style="padding:1rem 1.5rem;">
    <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
      <div class="form-group" style="flex:1;min-width:200px;">
        <label>Buscar</label>
        <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>"
               placeholder="Nome, telefone, nº chamada, motivo...">
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status">
          <option value="">Todos</option>
          <option value="aberta"       <?= $status==='aberta'       ?'selected':'' ?>>Aberta</option>
          <option value="em_andamento" <?= $status==='em_andamento' ?'selected':'' ?>>Em andamento</option>
          <option value="fechada"      <?= $status==='fechada'      ?'selected':'' ?>>Fechada</option>
        </select>
      </div>
      <div class="form-group">
        <label>Período</label>
        <select name="periodo">
          <option value="">Todos</option>
          <option value="hoje"  <?= $periodo==='hoje' ?'selected':'' ?>>Hoje</option>
          <option value="semana"<?= $periodo==='semana'?'selected':'' ?>>Últimos 7 dias</option>
          <option value="mes"   <?= $periodo==='mes'  ?'selected':'' ?>>Últimos 30 dias</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end;">🔍 Filtrar</button>
      <a href="chamadas.php" class="btn btn-ghost btn-sm" style="align-self:flex-end;">Limpar</a>
    </form>
  </div>
</div>

<!-- TABELA -->
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Data</th>
          <th>Consumidor</th>
          <th>Telefone</th>
          <th>Cidade/UF</th>
          <th>Motivo</th>
          <th>Atendente</th>
          <th>Status</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($chamadas)): ?>
        <tr><td colspan="9" style="text-align:center;padding:2rem;color:var(--mid);">Nenhuma chamada encontrada.</td></tr>
        <?php endif; ?>
        <?php foreach ($chamadas as $c): ?>
        <tr>
          <td><span class="num-chamada"><?= $c['id'] ?></span></td>
          <td style="white-space:nowrap;font-size:.82rem;color:var(--mid);">
            <?= date('d/m/Y', strtotime($c['criado_em'])) ?><br>
            <?= date('H:i', strtotime($c['criado_em'])) ?>
          </td>
          <td><?= htmlspecialchars($c['nome_consumidor'] ?: '—') ?></td>
          <td style="font-variant-numeric:tabular-nums;"><?= htmlspecialchars($c['telefone'] ?: '—') ?></td>
          <td><?= htmlspecialchars($c['municipio'] ? $c['municipio'].'/'.$c['estado'] : '—') ?></td>
          <td><?= htmlspecialchars($c['motivo'] ?: '—') ?></td>
          <td style="font-size:.82rem;"><?= htmlspecialchars($c['atendente'] ?: '—') ?></td>
          <td><span class="badge badge-<?= $c['status'] ?>"><?= ucfirst(str_replace('_',' ',$c['status'])) ?></span></td>
          <td>
            <a href="ver_chamada.php?id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm">Ver</a>
            <?php if ($c['status'] !== 'fechada'): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Fechar chamada #<?= $c['id'] ?>?')">
              <input type="hidden" name="fechar_id" value="<?= $c['id'] ?>">
              <button type="submit" class="btn btn-outline btn-sm">✓ Fechar</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- PAGINAÇÃO -->
  <?php if ($paginas > 1): ?>
  <div style="padding:1rem 1.5rem;display:flex;gap:.5rem;align-items:center;border-top:1px solid var(--border);">
    <?php
    $q = http_build_query(array_filter(['busca'=>$busca,'status'=>$status,'periodo'=>$periodo]));
    for ($i = 1; $i <= $paginas; $i++):
    ?>
    <a href="?<?= $q ?>&p=<?= $i ?>"
       class="btn btn-sm <?= $i === $pagina ? 'btn-primary' : 'btn-outline' ?>"
       style="min-width:36px;justify-content:center;"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php require '_footer.php'; ?>
