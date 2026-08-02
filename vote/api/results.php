<?php

declare(strict_types=1);

/**
 * Endpoint pubblico JSON per i risultati di un turno di votazione.
 *
 * URL:    /vote/api/results.php?slug={event_slug}&round_id={round_id}
 * Metodo: GET — nessuna autenticazione, nessuna sessione, nessun CSRF.
 *
 * Parametri:
 *   slug      (string, richiesto) — slug dell'evento
 *   round_id  (int, richiesto)    — id del turno
 *
 * Risposte:
 *   200 { event: {name, slug}, round: {id, round_number, label,
 *         results_released, computed_at}, categories: [{id, name,
 *         candidates: [{rank, name, total_votes, total_points}]}] }
 *   403 { error: "results_not_released" } — risultati non ancora pubblicati
 *   404 { error: "not_found" }            — evento o turno inesistenti
 */

define('ROOT', dirname(__DIR__, 2));
require ROOT . '/vendor/autoload.php';

use Electus\Models\Event;
use Electus\Models\Results;
use Electus\Models\Round;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=60');

function apiError(int $status, string $error): never
{
    http_response_code($status);
    echo json_encode(['error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

$slug    = $_GET['slug'] ?? '';
$roundId = (int) ($_GET['round_id'] ?? 0);

$event = $slug !== '' ? Event::findBySlug($slug) : null;
if (!$event) {
    apiError(404, 'not_found');
}

$round = $roundId ? Round::find($roundId) : null;
if (!$round || (int) $round['event_id'] !== (int) $event['id']) {
    apiError(404, 'not_found');
}

if (!$round['results_released']) {
    apiError(403, 'results_not_released');
}

$resultsByCategory = Results::forRound((int) $round['id']);

$computedAt = null;
$categories = [];
foreach ($resultsByCategory as $catId => $rows) {
    $candidates = [];
    foreach ($rows as $r) {
        $candidates[] = [
            'rank'         => (int) $r['rank'],
            'name'         => $r['candidate_name'],
            'total_votes'  => (int) $r['total_votes'],
            'total_points' => (int) $r['total_points'],
        ];
        if ($computedAt === null) {
            $computedAt = $r['computed_at'];
        }
    }
    $categories[] = [
        'id'         => (int) $catId,
        'name'       => $rows[0]['category_name'],
        'candidates' => $candidates,
    ];
}

echo json_encode([
    'event' => [
        'name' => $event['name'],
        'slug' => $event['slug'],
    ],
    'round' => [
        'id'               => (int) $round['id'],
        'round_number'     => (int) $round['round_number'],
        'label'            => $round['label'],
        'results_released' => (bool) $round['results_released'],
        'computed_at'      => $computedAt !== null ? str_replace(' ', 'T', $computedAt) : null,
    ],
    'categories' => $categories,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
