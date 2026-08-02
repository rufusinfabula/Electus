<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Electus\Core\Auth;
use Electus\Core\CatTerm;
use Electus\Core\Database;
use Electus\Core\Flash;
use Electus\Models\Event;
use Electus\Models\Results;
use Electus\Models\Round;

$eventId = (int) ($_GET['event_id'] ?? 0);
if (!$eventId) { header('Location: /admin/events.php'); exit; }

$event = Event::find($eventId);
if (!$event) { Flash::error('Voting session not found.'); header('Location: /admin/events.php'); exit; }

Auth::requireEventPermission($eventId);

$pdo    = Database::get();
$rounds = Round::forEvent($eventId);

// Resolve selected round: explicit GET param, else active round, else last round
$roundId = (int) ($_GET['round_id'] ?? 0);
$round   = null;
foreach ($rounds as $r) {
    if ((int) $r['id'] === $roundId) { $round = $r; break; }
}
if (!$round && !empty($rounds)) {
    foreach ($rounds as $r) {
        if ($r['status'] === 'active') { $round = $r; break; }
    }
    if (!$round) $round = end($rounds);
    $roundId = (int) $round['id'];
}

// KPIs for the selected round
$totalVotes       = 0;
$uniqueCandidates = 0;
$avgMinutes       = null;

if ($roundId) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM votes WHERE round_id = ?');
    $stmt->execute([$roundId]);
    $totalVotes = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM candidates WHERE round_id = ? AND status = 'active'");
    $stmt->execute([$roundId]);
    $uniqueCandidates = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT AVG(TIMESTAMPDIFF(MINUTE, er.opens_at, v.voted_at))
         FROM votes v
         JOIN event_rounds er ON er.id = v.round_id
         WHERE v.round_id = ? AND er.opens_at IS NOT NULL'
    );
    $stmt->execute([$roundId]);
    $avgMinutesRaw = $stmt->fetchColumn();
    $avgMinutes    = $avgMinutesRaw !== null ? (float) $avgMinutesRaw : null;
}

// Participation rate — registered voters vs. voters who actually used their token
$stmt = $pdo->prepare('SELECT COUNT(*) FROM voter_lists WHERE event_id = ?');
$stmt->execute([$eventId]);
$registeredVoters = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM voter_lists WHERE event_id = ? AND token_used = 1');
$stmt->execute([$eventId]);
$votedVoters = (int) $stmt->fetchColumn();

$participationRate = $registeredVoters > 0 ? round($votedVoters / $registeredVoters * 100, 1) : null;

// Votes-over-time series (hourly if the round spans <=48h, daily otherwise)
$timeSeries = [];
if ($roundId) {
    $opensTs   = $round['opens_at']  ? strtotime($round['opens_at'])  : null;
    $closesTs  = $round['closes_at'] ? strtotime($round['closes_at']) : time();
    $spanHours = $opensTs ? ($closesTs - $opensTs) / 3600 : 999;
    $format    = ($spanHours > 0 && $spanHours <= 48) ? '%Y-%m-%d %H:00:00' : '%Y-%m-%d';

    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(voted_at, '{$format}') AS slot, COUNT(*) AS cnt
         FROM votes WHERE round_id = ? GROUP BY slot ORDER BY slot"
    );
    $stmt->execute([$roundId]);

    $cumulative = 0;
    foreach ($stmt->fetchAll() as $r) {
        $cumulative  += (int) $r['cnt'];
        $timeSeries[] = ['slot' => $r['slot'], 'cnt' => $cumulative];
    }
}

// Distribution by category — reuse the same computed snapshot as results.php
$resultsByCategory = $roundId ? Results::forRound($roundId) : [];

// Top 5 performers across every round of this event
$stmt = $pdo->prepare(
    'SELECT r.rank, r.total_points, r.total_votes, c.name AS candidate_name,
            cat.name AS category_name, er.round_number, er.label AS round_label
     FROM results r
     JOIN candidates c    ON c.id  = r.candidate_id
     JOIN categories cat  ON cat.id = r.category_id
     JOIN event_rounds er ON er.id = r.round_id
     WHERE er.event_id = ?
     ORDER BY r.total_points DESC
     LIMIT 5'
);
$stmt->execute([$eventId]);
$topPerformers = $stmt->fetchAll();

$catTermS       = CatTerm::label($event, 's');
$catTermP       = CatTerm::label($event, 'p');
$pageTitle      = htmlspecialchars($event['name']);
$activeMenu     = 'analytics';
$currentEventId = $eventId;

ob_start();
?>

<!-- Header -->
<div class="uk-flex uk-flex-between uk-flex-middle uk-margin-bottom" style="flex-wrap:wrap;gap:12px">
    <div class="uk-flex uk-flex-middle" style="gap:12px">
        <a href="/admin/rounds.php?event_id=<?= $eventId ?>" uk-icon="arrow-left" class="uk-icon-link"></a>
        <div>
            <h1 class="e-page-title uk-margin-remove"><?= __('analytics_title') ?></h1>
            <p style="margin:2px 0 0;font-size:.8rem;color:#9a94b8"><?= htmlspecialchars($event['name']) ?></p>
        </div>
    </div>
    <?php if (!empty($rounds)): ?>
    <form method="get" class="uk-flex uk-flex-middle" style="gap:8px">
        <input type="hidden" name="event_id" value="<?= $eventId ?>">
        <select name="round_id" class="uk-select uk-form-small" style="width:auto" onchange="this.form.submit()">
            <?php foreach ($rounds as $r): ?>
            <option value="<?= $r['id'] ?>" <?= $roundId === (int) $r['id'] ? 'selected' : '' ?>>
                <?= __('round_number') ?><?= $r['round_number'] ?><?= $r['label'] ? ' – ' . htmlspecialchars($r['label']) : '' ?>
            </option>
            <?php endforeach ?>
        </select>
    </form>
    <?php endif ?>
</div>

<?php if (empty($rounds)): ?>
<div class="e-card uk-text-center" style="padding:60px">
    <span uk-icon="icon:info;ratio:2" style="color:#c8c3e0"></span>
    <p style="color:#9a94b8;margin-top:12px"><?= __('analytics_no_data') ?></p>
</div>
<?php else: ?>

<!-- KPI row -->
<div class="uk-grid-small uk-margin-bottom" uk-grid>
    <div class="uk-width-auto">
        <div class="e-stat" style="min-width:110px">
            <div class="e-stat-value" style="color:var(--e-primary)"><?= $totalVotes ?></div>
            <div class="e-stat-label"><?= __('total_votes') ?></div>
        </div>
    </div>
    <div class="uk-width-auto">
        <div class="e-stat" style="min-width:110px">
            <div class="e-stat-value"><?= $participationRate !== null ? $participationRate . '%' : '—' ?></div>
            <div class="e-stat-label"><?= __('analytics_participation_rate') ?></div>
        </div>
    </div>
    <div class="uk-width-auto">
        <div class="e-stat" style="min-width:110px">
            <div class="e-stat-value" style="color:var(--e-accent)"><?= $uniqueCandidates ?></div>
            <div class="e-stat-label"><?= __('stat_unique_candidates') ?></div>
        </div>
    </div>
    <div class="uk-width-auto">
        <div class="e-stat" style="min-width:110px">
            <div class="e-stat-value">
                <?php if ($avgMinutes === null): ?>
                —
                <?php else:
                    $m = (int) round($avgMinutes);
                    echo $m < 60 ? $m . ' min' : intdiv($m, 60) . 'h ' . ($m % 60) . 'min';
                endif ?>
            </div>
            <div class="e-stat-label"><?= __('analytics_avg_time_to_vote') ?></div>
        </div>
    </div>
</div>

<!-- Votes over time -->
<div class="e-card uk-margin-bottom" style="padding:16px">
    <h3 style="font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--e-accent);margin:0 0 12px"><?= __('analytics_votes_over_time') ?></h3>
    <?php if (empty($timeSeries)): ?>
    <p style="color:#9a94b8;text-align:center;padding:30px 0;margin:0"><?= __('analytics_no_data') ?></p>
    <?php else: ?>
    <canvas id="chart-votes-over-time" style="max-height:300px"></canvas>
    <script>
    (function(){
        var ctx = document.getElementById('chart-votes-over-time');
        if (!ctx) return;
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($timeSeries, 'slot')) ?>,
                datasets: [{
                    label: '<?= addslashes(__('analytics_votes_over_time')) ?>',
                    data: <?= json_encode(array_column($timeSeries, 'cnt')) ?>,
                    borderColor: '#2f6ad9',
                    backgroundColor: 'rgba(47,106,217,.1)',
                    fill: true,
                    tension: .3,
                    pointRadius: 2
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                    y: { beginAtZero: true, grid: { color: '#f0eeff' } }
                }
            }
        });
    })();
    </script>
    <?php endif ?>
</div>

<!-- Distribution by category -->
<?php if (!empty($resultsByCategory)): ?>
<?php foreach ($resultsByCategory as $catId => $rows):
    if (count($rows) > 25) continue;
    $catName = $rows[0]['category_name'];
    $chartId = 'chart-dist-' . $catId;
    $labels  = array_map(fn($r) => $r['candidate_name'], $rows);
    $values  = array_map(fn($r) => (float) $r['total_points'], $rows);
?>
<div class="e-card uk-margin-bottom" style="padding:16px">
    <h3 style="font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--e-accent);margin:0 0 12px">
        <?= htmlspecialchars($catName) ?>
    </h3>
    <canvas id="<?= $chartId ?>" style="max-height:300px"></canvas>
    <script>
    (function(){
        var ctx = document.getElementById('<?= $chartId ?>');
        if (!ctx) return;
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>,
                datasets: [{
                    label: '<?= addslashes(__('total_points')) ?>',
                    data: <?= json_encode($values) ?>,
                    backgroundColor: <?= json_encode(array_map(fn($r) => $r['rank'] == 1 ? '#27ae60' : '#7B68EE', $rows)) ?>,
                    borderRadius: 6
                }]
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
    })();
    </script>
</div>
<?php endforeach ?>
<?php endif ?>

<!-- Top performers -->
<div class="e-card" style="padding:16px">
    <h3 style="font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--e-accent);margin:0 0 12px"><?= __('analytics_top_performers') ?></h3>
    <?php if (empty($topPerformers)): ?>
    <p style="color:#9a94b8;text-align:center;padding:30px 0;margin:0"><?= __('analytics_no_data') ?></p>
    <?php else: ?>
    <div class="e-table">
        <table class="uk-table uk-table-hover uk-table-divider uk-table-small uk-margin-remove">
            <thead>
                <tr>
                    <th style="width:36px">#</th>
                    <th><?= __('candidate_col') ?></th>
                    <th><?= $catTermS ?></th>
                    <th><?= __('round_number') ?></th>
                    <th style="text-align:right;white-space:nowrap"><?= __('total_points') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($topPerformers as $tp): ?>
            <tr>
                <td><span style="color:#9a94b8"><?= $tp['rank'] ?></span></td>
                <td><?= htmlspecialchars($tp['candidate_name']) ?></td>
                <td style="color:#6b6494"><?= htmlspecialchars($tp['category_name']) ?></td>
                <td style="color:#6b6494"><?= __('round_number') ?><?= $tp['round_number'] ?><?= $tp['round_label'] ? ' – ' . htmlspecialchars($tp['round_label']) : '' ?></td>
                <td style="text-align:right;white-space:nowrap;color:var(--e-primary);font-weight:600"><?= $tp['total_points'] ?></td>
            </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?php endif ?>
</div>

<?php endif ?>

<?php
$content = ob_get_clean();
require ROOT . '/templates/admin/layout.php';
