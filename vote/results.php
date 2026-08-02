<?php

declare(strict_types=1);

define('ROOT', dirname(__DIR__));
require ROOT . '/vendor/autoload.php';

use Electus\Core\CatTerm;
use Electus\Core\Database;
use Electus\Core\Lang;
use Electus\Core\Theme;
use Electus\Models\Event;
use Electus\Models\Results;
use Electus\Models\Round;

// Config guard
if (!file_exists(ROOT . '/config/config.php')) {
    header('Location: /install/');
    exit;
}
$cfg = require ROOT . '/config/config.php';

// Language
session_start();
$lang = $_GET['lang'] ?? $_SESSION['pub_lang'] ?? 'it';
if (!in_array($lang, Lang::available(), true)) $lang = 'it';
$_SESSION['pub_lang'] = $lang;
Lang::init($lang);

// Resolve event
$slug  = $_GET['slug'] ?? '';
$event = $slug ? Event::findBySlug($slug) : null;
if (!$event) {
    http_response_code(404);
    exit('Event not found.');
}

$eventId = (int) $event['id'];
$palette = Theme::forEvent($event, $cfg);

// Resolve round
$roundId = (int) ($_GET['round_id'] ?? 0);
if ($roundId) {
    $round = Round::find($roundId);
    if (!$round || (int) $round['event_id'] !== $eventId) {
        http_response_code(404);
        exit('Round not found.');
    }
} else {
    $pdo  = Database::get();
    $stmt = $pdo->prepare(
        "SELECT * FROM event_rounds
         WHERE event_id = ? AND results_released = 1
         ORDER BY round_number LIMIT 1"
    );
    $stmt->execute([$eventId]);
    $row = $stmt->fetch();
    if ($row && $row['config']) $row['config'] = json_decode($row['config'], true) ?? [];
    $round = $row ?: null;
}

// Gate: results must be explicitly released
if (!$round || !$round['results_released']) {
    $canVoteNow = $round && $round['status'] === 'active';
    ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?= htmlspecialchars($event['name']) ?> — <?= __('results_title') ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/electus-32.png">
    <link rel="icon" type="image/png" sizes="64x64" href="/icons/electus-64.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/electus-180.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/uikit@3/dist/css/uikit.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    <?php echo Theme::cssBlock($palette); ?>
</head>
<body class="e-public-body">
<header class="e-public-header">
    <div class="uk-container">
        <a class="e-public-logo" href="/vote/event.php?slug=<?= urlencode($slug) ?>">
            <span class="e-logo-mark">E</span>
            <span class="e-logo-text" style="color:#fff"><?= htmlspecialchars($event['name']) ?></span>
        </a>
    </div>
</header>
<main class="e-public-main">
    <div class="uk-container uk-container-small">
        <div class="e-results-gate">
            <?php if (!empty($event['public_logo_url'])): ?>
            <div class="e-results-hero-logo" style="margin:0 auto 20px;background:var(--e-bg)">
                <img src="<?= htmlspecialchars($event['public_logo_url']) ?>" alt="<?= htmlspecialchars($event['name']) ?>">
            </div>
            <?php else: ?>
            <div class="e-results-gate-icon">
                <span uk-icon="icon:clock;ratio:1.6"></span>
            </div>
            <?php endif ?>
            <h2 style="color:var(--e-text);margin:0 0 8px;font-size:1.3rem;font-weight:800">
                <?= htmlspecialchars($event['name']) ?>
            </h2>
            <p style="color:#9a94b8;margin-bottom:4px"><?= __('results_title') ?></p>
            <p style="color:#9a94b8"><?= __('results_not_computed') ?></p>
            <?php if ($canVoteNow): ?>
            <p style="color:var(--e-accent);font-size:.875rem;margin-top:16px"><?= __('results_not_available_hint') ?></p>
            <a href="/vote/event.php?slug=<?= urlencode($slug) ?>" class="uk-button uk-button-primary uk-margin-top">
                <?= __('vote_proceed') ?> &rarr;
            </a>
            <?php endif ?>
        </div>
    </div>
</main>
<footer class="e-public-footer">
    <div class="uk-container">
        Powered by <a href="https://electus.spazioumano.org" target="_blank" rel="noopener">Electus</a>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/uikit@3/dist/js/uikit.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/uikit@3/dist/js/uikit-icons.min.js"></script>
</body>
</html>
    <?php
    exit;
}

// Load data
$resultsByCategory = Results::forRound((int) $round['id']);
$voterCount        = Results::voterCount((int) $round['id']);

$pdo  = Database::get();
$stmt = $pdo->prepare(
    "SELECT id, round_number, label FROM event_rounds
     WHERE event_id = ? AND results_released = 1
     ORDER BY round_number"
);
$stmt->execute([$eventId]);
$releasedRounds = $stmt->fetchAll();

// Per-category advancement mode (for the winner/promoted badge)
$stmt = $pdo->prepare(
    'SELECT category_id, advancement_mode FROM round_category_map WHERE round_id = ?'
);
$stmt->execute([(int) $round['id']]);
$advancementByCategory = [];
foreach ($stmt->fetchAll() as $r) {
    $advancementByCategory[(int) $r['category_id']] = $r['advancement_mode'];
}

$catTermP = CatTerm::label($event, 'p');

// Collect chart init scripts — emitted after Chart.js CDN loads
$chartInits = '';

$rankColors = [
    1 => '#D4AF37',
    2 => '#9aa2ad',
    3 => '#b9743f',
];
$defaultBarColor = $palette['primary'];

?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?= htmlspecialchars($event['name']) ?> — <?= __('results_title') ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/electus-32.png">
    <link rel="icon" type="image/png" sizes="64x64" href="/icons/electus-64.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/electus-180.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/uikit@3/dist/css/uikit.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    <?php echo Theme::cssBlock($palette); ?>
</head>
<body class="e-public-body">

<header class="e-public-header">
    <div class="uk-container">
        <a class="e-public-logo" href="/vote/event.php?slug=<?= urlencode($slug) ?>">
            <span class="e-logo-mark">E</span>
            <span class="e-logo-text" style="color:#fff"><?= htmlspecialchars($event['name']) ?></span>
        </a>
        <div class="e-lang-switcher">
            <?php foreach (Lang::available() as $l): ?>
            <a href="?slug=<?= urlencode($slug) ?>&round_id=<?= $round['id'] ?>&lang=<?= $l ?>"
               class="<?= $l === $lang ? 'e-lang-active' : '' ?>">
                <?= strtoupper($l) ?>
            </a>
            <?php endforeach ?>
        </div>
    </div>
</header>

<div class="e-results-hero">
    <div class="uk-container uk-container-large">
        <div class="e-results-hero-inner">
            <?php if (!empty($event['public_logo_url'])): ?>
            <div class="e-results-hero-logo">
                <img src="<?= htmlspecialchars($event['public_logo_url']) ?>" alt="<?= htmlspecialchars($event['name']) ?>">
            </div>
            <?php else: ?>
            <div class="e-results-hero-logo">
                <span class="e-logo-mark" style="color:var(--e-primary)">E</span>
            </div>
            <?php endif ?>
            <div>
                <div class="e-results-hero-eyebrow"><?= __('results_title') ?></div>
                <h1 class="e-results-hero-title"><?= htmlspecialchars($event['name']) ?></h1>
                <div class="e-results-hero-meta">
                    <span class="e-badge"><?= __('round_number') ?><?= $round['round_number'] ?><?= $round['label'] ? ' — ' . htmlspecialchars($round['label']) : '' ?></span>
                    <span class="e-badge"><?= count($resultsByCategory) ?> <?= htmlspecialchars($catTermP) ?></span>
                    <span class="e-badge"><?= $voterCount ?> <?= __('stat_voters') ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<main class="e-public-main" style="padding-top:0">
    <div class="uk-container uk-container-large">

        <?php if (count($releasedRounds) > 1): ?>
        <nav class="e-round-tabs">
            <?php foreach ($releasedRounds as $rr): ?>
            <a href="?slug=<?= urlencode($slug) ?>&round_id=<?= $rr['id'] ?>"
               class="<?= (int) $rr['id'] === (int) $round['id'] ? 'e-round-tab-active' : '' ?>">
                <?= __('round_number') ?><?= $rr['round_number'] ?>
                <?= $rr['label'] ? '— ' . htmlspecialchars($rr['label']) : '' ?>
            </a>
            <?php endforeach ?>
        </nav>
        <?php endif ?>

        <?php if (empty($resultsByCategory)): ?>
        <div class="e-vote-box uk-text-center" style="padding:60px;margin-top:24px">
            <p style="color:#9a94b8"><?= __('results_not_computed') ?></p>
        </div>
        <?php endif ?>

        <?php foreach ($resultsByCategory as $catId => $rows):
            $catName    = $rows[0]['category_name'];
            $chartBarId = 'chart-bar-' . $catId;
            $labels     = array_map(fn($r) => $r['candidate_name'], $rows);
            $values     = array_map(fn($r) => (float) $r['total_points'], $rows);
            $bgColors   = array_map(fn($r) => $rankColors[(int) $r['rank']] ?? $defaultBarColor, $rows);

            $mode       = $advancementByCategory[(int) $catId] ?? 'none';
            $isPromoted = $mode !== 'none';
            $badgeLabel = $isPromoted ? __('results_promoted_badge') : __('results_winner_badge');
            $badgeClass = $isPromoted ? 'e-badge-promoted' : 'e-badge-winner';

            $labelsJson  = json_encode($labels, JSON_UNESCAPED_UNICODE);
            $valuesJson  = json_encode($values);
            $colorsJson  = json_encode($bgColors);
            $pointsLabel = addslashes(__('total_points'));

            $chartInits .= <<<JS
(function(){
    var barCtx = document.getElementById('{$chartBarId}');
    if (barCtx) {
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: {$labelsJson},
                datasets: [{ label: '{$pointsLabel}', data: {$valuesJson}, backgroundColor: {$colorsJson}, borderRadius: 6 }]
            },
            options: {
                indexAxis: 'y', responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: '#f0eeff' } },
                    y: { grid: { display: false }, ticks: { font: { size: 11 } } }
                }
            }
        });
    }
})();

JS;

            $podium = array_slice($rows, 0, 3);
        ?>
        <div class="e-results-card">
            <h2 class="e-results-card-title"><?= htmlspecialchars($catName) ?></h2>

            <?php if (count($podium) > 1): ?>
            <div class="e-podium">
                <?php foreach ($podium as $p): ?>
                <div class="e-podium-item">
                    <div class="e-podium-avatar"><?= (int) $p['rank'] ?></div>
                    <div class="e-podium-name"><?= htmlspecialchars($p['candidate_name']) ?></div>
                    <div class="e-podium-points"><?= number_format((float) $p['total_points'], 0) ?> <?= __('total_points') ?></div>
                    <div class="e-podium-step"></div>
                </div>
                <?php endforeach ?>
            </div>
            <?php endif ?>

            <div class="uk-grid-medium" uk-grid>
                <div class="uk-width-1-2@m">
                    <div class="e-table e-results-table-wrap">
                        <table class="uk-table uk-table-hover uk-table-divider uk-margin-remove">
                            <thead>
                                <tr>
                                    <th style="width:44px">#</th>
                                    <th><?= __('candidate_name') ?></th>
                                    <th style="text-align:right"><?= __('total_points') ?></th>
                                    <th style="text-align:right"><?= __('total_votes') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $r): ?>
                            <tr <?= $r['rank'] == 1 ? 'class="e-results-rank-1"' : '' ?>>
                                <td style="font-weight:<?= $r['rank'] == 1 ? '800' : '400' ?>;color:<?= $r['rank'] == 1 ? '#8a6d1a' : '#9a94b8' ?>">
                                    <?= (int) $r['rank'] ?>
                                </td>
                                <td style="font-weight:<?= $r['rank'] == 1 ? '700' : '400' ?>">
                                    <?= htmlspecialchars($r['candidate_name']) ?>
                                    <?php if ($r['rank'] == 1): ?>
                                    <span class="<?= $badgeClass ?>"><?= htmlspecialchars($badgeLabel) ?></span>
                                    <?php endif ?>
                                </td>
                                <td style="text-align:right;color:var(--e-primary);font-weight:600">
                                    <?= number_format((float) $r['total_points'], 0) ?>
                                </td>
                                <td style="text-align:right;color:#9a94b8">
                                    <?= number_format((float) $r['total_votes'], 0) ?>
                                </td>
                            </tr>
                            <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="uk-width-1-2@m">
                    <div class="e-vote-box" style="padding:20px">
                        <canvas id="<?= $chartBarId ?>" style="max-height:280px"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach ?>

    </div>
</main>

<footer class="e-public-footer">
    <div class="uk-container">
        Powered by <a href="https://electus.spazioumano.org" target="_blank" rel="noopener">Electus</a>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/uikit@3/dist/js/uikit.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/uikit@3/dist/js/uikit-icons.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<?php if ($chartInits): ?>
<script><?= $chartInits ?></script>
<?php endif ?>
</body>
</html>
