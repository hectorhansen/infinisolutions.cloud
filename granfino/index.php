<?php
// ============================================
// GRANFINO - Nova Chamada
// index.php
// ============================================
require_once 'config.php';
auth();

$sucesso = '';
$erro    = '';

// Lista de estados
$estados = ['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT',
            'PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'];

$motivos = [
    'Solicitação de troca',
    'Reclamação de qualidade',
    'Produto com defeito',
    'Produto vencido',
    'Embalagem danificada',
    'Corpo estranho',
    'Cor/odor/sabor alterado',
    'Informação sobre produto',
    'Elogio',
    'Outro',
];

$produtos_lista = [
    'Farinha Mandioca Torrada',
    'Farinha de Mandioca Crua',
    'Farinha de Trigo',
    'Farinha de Milho',
    'Fubá',
    'Amido de Milho',
    'Creme de Milho',
    'Polvilho Azedo',
    'Polvilho Doce',
    'Tapioca Granulada',
];

// Busca municípios via AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] === 'municipios') {
    header('Content-Type: application/json');
    $uf = strtoupper(preg_replace('/[^A-Z]/', '', $_GET['uf'] ?? ''));
    echo json_encode(municipiosPorEstado($uf));
    exit;
}

// Salvar chamada
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = db();

    try {
        $pdo->beginTransaction();

        // Próximo número de chamada
        $stmt = $pdo->query('SELECT COALESCE(MAX(id),0)+1 FROM chamadas');
        // usamos AUTO_INCREMENT, então só insert

        $stmt = $pdo->prepare("
            INSERT INTO chamadas
                (atendente_id, nome_consumidor, telefone, endereco, bairro, estado, municipio,
                 ponto_referencia, motivo, descricao_geral, observacoes_gerais, horario_preferencial)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $_SESSION['atendente_id'],
            trim($_POST['nome_consumidor'] ?? ''),
            trim($_POST['telefone'] ?? ''),
            trim($_POST['endereco'] ?? ''),
            trim($_POST['bairro'] ?? ''),
            trim($_POST['estado'] ?? ''),
            trim($_POST['municipio'] ?? ''),
            trim($_POST['ponto_referencia'] ?? ''),
            trim($_POST['motivo'] ?? ''),
            trim($_POST['descricao_geral'] ?? ''),
            trim($_POST['observacoes_gerais'] ?? ''),
            trim($_POST['horario_preferencial'] ?? ''),
        ]);
        $chamada_id = $pdo->lastInsertId();

        // Produtos (até 4)
        $stmtP = $pdo->prepare("
            INSERT INTO chamada_produtos (chamada_id, produto, quantidade, lote, fabricacao, validade, local_compra)
            VALUES (?,?,?,?,?,?,?)
        ");
        for ($i = 1; $i <= 4; $i++) {
            $prod = trim($_POST["produto_$i"] ?? '');
            if ($prod === '') continue;
            $fab = $_POST["fabricacao_$i"] ?? '';
            $val = $_POST["validade_$i"] ?? '';
            $stmtP->execute([
                $chamada_id,
                $prod,
                trim($_POST["quantidade_$i"] ?? ''),
                trim($_POST["lote_$i"] ?? ''),
                $fab ?: null,
                $val ?: null,
                trim($_POST["local_compra_$i"] ?? ''),
            ]);
        }

        $pdo->commit();
        $sucesso = "Chamada <strong>#$chamada_id</strong> registrada com sucesso!";

    } catch (Exception $e) {
        $pdo->rollBack();
        $erro = 'Erro ao salvar: ' . $e->getMessage();
    }
}

$pagina_atual  = 'nova_chamada';
$titulo_pagina = 'Nova Chamada';
require '_header.php';
?>

<div class="page-header">
  <h2>Nova Chamada</h2>
  <p>Registre uma nova ocorrência do SAC</p>
</div>

<?php if ($sucesso): ?>
<div class="alert alert-success"><?= $sucesso ?></div>
<?php endif; ?>
<?php if ($erro): ?>
<div class="alert alert-error"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<form method="POST">

  <!-- ── PERFIL DO CONSUMIDOR ── -->
  <div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header">
      <h3>👤 Perfil do consumidor</h3>
    </div>
    <div class="card-body">
      <div class="form-row" style="grid-template-columns:1fr auto;">
        <div class="form-group">
          <label>Nome</label>
          <input type="text" name="nome_consumidor" placeholder="Nome completo">
        </div>
        <div class="form-group">
          <label>Telefone</label>
          <input type="text" name="telefone" placeholder="(00) 00000-0000" style="width:180px;">
        </div>
      </div>

      <div class="form-row" style="margin-top:.75rem; grid-template-columns:1fr auto;">
        <div class="form-group">
          <label>Endereço</label>
          <input type="text" name="endereco" placeholder="Rua, número">
        </div>
        <div class="form-group">
          <label>Bairro</label>
          <input type="text" name="bairro" placeholder="Bairro" style="width:200px;">
        </div>
      </div>

      <div class="form-row" style="margin-top:.75rem; grid-template-columns:90px 1fr 1fr;">
        <div class="form-group">
          <label>Estado</label>
          <select name="estado" id="sel-estado" onchange="carregarMunicipios(this.value)">
            <option value="">UF</option>
            <?php foreach ($estados as $uf): ?>
            <option value="<?= $uf ?>"><?= $uf ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Município</label>
          <select name="municipio" id="sel-municipio">
            <option value="">Selecione o estado</option>
          </select>
        </div>
        <div class="form-group">
          <label>Ponto de referência</label>
          <input type="text" name="ponto_referencia" placeholder="Referência">
        </div>
      </div>
    </div>
  </div>

  <!-- ── PRODUTOS ── -->
  <div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header">
      <h3>📦 Produtos</h3>
    </div>
    <div class="card-body">
      <div class="produtos-grid">
        <?php for ($i = 1; $i <= 4; $i++): ?>
        <div class="produto-bloco">
          <div class="prod-title">Produto <?= $i ?></div>
          <div class="form-group">
            <label>Produto</label>
            <select name="produto_<?= $i ?>">
              <option value="">— selecione —</option>
              <?php foreach ($produtos_lista as $p): ?>
              <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Quantidade</label>
            <input type="text" name="quantidade_<?= $i ?>" placeholder="Ex: 1 pacote 500g">
          </div>
          <div class="form-group">
            <label>Lote</label>
            <input type="text" name="lote_<?= $i ?>" placeholder="Número do lote">
          </div>
          <div class="form-group">
            <label>Fabricação</label>
            <input type="date" name="fabricacao_<?= $i ?>">
          </div>
          <div class="form-group">
            <label>Validade</label>
            <input type="date" name="validade_<?= $i ?>">
          </div>
          <div class="form-group">
            <label>Local de compra</label>
            <input type="text" name="local_compra_<?= $i ?>" placeholder="Supermercado">
          </div>
        </div>
        <?php endfor; ?>
      </div>
    </div>
  </div>

  <!-- ── DESCRIÇÃO DA CHAMADA ── -->
  <div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
      <h3>📝 Descrição da chamada</h3>
    </div>
    <div class="card-body">
      <div class="form-row" style="grid-template-columns:240px 1fr;">
        <div class="form-group">
          <label>Motivo da chamada</label>
          <select name="motivo">
            <option value="">— selecione —</option>
            <?php foreach ($motivos as $m): ?>
            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Horário preferencial de agendamento</label>
          <input type="text" name="horario_preferencial" placeholder="Ex: manhã, tarde, 14h-18h">
        </div>
      </div>

      <div class="form-group" style="margin-top:.75rem;">
        <label>Descrição geral</label>
        <textarea name="descricao_geral" rows="4" placeholder="Descreva o problema relatado pelo consumidor..."></textarea>
      </div>

      <div class="form-group" style="margin-top:.75rem;">
        <label>Observações gerais</label>
        <textarea name="observacoes_gerais" rows="3" placeholder="Observações adicionais..."></textarea>
      </div>
    </div>
  </div>

  <!-- ── ACTIONS ── -->
  <div style="display:flex; gap:.75rem; justify-content:flex-end;">
    <a href="chamadas.php" class="btn btn-outline">Cancelar</a>
    <button type="submit" class="btn btn-primary">💾 Salvar chamada</button>
  </div>

</form>

<script>
function carregarMunicipios(uf) {
  const sel = document.getElementById('sel-municipio');
  sel.innerHTML = '<option value="">Carregando...</option>';
  if (!uf) { sel.innerHTML = '<option value="">Selecione o estado</option>'; return; }
  fetch('index.php?ajax=municipios&uf=' + uf)
    .then(r => r.json())
    .then(lista => {
      sel.innerHTML = '<option value="">Selecione</option>';
      lista.forEach(m => {
        const o = document.createElement('option');
        o.value = o.textContent = m;
        sel.appendChild(o);
      });
    });
}
</script>

<?php require '_footer.php'; ?>
