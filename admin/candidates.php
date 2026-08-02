<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Electus\Core\Auth;
use Electus\Core\CatTerm;
use Electus\Core\Csrf;
use Electus\Core\Flash;
use Electus\Models\Candidate;
use Electus\Models\Category;
use Electus\Models\Deduplication;
use Electus\Models\Event;
use Electus\Models\Round;

$roundId = (int) ($_GET['round_id'] ?? 0);
$round   = $roundId ? Round::find($roundId) : null;
if (!$round) { Flash::error('Round not found.'); header('Location: /admin/events.php'); exit; }

$eventId = (int) $round['event_id'];
$event   = Event::find($eventId);
Auth::requireEventPermission($eventId);

$categories = Category::forRound($roundId);

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action = $_POST['_action'] ?? '';

    if ($action === 'create') {
        $catId = (int) ($_POST['category_id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        if ($catId && $name) {
            Candidate::create($roundId, $catId, $name, 'manual');
            Flash::success(__('candidate_saved'));
        }
    }

    if ($action === 'update') {
        $candId = (int) ($_POST['id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        if ($candId && $name) {
            Candidate::update($candId, $name);
            Flash::success(__('candidate_saved'));
        }
    }

    if ($action === 'delete') {
        $candId = (int) ($_POST['id'] ?? 0);
        if ($candId) {
            Candidate::delete($candId);
            Flash::success(__('candidate_deleted'));
        }
    }

    if ($action === 'exclude') {
        $candId = (int) ($_POST['id'] ?? 0);
        if ($candId) {
            Candidate::setStatus($candId, 'excluded');
            Flash::success(__('candidate_excluded'));
        }
    }

    if ($action === 'restore') {
        $candId = (int) ($_POST['id'] ?? 0);
        if ($candId) {
            Candidate::setStatus($candId, 'active');
            Flash::success(__('candidate_restored'));
        }
    }

    if ($action === 'manual_merge') {
        $sourceId = (int) ($_POST['source_id'] ?? 0);
        $targetId = (int) ($_POST['target_id'] ?? 0);
        $override = trim($_POST['canonical_override'] ?? '');
        if ($sourceId && $targetId && $sourceId !== $targetId) {
            Deduplication::manualMerge($sourceId, $targetId, $roundId, (int) Auth::currentUser()['id'], $override);
            Flash::success(__('candidates_merged'));
        }
    }

    if ($action === 'batch_exclude') {
        $ids = array_map('intval', array_filter($_POST['cids'] ?? []));
        foreach ($ids as $cid) { Candidate::setStatus($cid, 'excluded'); }
        if ($ids) Flash::success(count($ids) . ' ' . __('candidate_excluded'));
    }

    if ($action === 'batch_delete') {
        $ids = array_map('intval', array_filter($_POST['cids'] ?? []));
        foreach ($ids as $cid) { Candidate::delete($cid); }
        if ($ids) Flash::success(count($ids) . ' ' . __('candidate_deleted'));
    }

    if ($action === 'batch_merge') {
        $ids      = array_map('intval', array_filter($_POST['cids'] ?? []));
        $override = trim($_POST['canonical_override'] ?? '');
        $user     = Auth::currentUser();
        if (count($ids) >= 2) {
            Deduplication::mergeManual($ids, (int) $user['id'], $override);
            Flash::success(__('candidates_merged'));
        }
    }

    header('Location: /admin/candidates.php?round_id=' . $roundId);
    exit;
}

$candidates     = Candidate::forRound($roundId, 'active');
$excluded       = Candidate::forRound($roundId, 'excluded');
$merged         = Candidate::forRound($roundId, 'merged');
$isOpen         = $round['model'] === 'open';

$catTermS       = CatTerm::label($event, 's');
$catTermP       = CatTerm::label($event, 'p');
$pageTitle      = __('candidates_title');
$activeMenu     = 'candidates';
$currentEventId = $eventId;
$currentRoundId = $roundId;

// Group candidates by category
$byCategory = [];
foreach ($candidates as $c) {
    $byCategory[$c['category_id']][] = $c;
}

ob_start();
?>
<div class="uk-flex uk-flex-between uk-flex-middle uk-margin-bottom">
    <div class="uk-flex uk-flex-middle" style="gap:12px">
        <a href="/admin/rounds.php?event_id=<?= $eventId ?>" uk-icon="arrow-left" class="uk-icon-link"></a>
        <div>
            <p style="margin:0;font-size:.8rem;color:#9a94b8">
                <?= htmlspecialchars($event['name']) ?> —
                <?= __('round_number') ?><?= $round['round_number'] ?>
                <?= $round['label'] ? '— ' . htmlspecialchars($round['label']) : '' ?>
                <span class="e-badge e-badge-<?= $round['status'] ?>" style="margin-left:6px">
                    <?= __('model_' . $round['model']) ?>
                </span>
            </p>
            <h1 class="e-page-title uk-margin-remove"><?= __('candidates_title') ?></h1>
        </div>
    </div>
    <?php if ($round['model'] === 'open'): ?>
    <a href="/admin/results.php?round_id=<?= $roundId ?>&tab=review" class="uk-button uk-button-default uk-button-small">
        <?= __('dedup_title') ?>
    </a>
    <?php endif ?>
</div>

<?php if (empty($categories)): ?>
<div class="uk-alert-warning" uk-alert>
    <p>
        <?= str_replace(':cats', $catTermS, __('no_cats_warning')) ?>
        <a href="/admin/categories.php?event_id=<?= $eventId ?>">
            <?= str_replace(':catp', $catTermP, __('add_cats_first')) ?>
        </a>.
    </p>
</div>
<?php else: ?>

<div class="uk-grid-medium" uk-grid>

    <!-- Candidates by category -->
    <div class="uk-width-<?= $isOpen ? '1-1' : '2-3' ?>@m">

        <?php if ($isOpen): ?>
        <div class="uk-alert-primary" uk-alert>
            <p><?= str_replace(
                '<a>',
                '<a href="/admin/results.php?round_id=' . $roundId . '&tab=review">',
                __('open_round_info')
            ) ?></p>
        </div>
        <?php endif ?>

        <?php if (empty($candidates) && !$isOpen): ?>
        <div class="e-card uk-text-center" style="padding:40px">
            <p style="color:#9a94b8"><?= __('no_candidates_yet') ?></p>
        </div>
        <?php else: ?>

        <?php foreach ($categories as $cat): ?>
        <?php $catCandidates = $byCategory[$cat['id']] ?? []; $catCount = count($catCandidates); ?>
        <div class="e-card uk-margin-small-bottom">
            <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-bottom">
                <div class="uk-flex uk-flex-middle" style="gap:10px">
                    <input type="checkbox" class="uk-checkbox e-cat-check"
                           data-cat="<?= $cat['id'] ?>" onchange="eCatCheckAll(this)"
                           title="Seleziona tutti">
                    <h3 style="margin:0;font-size:.9rem;font-weight:700;color:var(--e-accent)">
                        <?= htmlspecialchars($cat['name']) ?>
                    </h3>
                </div>
                <span style="color:#9a94b8;font-size:.8rem"><?= str_replace(':count', (string)$catCount, __('candidates_count_label')) ?></span>
            </div>

            <?php if (empty($catCandidates)): ?>
            <p style="color:#c8c3e0;font-size:.875rem;margin:0">—</p>
            <?php else: ?>
            <table class="uk-table uk-table-small uk-table-divider uk-margin-remove" id="cat-table-<?= $cat['id'] ?>">
                <tbody>
                    <?php foreach ($catCandidates as $i => $cand): ?>
                    <tr class="<?= $i >= 10 ? 'e-cand-extra' : '' ?>"
                        style="<?= $i >= 10 ? 'display:none' : '' ?>"
                        data-cat="<?= $cat['id'] ?>">
                        <td style="width:28px">
                            <input type="checkbox" class="uk-checkbox e-cand-check"
                                   name="cids[]" value="<?= $cand['id'] ?>"
                                   data-cat="<?= $cat['id'] ?>"
                                   data-name="<?= htmlspecialchars($cand['name'], ENT_QUOTES) ?>"
                                   onchange="eUpdateBatch()">
                        </td>
                        <td>
                            <?= htmlspecialchars($cand['name']) ?>
                            <?php if ($cand['source'] !== 'manual'): ?>
                            <span class="e-badge" style="background:#f0eeff;color:#6b52d4;font-size:.7rem">
                                <?= $cand['source'] ?>
                            </span>
                            <?php endif ?>
                        </td>
                        <td style="width:<?= $isOpen ? '110' : '90' ?>px">
                            <div class="uk-flex" style="gap:6px">
                                <a href="#modal-cand-edit-<?= $cand['id'] ?>" uk-toggle
                                   uk-icon="icon:pencil;ratio:.8" class="uk-icon-link"></a>
                                <?php if ($isOpen && $catCount > 1): ?>
                                <a href="#modal-merge-<?= $cand['id'] ?>" uk-toggle
                                   uk-icon="icon:git-fork;ratio:.8"
                                   uk-tooltip="<?= __('merge_with') ?>"
                                   class="uk-icon-link" style="color:var(--e-primary)"></a>
                                <?php endif ?>
                                <form method="post" style="display:inline">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="_action" value="exclude">
                                    <input type="hidden" name="id" value="<?= $cand['id'] ?>">
                                    <button uk-icon="icon:ban;ratio:.8"
                                            uk-tooltip="<?= __('dedup_exclude') ?>"
                                            style="color:#e74c3c;background:none;border:none;cursor:pointer;padding:0"
                                            class="uk-icon-link"></button>
                                </form>
                                <?php if (!$isOpen): ?>
                                <form method="post" style="display:inline">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="_action" value="delete">
                                    <input type="hidden" name="id" value="<?= $cand['id'] ?>">
                                    <button uk-icon="icon:trash;ratio:.8"
                                            style="color:#e74c3c;background:none;border:none;cursor:pointer;padding:0"
                                            class="uk-icon-link"></button>
                                </form>
                                <?php endif ?>
                            </div>
                        </td>
                    </tr>

                    <div id="modal-cand-edit-<?= $cand['id'] ?>" uk-modal>
                        <div class="uk-modal-dialog uk-modal-body">
                            <h3 class="uk-modal-title"><?= __('edit') ?></h3>
                            <form method="post">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="_action" value="update">
                                <input type="hidden" name="id" value="<?= $cand['id'] ?>">
                                <input class="uk-input" type="text" name="name"
                                       value="<?= htmlspecialchars($cand['name']) ?>" required>
                                <div class="uk-flex uk-margin-top" style="gap:8px">
                                    <button class="uk-button uk-button-primary"><?= __('save') ?></button>
                                    <button class="uk-button uk-button-default uk-modal-close"><?= __('cancel') ?></button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if ($isOpen && $catCount > 1): ?>
                    <div id="modal-merge-<?= $cand['id'] ?>" uk-modal>
                        <div class="uk-modal-dialog uk-modal-body">
                            <button class="uk-modal-close-default" type="button" uk-close></button>
                            <h3 class="uk-modal-title" style="font-size:1rem"><?= __('merge_candidate_title') ?></h3>
                            <p style="font-size:.875rem;color:#9a94b8;margin-bottom:16px">
                                <?= str_replace(':name', htmlspecialchars($cand['name']), __('merge_intro')) ?>
                            </p>
                            <form method="post">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="_action" value="manual_merge">
                                <input type="hidden" name="source_id" value="<?= $cand['id'] ?>">
                                <div class="uk-margin">
                                    <label class="uk-form-label"><?= __('merge_into') ?></label>
                                    <select class="uk-select" name="target_id" required>
                                        <option value=""><?= __('select_placeholder') ?></option>
                                        <?php foreach ($catCandidates as $sibling):
                                            if ($sibling['id'] === $cand['id']) continue; ?>
                                        <option value="<?= $sibling['id'] ?>"><?= htmlspecialchars($sibling['name']) ?></option>
                                        <?php endforeach ?>
                                    </select>
                                </div>
                                <div class="uk-margin">
                                    <label class="uk-form-label"><?= __('canonical_override_label') ?></label>
                                    <input class="uk-input" type="text" name="canonical_override"
                                           placeholder="<?= htmlspecialchars(__('canonical_override_hint')) ?>">
                                </div>
                                <div class="uk-flex uk-margin-top" style="gap:8px">
                                    <button class="uk-button uk-button-primary"><?= __('dedup_merge') ?></button>
                                    <button type="button" class="uk-button uk-button-default uk-modal-close"><?= __('cancel') ?></button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif ?>

                    <?php endforeach ?>
                </tbody>
            </table>

            <?php if ($catCount > 10): ?>
            <button type="button" class="uk-button uk-button-link uk-button-small uk-margin-small-top e-show-more"
                    data-cat="<?= $cat['id'] ?>" data-total="<?= $catCount ?>"
                    onclick="eToggleExtra(this)">
                <span uk-icon="icon:chevron-down;ratio:.8"></span>
                Mostra altri <?= $catCount - 10 ?>
            </button>
            <?php endif ?>

            <?php endif ?>
        </div>
        <?php endforeach ?>

        <?php endif ?>
    </div>

    <!-- Add form (closed rounds only) -->
    <?php if (!$isOpen): ?>
    <div class="uk-width-1-3@m">
        <div class="e-card">
            <h3 style="font-size:1rem;font-weight:600;margin-bottom:16px"><?= __('candidate_new') ?></h3>
            <form method="post">
                <?= Csrf::field() ?>
                <input type="hidden" name="_action" value="create">
                <div class="uk-margin">
                    <label class="uk-form-label"><?= $catTermP ?></label>
                    <select class="uk-select" name="category_id" required>
                        <option value=""><?= __('select_placeholder') ?></option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="uk-margin">
                    <label class="uk-form-label"><?= __('candidate_name') ?></label>
                    <input class="uk-input" type="text" name="name" required>
                </div>
                <button class="uk-button uk-button-primary uk-width-1-1"><?= __('create') ?></button>
            </form>
        </div>
    </div>
    <?php endif ?>

</div>

<!-- Excluded / merged candidates -->
<?php if (!empty($excluded) || !empty($merged)): ?>
<details class="uk-margin-top" style="cursor:pointer">
    <summary style="color:#9a94b8;font-size:.85rem">
        <?= str_replace(':count', (string)(count($excluded) + count($merged)), __('merged_excluded_title')) ?>
    </summary>
    <div class="e-table uk-margin-small-top">
        <table class="uk-table uk-table-small uk-table-divider uk-margin-remove">
            <tbody>
                <?php foreach ($excluded as $cand): ?>
                <tr>
                    <td style="color:#9a94b8"><?= htmlspecialchars($cand['name']) ?></td>
                    <td style="color:#9a94b8"><?= htmlspecialchars($cand['category_name']) ?></td>
                    <td><span class="e-badge e-badge-closed"><?= __('status_excluded_label') ?></span></td>
                    <td>
                        <form method="post" style="display:inline">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="_action" value="restore">
                            <input type="hidden" name="id" value="<?= $cand['id'] ?>">
                            <button class="uk-button uk-button-link uk-button-small"><?= __('restore') ?></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach ?>
                <?php foreach ($merged as $cand): ?>
                <tr>
                    <td style="color:#9a94b8"><?= htmlspecialchars($cand['name']) ?></td>
                    <td style="color:#9a94b8"><?= htmlspecialchars($cand['category_name']) ?></td>
                    <td><span class="e-badge" style="background:#f0eeff;color:#6b52d4"><?= __('status_merged_label') ?></span></td>
                    <td style="color:#9a94b8;font-size:.8rem">—</td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</details>
<?php endif ?>

<?php endif ?>

<!-- Batch merge modal -->
<div id="modal-batch-merge" uk-modal>
    <div class="uk-modal-dialog uk-modal-body">
        <button class="uk-modal-close-default" type="button" uk-close></button>
        <h3 class="uk-modal-title" style="font-size:1rem">Unisci selezionati</h3>
        <p style="font-size:.875rem;color:#9a94b8;margin-bottom:12px">
            I candidati selezionati verranno uniti. Quello con più voti sarà il candidato principale.
        </p>
        <form method="post" id="form-batch-merge">
            <?= Csrf::field() ?>
            <input type="hidden" name="_action" value="batch_merge">
            <div id="batch-merge-cids"></div>
            <div class="uk-margin">
                <label class="uk-form-label">Nome canonico risultante</label>
                <input class="uk-input" type="text" name="canonical_override"
                       id="batch-merge-name-input"
                       list="batch-merge-namelist"
                       placeholder="Scegli dal menu o scrivi un nuovo nome..."
                       autocomplete="off">
                <datalist id="batch-merge-namelist"></datalist>
                <p style="font-size:.75rem;color:#9a94b8;margin:4px 0 0">
                    Il menu a tendina mostra i nomi selezionati — puoi scegliere uno o scrivere un nuovo nome da zero. Lascia vuoto per usare automaticamente il candidato con più voti.
                </p>
            </div>
            <div class="uk-flex uk-margin-top" style="gap:8px">
                <button class="uk-button uk-button-primary"><?= __('dedup_merge') ?></button>
                <button type="button" class="uk-button uk-button-default uk-modal-close"><?= __('cancel') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Sticky batch action bar -->
<div id="e-batch-bar"
     style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:1000;
            background:#fff;border-top:2px solid var(--e-primary);
            padding:12px 24px;box-shadow:0 -4px 16px rgba(76,61,158,.12)">
    <form method="post" id="form-batch-action" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <?= Csrf::field() ?>
        <input type="hidden" name="_action" id="batch-action-field" value="">
        <div id="batch-cids-container"></div>
        <span id="batch-count-label" style="font-size:.875rem;font-weight:600;color:var(--e-primary);min-width:80px"></span>
        <div style="display:flex;gap:8px;margin-left:auto">
            <button type="button" class="uk-button uk-button-default uk-button-small"
                    onclick="eSetBatchAction('batch_exclude')">
                <span uk-icon="icon:ban;ratio:.8"></span> Escludi
            </button>
            <?php if (!$isOpen): ?>
            <button type="button" class="uk-button uk-button-default uk-button-small"
                    style="color:#e74c3c;border-color:#e74c3c"
                    onclick="eSetBatchAction('batch_delete')">
                <span uk-icon="icon:trash;ratio:.8"></span> Elimina
            </button>
            <?php endif ?>
            <button type="button" id="btn-batch-merge"
                    class="uk-button uk-button-primary uk-button-small"
                    onclick="eOpenBatchMerge()">
                <span uk-icon="icon:git-fork;ratio:.8"></span> Unisci
            </button>
            <button type="button" class="uk-button uk-button-link uk-button-small"
                    style="color:#9a94b8" onclick="eDeselectAll()">Annulla</button>
        </div>
    </form>
</div>

<script>
function eUpdateBatch() {
    var checked = document.querySelectorAll('.e-cand-check:checked');
    var bar = document.getElementById('e-batch-bar');
    var container = document.getElementById('batch-cids-container');
    var mergeBtn = document.getElementById('btn-batch-merge');
    container.innerHTML = '';
    if (!checked.length) { bar.style.display = 'none'; return; }
    bar.style.display = '';
    document.getElementById('batch-count-label').textContent = checked.length + ' selezionat' + (checked.length === 1 ? 'o' : 'i');
    // Check all same category
    var cats = new Set(Array.from(checked).map(function(c){ return c.dataset.cat; }));
    mergeBtn.disabled = cats.size > 1;
    mergeBtn.title = cats.size > 1 ? 'Seleziona candidati della stessa categoria per unirli' : '';
    checked.forEach(function(c) {
        var h = document.createElement('input');
        h.type = 'hidden'; h.name = 'cids[]'; h.value = c.value;
        container.appendChild(h);
    });
}

function eSetBatchAction(action) {
    document.getElementById('batch-action-field').value = action;
    document.getElementById('form-batch-action').submit();
}

function eOpenBatchMerge() {
    var checked = document.querySelectorAll('.e-cand-check:checked');
    var dest = document.getElementById('batch-merge-cids');
    var dl   = document.getElementById('batch-merge-namelist');
    var inp  = document.getElementById('batch-merge-name-input');
    dest.innerHTML = '';
    dl.innerHTML   = '';
    inp.value      = '';
    checked.forEach(function(c) {
        var h = document.createElement('input');
        h.type = 'hidden'; h.name = 'cids[]'; h.value = c.value;
        dest.appendChild(h);
        var opt = document.createElement('option');
        opt.value = c.dataset.name;
        dl.appendChild(opt);
    });
    UIkit.modal('#modal-batch-merge').show();
}

function eCatCheckAll(master) {
    var cat = master.dataset.cat;
    document.querySelectorAll('.e-cand-check[data-cat="' + cat + '"]').forEach(function(c) {
        c.checked = master.checked;
    });
    eUpdateBatch();
}

function eDeselectAll() {
    document.querySelectorAll('.e-cand-check').forEach(function(c){ c.checked = false; });
    document.querySelectorAll('.e-cat-check').forEach(function(c){ c.checked = false; });
    eUpdateBatch();
}

function eToggleExtra(btn) {
    var cat = btn.dataset.cat;
    var total = parseInt(btn.dataset.total);
    var rows = document.querySelectorAll('tr.e-cand-extra[data-cat="' + cat + '"]');
    var open = btn.dataset.open === '1';
    rows.forEach(function(r){ r.style.display = open ? 'none' : ''; });
    btn.dataset.open = open ? '0' : '1';
    btn.innerHTML = open
        ? '<span uk-icon="icon:chevron-down;ratio:.8"></span> Mostra altri ' + (total - 10)
        : '<span uk-icon="icon:chevron-up;ratio:.8"></span> Mostra meno';
    UIkit.update();
}
</script>

<?php
$content = ob_get_clean();
require ROOT . '/templates/admin/layout.php';
