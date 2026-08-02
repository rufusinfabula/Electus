<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Electus\Core\Auth;
use Electus\Core\Csrf;
use Electus\Core\Database;
use Electus\Core\Flash;
use Electus\Core\Lang;
use Electus\Core\Mailer;
use Electus\Core\Theme;
use Electus\Models\Settings;

Auth::requireRole('superadmin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action = $_POST['action'] ?? 'save_theme';

    if ($action === 'save_theme') {
        $preset = $_POST['admin_theme'] ?? Theme::DEFAULT_PRESET;
        if (!isset(Theme::PALETTES[$preset])) $preset = Theme::DEFAULT_PRESET;
        Settings::set('admin_theme', $preset);

        Flash::success(__('settings_saved'));
        header('Location: /admin/settings.php');
        exit;
    }

    if ($action === 'save_lang') {
        $lang = $_POST['admin_lang'] ?? 'en';
        if (!in_array($lang, Lang::available(), true)) $lang = 'en';
        Settings::set('admin_lang', $lang);

        Flash::success(__('settings_saved'));
        header('Location: /admin/settings.php');
        exit;
    }

    if ($action === 'test_email') {
        $to = trim((string) ($_POST['test_email'] ?? ''));
        if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $ok = Mailer::send(
                $to,
                $to,
                'Electus — Test email',
                '<p>Questa è una email di test inviata dal pannello di amministrazione di Electus.</p>'
            );
            $ok ? Flash::success(__('settings_test_email_ok')) : Flash::error(__('settings_test_email_fail'));
        } else {
            Flash::error(__('settings_test_email_fail'));
        }
        header('Location: /admin/settings.php');
        exit;
    }
}

$currentPreset = Settings::get('admin_theme', Theme::DEFAULT_PRESET);
$currentLang   = Settings::get('admin_lang', 'en');

$pageTitle  = __('settings_title');
$activeMenu = 'settings';

ob_start();
?>

<h1 class="e-page-title"><?= __('settings_title') ?></h1>

<ul uk-accordion="multiple: false">

    <!-- Tema grafico -->
    <li class="uk-open">
        <a class="uk-accordion-title" href="#"><?= __('settings_admin_theme') ?></a>
        <div class="uk-accordion-content">
            <p style="font-size:.82rem;color:#9a94b8;margin:0 0 16px"><?= __('settings_admin_theme_desc') ?></p>

            <form method="post" id="theme-form">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="save_theme">
                <input type="hidden" name="admin_theme" id="admin_theme_input" value="<?= htmlspecialchars($currentPreset) ?>">

                <div class="uk-flex uk-flex-right" style="margin-bottom:14px">
                    <button type="submit" class="uk-button uk-button-primary">
                        <span uk-icon="icon:check;ratio:.85"></span> <?= __('save') ?>
                    </button>
                </div>

                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:8px">
                <?php foreach (Theme::PALETTES as $key => $pal):
                    $selected = ($currentPreset === $key);
                ?>
                <div class="e-tc" data-key="<?= $key ?>" onclick="selectTheme('<?= $key ?>')"
                     style="border:2px solid <?= $selected ? 'var(--e-primary)' : '#e8e6f0' ?>;
                            border-radius:12px;overflow:hidden;cursor:pointer;
                            background:#fff;transition:border-color .15s,box-shadow .15s;
                            box-shadow:<?= $selected ? '0 0 0 3px rgba(76,61,158,.15)' : 'none' ?>">

                    <!-- Colour preview strip -->
                    <div style="height:56px;background:<?= htmlspecialchars($pal['bg']) ?>;position:relative">
                        <!-- Navbar bar -->
                        <div style="height:14px;background:<?= htmlspecialchars($pal['primary']) ?>;display:flex;align-items:center;padding:0 8px;gap:4px">
                            <div style="width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,.5)"></div>
                            <div style="flex:1;height:3px;border-radius:2px;background:rgba(255,255,255,.3)"></div>
                        </div>
                        <!-- Fake content -->
                        <div style="padding:5px 8px;display:flex;gap:4px;align-items:center">
                            <div style="width:28px;height:26px;border-radius:4px;background:<?= htmlspecialchars($pal['primary']) ?>;opacity:.15"></div>
                            <div style="flex:1">
                                <div style="height:4px;border-radius:2px;background:<?= htmlspecialchars($pal['text']) ?>;opacity:.2;margin-bottom:3px;width:70%"></div>
                                <div style="height:3px;border-radius:2px;background:<?= htmlspecialchars($pal['text']) ?>;opacity:.1;width:50%"></div>
                            </div>
                            <div style="height:16px;padding:0 6px;border-radius:4px;background:<?= htmlspecialchars($pal['primary']) ?>;display:flex;align-items:center">
                                <div style="height:2px;width:18px;border-radius:1px;background:#fff;opacity:.8"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Swatches + label -->
                    <div style="padding:10px 12px 12px">
                        <div style="display:flex;gap:5px;margin-bottom:8px;align-items:center">
                            <div style="width:16px;height:16px;border-radius:50%;background:<?= htmlspecialchars($pal['primary']) ?>;flex-shrink:0" title="Primary"></div>
                            <div style="width:16px;height:16px;border-radius:50%;background:<?= htmlspecialchars($pal['secondary']) ?>;flex-shrink:0" title="Secondary"></div>
                            <div style="width:16px;height:16px;border-radius:50%;background:<?= htmlspecialchars($pal['accent']) ?>;flex-shrink:0" title="Accent"></div>
                            <div style="width:16px;height:16px;border-radius:50%;background:<?= htmlspecialchars($pal['bg']) ?>;border:1px solid #ddd;flex-shrink:0" title="Background"></div>
                            <?php if ($selected): ?>
                            <div style="margin-left:auto;width:18px;height:18px;border-radius:50%;background:var(--e-primary);display:flex;align-items:center;justify-content:center;flex-shrink:0" id="check_<?= $key ?>">
                                <span style="color:#fff;font-size:10px;font-weight:800">✓</span>
                            </div>
                            <?php else: ?>
                            <div style="margin-left:auto;width:18px;height:18px;border-radius:50%;border:2px solid #e0ddf0;flex-shrink:0" id="check_<?= $key ?>"></div>
                            <?php endif ?>
                        </div>
                        <div style="font-size:.78rem;font-weight:600;color:#2d2a40;line-height:1.3">
                            <?= htmlspecialchars($pal['label']) ?>
                        </div>
                        <?php if ($pal['dark']): ?>
                        <div style="font-size:.68rem;color:#9a94b8;margin-top:2px"><?= __('settings_dark_theme') ?></div>
                        <?php endif ?>
                    </div>
                </div>
                <?php endforeach ?>
                </div>
            </form>
        </div>
    </li>

    <!-- Lingua admin di default -->
    <li>
        <a class="uk-accordion-title" href="#"><?= __('settings_lang') ?></a>
        <div class="uk-accordion-content">
            <p style="font-size:.82rem;color:#9a94b8;margin:0 0 16px"><?= __('settings_lang_desc') ?></p>

            <form method="post" class="uk-grid-small" uk-grid>
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="save_lang">

                <div class="uk-width-auto@s">
                    <select class="uk-select" name="admin_lang">
                        <?php foreach (['it' => 'Italiano', 'en' => 'English', 'fr' => 'Français'] as $code => $label): ?>
                        <option value="<?= $code ?>" <?= $currentLang === $code ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="uk-width-auto@s">
                    <button type="submit" class="uk-button uk-button-primary">
                        <span uk-icon="icon:check;ratio:.85"></span> <?= __('save') ?>
                    </button>
                </div>
            </form>
            <p style="font-size:.75rem;color:#9a94b8;margin:12px 0 0"><?= __('settings_lang_note') ?></p>
        </div>
    </li>

    <!-- Test email -->
    <li>
        <a class="uk-accordion-title" href="#"><?= __('settings_test_email') ?></a>
        <div class="uk-accordion-content">
            <form method="post" class="uk-grid-small" uk-grid>
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="test_email">

                <div class="uk-width-expand@s">
                    <label class="uk-form-label" for="test_email"><?= __('settings_test_email_label') ?></label>
                    <input class="uk-input" type="email" id="test_email" name="test_email" required placeholder="nome@esempio.it">
                </div>
                <div class="uk-width-auto@s uk-flex uk-flex-bottom">
                    <button type="submit" class="uk-button uk-button-primary">
                        <span uk-icon="icon:mail;ratio:.85"></span> <?= __('settings_test_email_btn') ?>
                    </button>
                </div>
            </form>
        </div>
    </li>

    <!-- Informazioni sistema -->
    <li>
        <a class="uk-accordion-title" href="#"><?= __('settings_system_info') ?></a>
        <div class="uk-accordion-content">
            <?php
            $pdo         = Database::get();
            $dbVersion   = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $totalEvents = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
            $totalUsers  = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $uploadDir   = ROOT . '/assets/uploads/logos';
            $uploadOk    = is_dir($uploadDir) && is_writable($uploadDir);
            $opcacheOn   = function_exists('opcache_get_status') && opcache_get_status() !== false;
            ?>
            <table class="uk-table uk-table-small uk-table-divider">
                <tbody>
                    <tr>
                        <td><?= __('settings_php_version') ?></td>
                        <td><?= htmlspecialchars(phpversion()) ?></td>
                    </tr>
                    <tr>
                        <td><?= __('settings_db_version') ?></td>
                        <td><?= htmlspecialchars($dbVersion) ?></td>
                    </tr>
                    <tr>
                        <td><?= __('settings_total_events') ?></td>
                        <td><?= $totalEvents ?></td>
                    </tr>
                    <tr>
                        <td><?= __('settings_total_users') ?></td>
                        <td><?= $totalUsers ?></td>
                    </tr>
                    <tr>
                        <td><?= __('settings_upload_dir') ?></td>
                        <td>
                            <span class="uk-label" style="background:<?= $uploadOk ? '#2e9e5b' : '#d64545' ?>">
                                <?= $uploadOk ? __('settings_upload_ok') : __('settings_upload_fail') ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><?= __('settings_opcache') ?></td>
                        <td>
                            <span class="uk-label" style="background:<?= $opcacheOn ? '#2e9e5b' : '#9a94b8' ?>">
                                <?= $opcacheOn ? __('yes') : __('no') ?>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </li>

</ul>

<script>
function selectTheme(key) {
    // Update hidden input
    document.getElementById('admin_theme_input').value = key;

    // Reset all cards
    document.querySelectorAll('.e-tc').forEach(function(card) {
        card.style.borderColor = '#e8e6f0';
        card.style.boxShadow   = 'none';
        var k = card.dataset.key;
        var chk = document.getElementById('check_' + k);
        if (chk) {
            chk.style.background = 'transparent';
            chk.style.border     = '2px solid #e0ddf0';
            chk.innerHTML        = '';
        }
    });

    // Highlight selected card
    var sel = document.querySelector('[data-key="' + key + '"]');
    if (sel) {
        sel.style.borderColor = 'var(--e-primary)';
        sel.style.boxShadow   = '0 0 0 3px rgba(76,61,158,.15)';
        var chk = document.getElementById('check_' + key);
        if (chk) {
            chk.style.background = 'var(--e-primary)';
            chk.style.border     = 'none';
            chk.innerHTML        = '<span style="color:#fff;font-size:10px;font-weight:800">✓</span>';
        }
    }
}
</script>

<?php
$content = ob_get_clean();
require ROOT . '/templates/admin/layout.php';
