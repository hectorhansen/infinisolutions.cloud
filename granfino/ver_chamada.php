<?php
// ============================================
// GRANFINO - Ver Chamada
// ver_chamada.php
// ============================================
require_once 'config.php';
auth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: chamadas.php'); exit; }

$stmt = db()->prepare("
    SELECT c.*, a.nome AS atendente_nome
    FROM chamadas c
    LEFT JOIN atendentes a ON a.id = c.atendente_id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) { header('Location: chamadas.php'); exit; }

$stmt2 = db()->prepare("SELECT * FROM chamada_produtos WHERE chamada_id = ?");
$stmt2->execute([$id]);
$produtos = $stmt2->fetchAll();

// Atualizar status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_status'])) {
    $statuses = ['aberta','em_andamento','fechada'];
    if (in_array($_POST['novo_status'], $statuses)) {
        db()->prepare("UPDATE chamadas SET status=? WHERE id=?")->execute([$_POST['novo_status'], $id]);
        header("Location: ver_chamada.php?id=$id&msg=ok");
        exit;
    }
}

$pagina_atual  = 'chamadas';
$titulo_pagina = "Chamada #$id";
require '_header.php';
?>

<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;">
  <div>
    <h2>Chamada <span class="num-chamada">#<?= $c['id'] ?></span></h2>
    <p>Registrada em <?= date('d/m/Y \à\s H:i', strtotime($c['criado_em'])) ?>
       por <?= htmlspecialchars($c['atendente_nome'] ?: 'sistema') ?></p>
  </div>
  <a href="chamadas.php" class="btn btn-outline">← Voltar</a>
</div>

<?php if (isset($_GET['msg'])): ?>
<div class="alert alert-success">Status atualizado com sucesso.</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 320px;gap:1.25rem;">

  <!-- COLUNA PRINCIPAL -->
  <div style="display:flex;flex-direction:column;gap:1.25rem;">

    <!-- Perfil consumidor -->
    <div class="card">
      <div class="card-header"><h3>👤 Perfil do consumidor</h3></div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
          <?php
          $campos = [
            'Nome'              => $c['nome_consumidor'],
            'Telefone'          => $c['telefone'],
            'Endereço'          => $c['endereco'],
            'Bairro'            => $c['bairro'],
            'Município/UF'      => trim($c['municipio'].'/'.$c['estado'], '/'),
            'Ponto de referência' => $c['ponto_referencia'],
          ];
          foreach ($campos as $label => $val): ?>
          <div>
            <div style="font-size:.72rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--mid);"><?= $label ?></div>
            <div style="margin-top:.2rem;"><?= htmlspecialchars($val ?: '—') ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Produtos -->
    <?php if ($produtos): ?>
    <div class="card">
      <div class="card-header"><h3>📦 Produtos</h3></div>
      <div class="card-body">
        <div class="produtos-grid">
          <?php foreach ($produtos as $i => $p): ?>
          <div class="produto-bloco">
            <div class="prod-title">Produto <?= $i+1 ?></div>
            <?php
            $pcampos = [
              'Produto'       => $p['produto'],
              'Quantidade'    => $p['quantidade'],
              'Lote'          => $p['lote'],
              'Fabricação'    => $p['fabricacao'] ? date('d/m/Y', strtotime($p['fabricacao'])) : '',
              'Validade'      => $p['validade']    ? date('d/m/Y', strtotime($p['validade']))    : '',
              'Local de compra' => $p['local_compra'],
            ];
            foreach ($pcampos as $lbl => $val): ?>
            <div style="margin-bottom:.5rem;">
              <div style="font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--mid);"><?= $lbl ?></div>
              <div style="font-size:.9rem;"><?= htmlspecialchars($val ?: '—') ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Descrição -->
    <div class="card">
      <div class="card-header"><h3>📝 Descrição da chamada</h3></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:1rem;">
        <div>
          <div style="font-size:.72rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--mid);margin-bottom:.3rem;">Motivo</div>
          <div><?= htmlspecialchars($c['motivo'] ?: '—') ?></div>
        </div>
        <div>
          <div style="font-size:.72rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--mid);margin-bottom:.3rem;">Descrição geral</div>
          <div style="white-space:pre-wrap;"><?= htmlspecialchars($c['descricao_geral'] ?: '—') ?></div>
        </div>
        <div>
          <div style="font-size:.72rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--mid);margin-bottom:.3rem;">Observações gerais</div>
          <div style="white-space:pre-wrap;"><?= htmlspecialchars($c['observacoes_gerais'] ?: '—') ?></div>
        </div>
        <div>
          <div style="font-size:.72rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--mid);margin-bottom:.3rem;">Horário preferencial</div>
          <div><?= htmlspecialchars($c['horario_preferencial'] ?: '—') ?></div>
        </div>
      </div>
    </div>

  </div>

  <!-- COLUNA LATERAL -->
  <div style="display:flex;flex-direction:column;gap:1.25rem;">

    <!-- Status -->
    <div class="card">
      <div class="card-header"><h3>Status</h3></div>
      <div class="card-body">
        <span class="badge badge-<?= $c['status'] ?>" style="font-size:.9rem;padding:.3rem .9rem;">
          <?= ucfirst(str_replace('_',' ',$c['status'])) ?>
        </span>
        <form method="POST" style="margin-top:1rem;">
          <div class="form-group">
            <label>Alterar status</label>
            <select name="novo_status">
              <option value="aberta"       <?= $c['status']==='aberta'       ?'selected':'' ?>>Aberta</option>
              <option value="em_andamento" <?= $c['status']==='em_andamento' ?'selected':'' ?>>Em andamento</option>
              <option value="fechada"      <?= $c['status']==='fechada'      ?'selected':'' ?>>Fechada</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary btn-sm" style="margin-top:.5rem;width:100%;justify-content:center;">
            Salvar status
          </button>
        </form>
      </div>
    </div>

    <!-- Info rápida -->
    <div class="card">
      <div class="card-body" style="display:flex;flex-direction:column;gap:.75rem;">
        <div>
          <div style="font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--mid);">Registrado em</div>
          <div><?= date('d/m/Y H:i', strtotime($c['criado_em'])) ?></div>
        </div>
        <div>
          <div style="font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--mid);">Última atualização</div>
          <div><?= date('d/m/Y H:i', strtotime($c['atualizado_em'])) ?></div>
        </div>
        <div>
          <div style="font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--mid);">Atendente</div>
          <div><?= htmlspecialchars($c['atendente_nome'] ?: '—') ?></div>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require '_footer.php'; ?>
