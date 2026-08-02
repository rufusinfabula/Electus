<?php

declare(strict_types=1);

define('ROOT', dirname(__DIR__));
require ROOT . '/vendor/autoload.php';

$config = require ROOT . '/config/config.php';
date_default_timezone_set($config['app']['timezone']);
session_name($config['app']['session_name'] ?? 'electus_session');
session_start();

$lang = $_SESSION['admin_lang']
    ?? \Electus\Models\Settings::get('admin_lang', $config['app']['admin_lang'] ?? 'en');
\Electus\Core\Lang::init($lang);

use Electus\Core\Auth;
use Electus\Core\Csrf;
use Electus\Core\Flash;

if (Auth::isLoggedIn()) {
    header('Location: /admin/');
    exit;
}

// Rate limiting login per sessione: nessuna colonna di lock su `users`, nessun
// servizio esterno. Limite noto: è per sessione, non per IP/account globale
// (una finestra in incognito azzera il contatore) — accettabile per scoraggiare
// solo attacchi automatici di base.
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_SECONDS = 300;

$error = '';
$lockedUntil = $_SESSION['login_locked_until'] ?? 0;
$locked = $lockedUntil > time();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
    Csrf::check();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (Auth::login($email, $password)) {
        unset($_SESSION['login_attempts'], $_SESSION['login_locked_until'], $_SESSION['login_attempt_email']);
        $user = Auth::currentUser();
        Flash::success(__('welcome_back', ['name' => $user['name']]));
        header('Location: /admin/');
        exit;
    }

    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['login_attempt_email'] = $email;

    if ($_SESSION['login_attempts'] >= LOGIN_MAX_ATTEMPTS) {
        $_SESSION['login_locked_until'] = time() + LOGIN_LOCKOUT_SECONDS;
        $_SESSION['login_attempts'] = 0;
        $lockedUntil = $_SESSION['login_locked_until'];
        $locked = true;
        error_log('Electus login lockout: ' . $email . ' from ' . $_SERVER['REMOTE_ADDR']);
    }

    $error = __('invalid_credentials');
}

$lockSecondsRemaining = $locked ? max(0, $lockedUntil - time()) : 0;
?>
<!DOCTYPE html>
<html lang="<?= \Electus\Core\Lang::current() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('login_title') ?> — Electus</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/electus-32.png">
    <link rel="icon" type="image/png" sizes="64x64" href="/icons/electus-64.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/electus-180.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/uikit@3/dist/css/uikit.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        body { background: var(--e-bg); display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .e-login-box { background: #fff; border-radius: 16px; padding: 40px 48px; width: 100%; max-width: 420px; box-shadow: 0 4px 24px rgba(47,106,217,.1); border: 1px solid #dce8fb; }
        .e-login-logo { text-align: center; margin-bottom: 32px; }
    </style>
</head>
<body>
    <div class="e-login-box">
        <div class="e-login-logo">
            <div style="width:64px;height:64px;display:inline-flex;align-items:center;justify-content:center;background:var(--e-primary);border-radius:16px;margin-bottom:4px"><img src="/icons/electus-64.png" alt="Electus" style="width:48px;height:48px;object-fit:contain;display:block"></div>
            <h2 class="e-wordmark" style="margin:12px 0 0;font-size:1.3rem;color:var(--e-text);">Electus</h2>
        </div>

        <?php if ($error): ?>
        <div class="uk-alert-danger" uk-alert>
            <p><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif ?>

        <form method="post" action="">
            <?= Csrf::field() ?>
            <div class="uk-margin">
                <label class="uk-form-label"><?= __('email') ?></label>
                <div class="uk-form-controls">
                    <input class="uk-input" type="email" name="email"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required autofocus>
                </div>
            </div>
            <div class="uk-margin">
                <label class="uk-form-label"><?= __('password') ?></label>
                <div class="uk-form-controls">
                    <input class="uk-input" type="password" name="password" required>
                </div>
            </div>
            <div class="uk-margin-top">
                <button class="uk-button uk-button-primary uk-width-1-1" type="submit"<?= $locked ? ' disabled' : '' ?>>
                    <?= __('login') ?>
                </button>
            </div>
        </form>

        <?php if ($locked): ?>
        <div class="uk-alert-warning uk-margin-top" uk-alert>
            <p><?= htmlspecialchars(__('login_too_many_attempts', ['seconds' => (string) $lockSecondsRemaining])) ?></p>
        </div>
        <?php endif ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/uikit@3/dist/js/uikit.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/uikit@3/dist/js/uikit-icons.min.js"></script>
    <?php require ROOT . '/templates/_footer.php' ?>
</body>
</html>
