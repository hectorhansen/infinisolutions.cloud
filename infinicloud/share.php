<?php
// ============================================================
// InfiniCloud - Página Pública de Download
// Acessada via: share.infinisolutions.cloud/{hash}
// .htaccess faz rewrite: /{hash} → share.php?hash={hash}
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$hash = trim($_GET['hash'] ?? '');

// Valida formato do hash (SHA1 = 40 chars hex)
$validHash = $hash && preg_match('/^[a-f0-9]{40}$/', $hash);

$record   = null;
$isValid  = false;
$isExpired= false;

if ($validHash) {
    $db = ICDB::connect();
    $stmt = $db->prepare('
        SELECT
            l.hash,
            l.expires_at,
            l.is_active,
            f.original_name,
            f.mime_type,
            f.size_bytes,
            f.user_id
        FROM ic_share_links l
        JOIN ic_files f ON f.id = l.file_id
        WHERE l.hash = ?
          AND f.deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->execute([$hash]);
    $record = $stmt->fetch();

    if ($record) {
        $isExpired = strtotime($record['expires_at']) < time();
        $isValid   = $record['is_active'] && !$isExpired;
    }
}

// Ícone do arquivo
$icon = $record ? ic_file_icon($record['mime_type']) : 'fa-file';
$size = $record ? ic_format_size((int)$record['size_bytes']) : '';
$expiresFormatted = $record ? date('d/m/Y \à\s H\hi', strtotime($record['expires_at'])) : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isValid ? htmlspecialchars($record['original_name']) . ' — ' : '' ?>InfiniCloud</title>
    <meta name="description" content="Download seguro de arquivos via InfiniCloud — Infini Solutions.">
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1; --accent: #8b5cf6;
            --bg: #0d0f17; --card: rgba(255,255,255,0.04);
            --border: rgba(255,255,255,0.08);
            --text: #f1f5f9; --muted: #94a3b8; --dim: #475569;
            --success: #10b981; --danger: #ef4444; --warning: #f59e0b;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background-image:
                radial-gradient(ellipse at 20% 40%, rgba(99,102,241,0.10) 0%, transparent 55%),
                radial-gradient(ellipse at 80% 70%, rgba(139,92,246,0.07) 0%, transparent 50%);
        }
        .card {
            width: 100%;
            max-width: 500px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 44px 40px;
            backdrop-filter: blur(20px);
            box-shadow: 0 4px 40px rgba(0,0,0,0.4);
            animation: slideUp 0.4s ease;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 36px;
        }
        .logo-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; color: #fff;
        }
        .logo-text { font-size: 1rem; font-weight: 700; color: var(--text); }
        .logo-text span { font-weight: 400; color: var(--muted); margin-left: 4px; font-size: 0.85rem; }

        /* Ícone do arquivo */
        .file-icon-big {
            width: 72px; height: 72px;
            border-radius: 16px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 30px;
            margin-bottom: 20px;
        }
        .ic-pdf   { background: rgba(239,68,68,.12);  color:#ef4444; }
        .ic-image { background: rgba(16,185,129,.12); color:#10b981; }
        .ic-zip   { background: rgba(245,158,11,.12); color:#f59e0b; }
        .ic-word  { background: rgba(59,130,246,.12); color:#3b82f6; }
        .ic-other { background: rgba(148,163,184,.1); color:#94a3b8; }

        .file-name {
            font-size: 1.15rem; font-weight: 700;
            color: var(--text);
            word-break: break-all;
            line-height: 1.4;
            margin-bottom: 10px;
        }
        .meta-row {
            display: flex; align-items: center; gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 28px;
        }
        .meta-tag {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            border-radius: 99px;
            font-size: 0.78rem; color: var(--muted);
        }
        .meta-tag i { font-size: 0.72rem; }

        /* Botão de download */
        .btn-download {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            width: 100%;
            padding: 15px 24px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border: none; border-radius: 10px;
            color: #fff; font-size: 1rem; font-weight: 700;
            font-family: inherit;
            cursor: pointer; text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 4px 20px rgba(99,102,241,0.3);
        }
        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(99,102,241,0.4);
        }
        .btn-download:active { transform: translateY(0); }

        /* Aviso de expiração */
        .expiry-notice {
            display: flex; align-items: center; gap: 8px;
            margin-top: 16px;
            padding: 10px 14px;
            background: rgba(245,158,11,0.08);
            border: 1px solid rgba(245,158,11,0.2);
            border-radius: 8px;
            font-size: 0.8rem; color: #fbbf24;
        }

        /* Estado de erro / expirado */
        .state-icon {
            font-size: 3rem;
            margin-bottom: 20px;
        }
        .state-icon.expired { color: var(--warning); }
        .state-icon.invalid { color: var(--danger); }

        h2.state-title {
            font-size: 1.3rem; font-weight: 700;
            margin-bottom: 10px;
        }
        p.state-desc {
            font-size: 0.9rem; color: var(--muted);
            line-height: 1.6;
        }
        .back-link {
            display: inline-block;
            margin-top: 24px;
            font-size: 0.85rem;
            color: var(--primary);
            text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; }

        .footer {
            margin-top: 20px;
            font-size: 0.75rem;
            color: var(--dim);
            text-align: center;
        }
        .footer a { color: var(--primary); text-decoration: none; }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @media (max-width: 480px) {
            .card { padding: 32px 24px; }
            .file-name { font-size: 1rem; }
        }
    </style>
</head>
<body>
<div class="card">
    <!-- Logo / Branding -->
    <div class="logo">
        <div class="logo-icon"><i class="fa fa-cloud-arrow-up"></i></div>
        <div class="logo-text">InfiniCloud <span>by Infini Solutions</span></div>
    </div>

    <?php if ($isValid): ?>
    <!-- ===================== LINK VÁLIDO ===================== -->
    <?php
        $iconClass = 'ic-other';
        if (str_contains($icon, 'pdf'))    $iconClass = 'ic-pdf';
        if (str_contains($icon, 'image'))  $iconClass = 'ic-image';
        if (str_contains($icon, 'zipper') || str_contains($icon, 'zip')) $iconClass = 'ic-zip';
        if (str_contains($icon, 'word') || str_contains($icon, 'excel')) $iconClass = 'ic-word';
    ?>
    <div style="text-align:center;">
        <div class="file-icon-big <?= $iconClass ?>">
            <i class="fa <?= htmlspecialchars($icon) ?>"></i>
        </div>
    </div>

    <div class="file-name"><?= htmlspecialchars($record['original_name']) ?></div>

    <div class="meta-row">
        <span class="meta-tag"><i class="fa fa-weight-hanging"></i> <?= $size ?></span>
        <span class="meta-tag"><i class="fa fa-file-circle-check"></i> <?= htmlspecialchars($record['mime_type']) ?></span>
    </div>

    <a
        href="api/download.php?hash=<?= urlencode($hash) ?>"
        class="btn-download"
        download
        id="download-btn"
    >
        <i class="fa fa-download"></i>
        Baixar Arquivo
    </a>

    <div class="expiry-notice">
        <i class="fa fa-clock"></i>
        Este link expira em <strong style="margin-left:4px;"><?= $expiresFormatted ?></strong>
    </div>

    <?php elseif ($isExpired && $record): ?>
    <!-- ===================== LINK EXPIRADO ===================== -->
    <div style="text-align:center;">
        <div class="state-icon expired"><i class="fa fa-clock-rotate-left"></i></div>
        <h2 class="state-title">Link Expirado</h2>
        <p class="state-desc">
            Este link de compartilhamento expirou em <strong><?= $expiresFormatted ?></strong>
            e o arquivo não está mais disponível para download.<br><br>
            Entre em contato com quem compartilhou para receber um novo link.
        </p>
    </div>

    <?php else: ?>
    <!-- ===================== LINK INVÁLIDO / NÃO ENCONTRADO ===================== -->
    <div style="text-align:center;">
        <div class="state-icon invalid"><i class="fa fa-link-slash"></i></div>
        <h2 class="state-title">Link Inválido</h2>
        <p class="state-desc">
            Este link não existe ou foi revogado pelo remetente.<br><br>
            Verifique se o endereço está correto ou solicite um novo link de compartilhamento.
        </p>
    </div>
    <?php endif; ?>

</div>

<p class="footer">
    &copy; <?= date('Y') ?> <a href="https://infinisolutions.cloud" target="_blank">Infini Solutions</a> — InfiniCloud
</p>
</body>
</html>
