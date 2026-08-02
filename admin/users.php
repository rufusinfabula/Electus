<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Electus\Core\Auth;
use Electus\Core\Csrf;
use Electus\Core\Database;
use Electus\Core\Flash;
use Electus\Models\Event;

Auth::requireRole('superadmin');

$pdo         = Database::get();
$currentUser = Auth::currentUser();

$assignableRoles = ['event_manager', 'results_reader'];

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action = $_POST['_action'] ?? '';

    if ($action === 'create') {
        $name      = trim($_POST['name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $password  = (string) ($_POST['password'] ?? '');
        $role      = $_POST['role'] ?? '';
        $requestId = (int) ($_POST['request_id'] ?? 0);

        if (!in_array($role, $assignableRoles, true)) {
            $role = 'results_reader';
        }

        if ($name === '' || $email === '' || strlen($password) < 8) {
            Flash::error(__('user_password_too_short'));
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                Flash::error(__('user_email_exists'));
            } else {
                Auth::createUser($name, $email, $password, $role);
                if ($requestId) {
                    $pdo->prepare("UPDATE access_requests SET status = 'approved' WHERE id = ?")
                        ->execute([$requestId]);
                }
                Flash::success(__('user_created_ok'));
            }
        }
    }

    if ($action === 'dismiss_request') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE access_requests SET status = 'dismissed' WHERE id = ?")->execute([$id]);
            Flash::success(__('access_request_dismissed_ok'));
        }
    }

    if ($action === 'toggle_status') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id && $id !== (int) $currentUser['id']) {
            $stmt = $pdo->prepare('SELECT role, status FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $target = $stmt->fetch();
            if ($target && $target['role'] !== 'superadmin') {
                $newStatus = $target['status'] === 'active' ? 'disabled' : 'active';
                $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
                Flash::success($newStatus === 'disabled' ? __('user_disabled_ok') : __('user_enabled_ok'));
            }
        }
    }

    if ($action === 'reset_password') {
        $id       = (int) ($_POST['id'] ?? 0);
        $password = (string) ($_POST['password'] ?? '');
        if ($id && strlen($password) >= 8) {
            $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $target = $stmt->fetch();
            if ($target && $target['role'] !== 'superadmin') {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $id]);
                Flash::success(__('user_updated_ok'));
            }
        } else {
            Flash::error(__('user_password_too_short'));
        }
    }

    if ($action === 'assign_events') {
        $id       = (int) ($_POST['id'] ?? 0);
        $eventIds = array_map('intval', array_filter($_POST['event_ids'] ?? []));
        if ($id) {
            $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $target = $stmt->fetch();
            if ($target && $target['role'] !== 'superadmin') {
                $pdo->prepare('DELETE FROM user_event_permissions WHERE user_id = ?')->execute([$id]);
                if ($eventIds) {
                    $insert = $pdo->prepare(
                        'INSERT INTO user_event_permissions (user_id, event_id, role) VALUES (?, ?, ?)'
                    );
                    foreach ($eventIds as $eventId) {
                        $insert->execute([$id, $eventId, $target['role']]);
                    }
                }
                Flash::success(__('user_updated_ok'));
            }
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id && $id !== (int) $currentUser['id']) {
            $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $target = $stmt->fetch();
            if ($target && $target['role'] !== 'superadmin') {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM events WHERE created_by = ?');
                $stmt->execute([$id]);
                if ((int) $stmt->fetchColumn() > 0) {
                    Flash::error(__('user_delete_blocked'));
                } else {
                    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                    Flash::success(__('user_deleted_ok'));
                }
            }
        }
    }

    header('Location: /admin/users.php');
    exit;
}

$users  = $pdo->query('SELECT * FROM users ORDER BY (role = "superadmin") DESC, created_at DESC')->fetchAll();
$events = Event::all();

$permsByUser = [];
$stmt = $pdo->query('SELECT user_id, event_id FROM user_event_permissions');
foreach ($stmt->fetchAll() as $row) {
    $permsByUser[(int) $row['user_id']][] = (int) $row['event_id'];
}

$pendingRequests = $pdo->query(
    "SELECT * FROM access_requests WHERE status = 'pending' ORDER BY created_at DESC"
)->fetchAll();

$pageTitle  = __('users_title');
$activeMenu = 'users';

ob_start();
?>
<div class="uk-flex uk-flex-between uk-flex-middle uk-margin-bottom">
    <h1 class="e-page-title uk-margin-remove"><?= __('users_title') ?></h1>
    <a href="#modal-user-create" uk-toggle class="uk-button uk-button-primary" onclick="euPrefill('','','')">
        <span uk-icon="plus-circle"></span> <?= __('user_create') ?>
    </a>
</div>

<?php if ($pendingRequests): ?>
<div class="e-table uk-margin-bottom">
    <h3 style="font-size:.95rem;font-weight:700;padding:16px 16px 0">
        <?= __('access_requests_title') ?>
        <span class="e-badge e-badge-draft" style="margin-left:6px"><?= count($pendingRequests) ?></span>
    </h3>
    <table class="uk-table uk-table-hover uk-table-divider uk-margin-remove">
        <thead>
            <tr>
                <th><?= __('access_request_name') ?></th>
                <th><?= __('access_request_email') ?></th>
                <th><?= __('access_request_org') ?></th>
                <th><?= __('access_request_date') ?></th>
                <th style="width:180px"><?= __('actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pendingRequests as $req): ?>
            <tr>
                <td><?= htmlspecialchars($req['name']) ?></td>
                <td><?= htmlspecialchars($req['email']) ?></td>
                <td><?= htmlspecialchars($req['organization'] ?: '—') ?></td>
                <td style="color:#9a94b8;font-size:.8rem"><?= date('d/m/Y', strtotime($req['created_at'])) ?></td>
                <td>
                    <div class="uk-flex uk-flex-middle" style="gap:8px">
                        <a href="#modal-user-create" uk-toggle class="uk-button uk-button-primary uk-button-small"
                           onclick="euPrefill('<?= htmlspecialchars($req['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($req['email'], ENT_QUOTES) ?>', '<?= (int) $req['id'] ?>')">
                            <?= __('access_request_approve') ?>
                        </a>
                        <form method="post" style="display:inline">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="_action" value="dismiss_request">
                            <input type="hidden" name="id" value="<?= $req['id'] ?>">
                            <button type="submit" class="uk-button uk-button-default uk-button-small"><?= __('access_request_dismiss') ?></button>
                        </form>
                    </div>
                    <?php if ($req['message']): ?>
                    <div style="font-size:.78rem;color:#9a94b8;margin-top:4px;max-width:320px">
                        <?= nl2br(htmlspecialchars($req['message'])) ?>
                    </div>
                    <?php endif ?>
                </td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<?php endif ?>

<div class="e-table">
    <table class="uk-table uk-table-hover uk-table-divider uk-margin-remove">
        <thead>
            <tr>
                <th><?= __('user_name') ?></th>
                <th><?= __('user_email') ?></th>
                <th><?= __('user_role') ?></th>
                <th><?= __('user_status') ?></th>
                <th><?= __('user_created') ?></th>
                <th style="width:160px"><?= __('actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): $isSuperadmin = $u['role'] === 'superadmin'; ?>
            <tr>
                <td><?= htmlspecialchars($u['name']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <span class="e-badge <?= $isSuperadmin ? 'e-badge-active' : 'e-badge-draft' ?>">
                        <?= __('role_' . $u['role']) ?>
                    </span>
                </td>
                <td>
                    <?php if ($isSuperadmin): ?>
                    <span class="e-badge e-badge-active"><?= __('user_status_active') ?></span>
                    <?php elseif ($u['status'] === 'active'): ?>
                    <span class="e-badge e-badge-active"><?= __('user_status_active') ?></span>
                    <?php else: ?>
                    <span class="e-badge e-badge-closed"><?= __('user_status_disabled') ?></span>
                    <?php endif ?>
                </td>
                <td style="color:#9a94b8;font-size:.8rem"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <?php if (!$isSuperadmin): ?>
                    <div class="uk-flex uk-flex-middle" style="gap:8px">
                        <a href="#modal-events-<?= $u['id'] ?>" uk-toggle
                           uk-icon="icon:calendar;ratio:.8" uk-tooltip="<?= __('user_events_access') ?>"
                           class="uk-icon-link"></a>
                        <a href="#modal-reset-<?= $u['id'] ?>" uk-toggle
                           uk-icon="icon:lock;ratio:.8" uk-tooltip="<?= __('user_reset_password') ?>"
                           class="uk-icon-link"></a>
                        <form method="post" style="display:inline">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="_action" value="toggle_status">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <?php if ($u['status'] === 'active'): ?>
                            <button uk-icon="icon:ban;ratio:.8" uk-tooltip="<?= __('user_disable') ?>"
                                    style="color:#e67e22;background:none;border:none;cursor:pointer;padding:0"
                                    class="uk-icon-link"></button>
                            <?php else: ?>
                            <button uk-icon="icon:check;ratio:.8" uk-tooltip="<?= __('user_enable') ?>"
                                    style="color:#27ae60;background:none;border:none;cursor:pointer;padding:0"
                                    class="uk-icon-link"></button>
                            <?php endif ?>
                        </form>
                        <form method="post" style="display:inline">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="_action" value="delete">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button uk-icon="icon:trash;ratio:.8" uk-tooltip="<?= __('user_delete') ?>"
                                    data-confirm="<?= htmlspecialchars(__('confirm_delete')) ?>"
                                    style="color:#e74c3c;background:none;border:none;cursor:pointer;padding:0"
                                    class="uk-icon-link"></button>
                        </form>
                    </div>

                    <!-- Events access modal -->
                    <div id="modal-events-<?= $u['id'] ?>" uk-modal>
                        <div class="uk-modal-dialog uk-modal-body">
                            <button class="uk-modal-close-default" type="button" uk-close></button>
                            <h3 class="uk-modal-title" style="font-size:1rem"><?= __('user_events_access') ?></h3>
                            <p style="font-size:.85rem;color:#9a94b8;margin-bottom:12px">
                                <?= htmlspecialchars($u['name']) ?>
                            </p>
                            <form method="post">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="_action" value="assign_events">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <?php if (empty($events)): ?>
                                <p style="color:#9a94b8"><?= __('user_no_events') ?></p>
                                <?php else: ?>
                                <div style="max-height:320px;overflow-y:auto">
                                    <?php foreach ($events as $ev): ?>
                                    <label class="uk-flex uk-flex-middle uk-margin-small-bottom" style="gap:8px">
                                        <input type="checkbox" class="uk-checkbox" name="event_ids[]"
                                               value="<?= $ev['id'] ?>"
                                               <?= in_array((int) $ev['id'], $permsByUser[$u['id']] ?? [], true) ? 'checked' : '' ?>>
                                        <?= htmlspecialchars($ev['name']) ?>
                                    </label>
                                    <?php endforeach ?>
                                </div>
                                <?php endif ?>
                                <div class="uk-flex uk-margin-top" style="gap:8px">
                                    <button class="uk-button uk-button-primary"><?= __('save') ?></button>
                                    <button type="button" class="uk-button uk-button-default uk-modal-close"><?= __('cancel') ?></button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Reset password modal -->
                    <div id="modal-reset-<?= $u['id'] ?>" uk-modal>
                        <div class="uk-modal-dialog uk-modal-body">
                            <button class="uk-modal-close-default" type="button" uk-close></button>
                            <h3 class="uk-modal-title" style="font-size:1rem"><?= __('user_reset_password') ?></h3>
                            <p style="font-size:.85rem;color:#9a94b8;margin-bottom:12px">
                                <?= htmlspecialchars($u['name']) ?>
                            </p>
                            <form method="post">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="_action" value="reset_password">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <div class="uk-margin">
                                    <label class="uk-form-label"><?= __('user_new_password') ?></label>
                                    <input class="uk-input" type="password" name="password" required minlength="8">
                                </div>
                                <div class="uk-flex uk-margin-top" style="gap:8px">
                                    <button class="uk-button uk-button-primary"><?= __('save') ?></button>
                                    <button type="button" class="uk-button uk-button-default uk-modal-close"><?= __('cancel') ?></button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif ?>
                </td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>

<!-- Create user modal -->
<div id="modal-user-create" uk-modal>
    <div class="uk-modal-dialog uk-modal-body">
        <button class="uk-modal-close-default" type="button" uk-close></button>
        <h3 class="uk-modal-title" style="font-size:1rem"><?= __('user_create') ?></h3>
        <form method="post">
            <?= Csrf::field() ?>
            <input type="hidden" name="_action" value="create">
            <input type="hidden" name="request_id" id="f-request-id" value="">
            <div class="uk-margin">
                <label class="uk-form-label"><?= __('user_name') ?></label>
                <input class="uk-input" type="text" name="name" id="f-user-name" required>
            </div>
            <div class="uk-margin">
                <label class="uk-form-label"><?= __('user_email') ?></label>
                <input class="uk-input" type="email" name="email" id="f-user-email" required>
            </div>
            <div class="uk-margin">
                <label class="uk-form-label"><?= __('user_new_password') ?></label>
                <input class="uk-input" type="password" name="password" required minlength="8">
            </div>
            <div class="uk-margin">
                <label class="uk-form-label"><?= __('user_role') ?></label>
                <select class="uk-select" name="role" required>
                    <option value="event_manager"><?= __('role_event_manager') ?></option>
                    <option value="results_reader"><?= __('role_results_reader') ?></option>
                </select>
            </div>
            <div class="uk-flex uk-margin-top" style="gap:8px">
                <button class="uk-button uk-button-primary"><?= __('save') ?></button>
                <button type="button" class="uk-button uk-button-default uk-modal-close"><?= __('cancel') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function euPrefill(name, email, requestId) {
    document.getElementById('f-user-name').value = name || '';
    document.getElementById('f-user-email').value = email || '';
    document.getElementById('f-request-id').value = requestId || '';
}
</script>

<?php
$content = ob_get_clean();
require ROOT . '/templates/admin/layout.php';
