<?php
// ============================================
// GRANFINO - Login
// login.php
// ============================================
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['atendente_id'])) {
    header('Location: index.php');
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email && $senha) {
        $stmt = db()->prepare('SELECT id, nome, senha FROM atendentes WHERE email = ? AND ativo = 1');
        $stmt->execute([$email]);
        $atendente = $stmt->fetch();

        if ($atendente && password_verify($senha, $atendente['senha'])) {
            $_SESSION['atendente_id']   = $atendente['id'];
            $_SESSION['atendente_nome'] = $atendente['nome'];
            $_SESSION['last_activity']  = time();
            header('Location: index.php');
            exit;
        }
    }
    $erro = 'E-mail ou senha inválidos.';
}
$timeout = isset($_GET['timeout']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login · <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --red:     #D4001A;
    --red-dk:  #A80015;
    --cream:   #FAF7F2;
    --warm:    #F0EAE0;
    --charcoal:#1A1A1A;
    --mid:     #6B6560;
    --border:  #DDD5C8;
  }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--cream);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
  }

  /* geometric background */
  body::before {
    content: '';
    position: fixed;
    top: -200px; right: -200px;
    width: 600px; height: 600px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(212,0,26,.07) 0%, transparent 70%);
    pointer-events: none;
  }
  body::after {
    content: '';
    position: fixed;
    bottom: -150px; left: -150px;
    width: 500px; height: 500px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(212,0,26,.05) 0%, transparent 70%);
    pointer-events: none;
  }

  .login-wrap {
    width: 100%;
    max-width: 420px;
    padding: 1.5rem;
    animation: fadeUp .5s ease both;
  }

  @keyframes fadeUp {
    from { opacity:0; transform: translateY(24px); }
    to   { opacity:1; transform: translateY(0); }
  }

  .logo-block {
    text-align: center;
    margin-bottom: 2.5rem;
  }

  .logo-block .brand {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin-bottom: .75rem;
  }

  .logo-block .dot {
    width: 36px; height: 36px;
    background: var(--red);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
  }

  .logo-block .dot span {
    font-family: 'Syne', sans-serif;
    font-weight: 800;
    font-size: 18px;
    color: #fff;
    letter-spacing: -1px;
  }

  .logo-block h1 {
    font-family: 'Syne', sans-serif;
    font-weight: 800;
    font-size: 1.5rem;
    color: var(--charcoal);
    letter-spacing: -0.5px;
  }

  .logo-block p {
    font-size: .82rem;
    color: var(--mid);
    letter-spacing: .04em;
    text-transform: uppercase;
  }

  .card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 32px rgba(0,0,0,.06);
  }

  .card h2 {
    font-family: 'Syne', sans-serif;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--charcoal);
    margin-bottom: 1.5rem;
  }

  .field { margin-bottom: 1.1rem; }

  .field label {
    display: block;
    font-size: .78rem;
    font-weight: 500;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: var(--mid);
    margin-bottom: .4rem;
  }

  .field input {
    width: 100%;
    padding: .7rem 1rem;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: .95rem;
    color: var(--charcoal);
    background: var(--cream);
    transition: border-color .2s, box-shadow .2s;
    outline: none;
  }

  .field input:focus {
    border-color: var(--red);
    box-shadow: 0 0 0 3px rgba(212,0,26,.1);
    background: #fff;
  }

  .btn-login {
    width: 100%;
    padding: .8rem;
    background: var(--red);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-family: 'Syne', sans-serif;
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: .03em;
    cursor: pointer;
    transition: background .2s, transform .1s;
    margin-top: .5rem;
  }

  .btn-login:hover  { background: var(--red-dk); }
  .btn-login:active { transform: scale(.98); }

  .alert {
    padding: .7rem 1rem;
    border-radius: 8px;
    font-size: .88rem;
    margin-bottom: 1rem;
  }
  .alert-error   { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
  .alert-warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
</style>
</head>
<body>
<div class="login-wrap">
  <div class="logo-block">
    <img src="granfino_logo.png" alt="Granfino" style="height:110px;margin-bottom:.5rem;">
    <p>Gestão de Qualidade · SAC</p>
  </div>

  <div class="card">
    <h2>Acesso ao sistema</h2>

    <?php if ($timeout): ?>
    <div class="alert alert-warning">Sessão expirada. Faça login novamente.</div>
    <?php endif; ?>

    <?php if ($erro): ?>
    <div class="alert alert-error"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="field">
        <label>E-mail</label>
        <input type="email" name="email" required autofocus
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Senha</label>
        <input type="password" name="senha" required>
      </div>
      <button type="submit" class="btn-login">Entrar</button>
    </form>
  </div>
</div>
</body>
</html>
