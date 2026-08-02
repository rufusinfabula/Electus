<?php

declare(strict_types=1);

define('ROOT', __DIR__);

require ROOT . '/vendor/autoload.php';

use Electus\Core\Csrf;
use Electus\Core\Database;
use Electus\Core\Mailer;

if (!file_exists(ROOT . '/config/config.php')) {
    header('Location: /install/');
    exit;
}

$config = require ROOT . '/config/config.php';
date_default_timezone_set($config['app']['timezone']);
session_name($config['app']['session_name'] ?? 'electus_session');
session_start();

$errors  = [];
$sent    = isset($_GET['ok']);
$name    = '';
$email   = '';
$org     = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();

    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $org     = trim($_POST['organization'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $honeypot = trim($_POST['website'] ?? '');

    if ($honeypot === '') {
        if ($name === '') {
            $errors[] = 'Il nome è obbligatorio.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Inserisci un indirizzo email valido.';
        }

        if (!$errors) {
            $pdo = Database::get();
            $stmt = $pdo->prepare(
                'INSERT INTO access_requests (name, email, organization, message) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$name, $email, $org ?: null, $message ?: null]);

            $admins = $pdo->query(
                "SELECT name, email FROM users WHERE role = 'superadmin' AND status = 'active'"
            )->fetchAll();
            foreach ($admins as $admin) {
                Mailer::send(
                    $admin['email'],
                    $admin['name'],
                    'Nuova richiesta di accesso — Electus',
                    '<p>Nuova richiesta di accesso ricevuta dalla landing page.</p>'
                    . '<p><strong>Nome:</strong> ' . htmlspecialchars($name) . '<br>'
                    . '<strong>Email:</strong> ' . htmlspecialchars($email) . '<br>'
                    . '<strong>Organizzazione:</strong> ' . htmlspecialchars($org ?: '—') . '</p>'
                    . '<p><strong>Messaggio:</strong><br>' . nl2br(htmlspecialchars($message ?: '—')) . '</p>'
                    . '<p><a href="' . htmlspecialchars($config['app']['url'] ?? '') . '/admin/users.php">Rivedi la richiesta</a></p>'
                );
            }

            header('Location: /richiedi-accesso.php?ok=1');
            exit;
        }
    } else {
        // Honeypot compilato: risposta silenziosa, nessuna insert.
        header('Location: /richiedi-accesso.php?ok=1');
        exit;
    }
}

$csrfField = Csrf::field();

ob_start();
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Richiedi accesso — Electus</title>
    <meta name="description" content="Richiedi un account per creare e gestire eventi di voto con Electus.">
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/electus-32.png">
    <link rel="icon" type="image/png" sizes="64x64" href="/icons/electus-64.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/electus-180.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/uikit@3/dist/css/uikit.min.css">
    <style>
        :root {
            --p: #2f6ad9;
            --p-dark: #1d4faa;
            --p-light: #ebf2ff;
            --p-mid: #dbeafe;
            --text: #0f172a;
            --muted: #64748b;
            --radius: 14px;
        }
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; color: var(--text); background: #f5f8ff; margin: 0; }

        .l-nav { background: var(--p); padding: 0 24px; height: 60px; display: flex; align-items: center; justify-content: space-between; }
        .l-nav-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .l-logo-img { width: 32px; height: 32px; border-radius: 8px; background: #fff; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .l-logo-img img { width: 28px; height: 28px; object-fit: contain; }
        .l-logo-text { color: #fff; font-weight: 700; font-size: 1.1rem; letter-spacing: -.01em; }
        .l-nav-back { color: rgba(255,255,255,.85); text-decoration: none; font-size: .9rem; font-weight: 600; }
        .l-nav-back:hover { color: #fff; }

        .r-wrap { max-width: 520px; margin: 0 auto; padding: 56px 24px 80px; }
        .r-card { background: #fff; border: 1px solid #e0ebfa; border-radius: var(--radius); padding: 36px 32px; box-shadow: 0 4px 24px rgba(15,23,42,.06); }
        .r-card h1 { font-size: 1.5rem; font-weight: 800; margin: 0 0 8px; letter-spacing: -.01em; }
        .r-card > p.r-sub { color: var(--muted); font-size: .95rem; margin: 0 0 28px; line-height: 1.55; }

        .r-field { margin-bottom: 18px; }
        .r-field label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: 6px; }
        .r-field input, .r-field textarea {
            width: 100%; border: 1px solid #d7e2f5; border-radius: 8px; padding: 10px 12px;
            font-size: .95rem; font-family: inherit; color: var(--text); background: #fff;
        }
        .r-field input:focus, .r-field textarea:focus { outline: none; border-color: var(--p); box-shadow: 0 0 0 3px var(--p-mid); }
        .r-field textarea { resize: vertical; min-height: 90px; }
        .r-hint { font-size: .78rem; color: var(--muted); margin-top: 4px; }
        .r-hp { position: absolute; left: -9999px; top: -9999px; }

        .r-submit { width: 100%; background: var(--p); color: #fff; border: none; border-radius: 10px; padding: 13px 20px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: background .15s; }
        .r-submit:hover { background: var(--p-dark); }

        .r-errors { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 8px; padding: 12px 16px; font-size: .88rem; margin-bottom: 20px; }
        .r-errors ul { margin: 0; padding-left: 18px; }

        .r-success { text-align: center; padding: 20px 0; }
        .r-success-icon { width: 56px; height: 56px; background: #dcfce7; color: #16a34a; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
        .r-success-icon svg { width: 28px; height: 28px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
        .r-success h1 { margin-bottom: 12px; }
        .r-success p { color: var(--muted); font-size: .95rem; line-height: 1.6; margin: 0 0 24px; }
        .r-back-link { display: inline-block; color: var(--p); font-weight: 600; text-decoration: none; font-size: .9rem; }
        .r-back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<nav class="l-nav">
    <a href="/" class="l-nav-logo">
        <div class="l-logo-img"><img src="/icons/electus-64.png" alt="Electus"></div>
        <span class="l-logo-text">Electus</span>
    </a>
    <a href="/" class="l-nav-back">&larr; Torna alla home</a>
</nav>

<div class="r-wrap">
    <div class="r-card">
        <?php if ($sent): ?>
        <div class="r-success">
            <div class="r-success-icon">
                <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
            </div>
            <h1>Richiesta inviata</h1>
            <p>Grazie! La tua richiesta è stata ricevuta. Il team Electus la esaminerà e ti contatterà via email per attivare il tuo account.</p>
            <a href="/" class="r-back-link">&larr; Torna alla home</a>
        </div>
        <?php else: ?>
        <h1>Richiedi accesso</h1>
        <p class="r-sub">Compila il modulo per richiedere un account amministratore. Il superadmin del sistema valuterà la richiesta e ti attiverà l'accesso.</p>

        <?php if ($errors): ?>
        <div class="r-errors">
            <ul>
                <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach ?>
            </ul>
        </div>
        <?php endif ?>

        <form method="post" novalidate>
            <?= $csrfField ?>
            <input class="r-hp" type="text" name="website" tabindex="-1" autocomplete="off">

            <div class="r-field">
                <label for="f-name">Nome e cognome</label>
                <input id="f-name" type="text" name="name" value="<?= htmlspecialchars($name) ?>" required>
            </div>
            <div class="r-field">
                <label for="f-email">Email</label>
                <input id="f-email" type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
            </div>
            <div class="r-field">
                <label for="f-org">Organizzazione <span style="font-weight:400;color:var(--muted)">(opzionale)</span></label>
                <input id="f-org" type="text" name="organization" value="<?= htmlspecialchars($org) ?>">
            </div>
            <div class="r-field">
                <label for="f-msg">Messaggio <span style="font-weight:400;color:var(--muted)">(opzionale)</span></label>
                <textarea id="f-msg" name="message" placeholder="Raccontaci brevemente per quale evento vuoi usare Electus."><?= htmlspecialchars($message) ?></textarea>
            </div>

            <button type="submit" class="r-submit">Invia richiesta</button>
            <p class="r-hint">Riceverai una risposta via email dal team che gestisce questa installazione di Electus.</p>
        </form>
        <?php endif ?>
    </div>
</div>

<?php require ROOT . '/templates/_footer.php' ?>
</body>
</html>
<?php
echo ob_get_clean();
