# Electus — Guida al progetto per Claude

## Panoramica

**Electus** è un sistema open source di gestione votazioni ed elezioni.
URL live: `https://electus.spazioumano.org`
Root: `/home/electus/web/electus.spazioumano.org/public_html`

## Stack tecnico

- **PHP 8.3** — `declare(strict_types=1)` ovunque, nessun framework MVC
- **PDO** raw per tutti gli accessi al database (MySQL/MariaDB)
- **Composer** — autoload PSR-4 (`Electus\` → `src/`), dipendenza: `phpmailer/phpmailer`
- **UIkit 3** CDN — tutta la UI admin è costruita su UIkit 3
- **Chart.js** — grafici a barre orizzontali nella pagina risultati (solo per categorie ≤25 candidati)
- **Quill** — editor rich text nella pagina events-edit
- **Pattern output**: `ob_start()` / `ob_get_clean()` in ogni pagina admin

## Struttura directory

```
admin/          pagine amministrative (bootstrap.php, ogni pagina .php)
assets/
  css/app.css   foglio stile globale admin + pubblico
  uploads/logos/ logo caricati via disk upload (max 4MB)
config/         config.php (non in repo — DB, URL app, mail)
lang/           it.php  en.php  fr.php   (~305 chiavi ciascuno)
src/
  Core/         Auth, CatTerm, Csrf, Database, Flash, Lang, Mailer, Theme
  Models/       Candidate, Category, Deduplication, Event, Results, Round, Settings, Vote, VoterList
  VoteModels/   BordaModel, MultipleModel, OpenModel, ProportionalModel, SingleModel, WeightedModel
  helpers.php   funzione globale __() per traduzioni
sql/
  schema.sql                  schema completo
  migration_001_pipeline.sql  aggiunge round_category_map
  migration_002_cat_term.sql  aggiunge events.cat_term
  migration_003_public_page.sql
vote/           pagine pubbliche (event.php, cast.php, confirm.php, verify.php, results.php)
templates/      layout admin condiviso
```

## Convenzioni critiche

### Nomi candidati
- `Candidate::sanitizeName($s)` — strisce `https?:?//(www.)?`, forza UPPERCASE. Applicato a TUTTI i punti di ingresso.
- `Candidate::normalize($s)` — lowercase, strip articoli iniziali, strip non-alfanumerici, collassa spazi. Usato per la chiave di dedup `canonical_name`.

### i18n
- Chiave `__('chiave')` risolve dalla lingua corrente (admin: `config['app']['admin_lang']`, pubblico: sessione)
- Tutte le chiavi devono essere aggiunte in `lang/it.php`, `lang/en.php`, `lang/fr.php`
- Usare sempre chiavi in snake_case (es. `stat_unique_candidates`)

### CatTerm
- Terminologia per le categorie di voto configurabile per evento (Categoria/Scelta/Premio/Seggio/ecc.)
- `CatTerm::label($event, 's')` → singolare, `CatTerm::label($event, 'p')` → plurale
- Preset: `categoria` (default), `scelta`, `carica`, `seggio`, `posto`, `premio`, `ruolo`, `proposta`, `incarico`

### OPcache
- Non si può pulire da CLI. Per forzare refresh dopo deploy: creare file PHP temporaneo, curlarlo, cancellarlo.

### CSRF
- `Csrf::check()` all'inizio di ogni POST handler

### Flash
- `Flash::success()`, `Flash::error()`, `Flash::warning()` — messaggi che sopravvivono al redirect

## Schema database (tabelle principali)

| Tabella | Ruolo |
|---|---|
| `events` | evento di voto (name, slug, status, cat_term, email_verification, results_released, ...) |
| `event_rounds` | turni di voto (event_id, round_number, label, status, votes_validated, results_released, promotion_confirmed) |
| `categories` | categorie per round (name, round_id) |
| `candidates` | candidati (name, canonical_name, round_id, category_id, status: active/excluded/promoted/merged) |
| `votes` | voti anonimi (round_id, candidate_id, category_id, value, anonymous_id) |
| `results` | snapshot risultati calcolati (round_id, category_id, candidate_id, total_votes, total_points, rank) |
| `round_category_map` | configurazione avanzamento per categoria (round_id, category_id, advancement_mode, advancement_count, next_category_id) |
| `voter_lists` | lista elettori registrati (event_id, email, name, token, token_used, approved, weight) |
| `dedup_queue` | coda deduplicazione candidati open round |
| `access_requests` | richieste pubbliche di account (name, email, organization, message, status: pending/approved/dismissed) |

## URL pubblici

- **Landing page**: `/` (`index.php`) — pagina marketing/presentazione del prodotto
- **Richiedi accesso**: `/richiedi-accesso.php` — form pubblico di richiesta account (nessun signup self-service: la richiesta va in `access_requests`, il superadmin la rivede in `admin/users.php` e crea l'account manualmente)
- Pagina evento (registrazione/accesso): `/vote/event.php?slug={slug}`
- Votazione: `/vote/cast.php?token={token}&round_id={round_id}`
- Risultati pubblici: `/vote/results.php?slug={slug}&round_id={round_id}`

## Pagine admin principali

| File | Funzione |
|---|---|
| `admin/events.php` | lista eventi |
| `admin/events-edit.php` | crea/modifica evento (upload logo, cat_term, temi, Quill description) |
| `admin/rounds.php` | pipeline turni — stats, link votazione copiabile, gestione avanzamento categorie |
| `admin/rounds-edit.php` | crea/modifica turno |
| `admin/candidates.php` | gestione candidati — collapse per categoria (mostra 10), batch exclude/delete/merge, rename |
| `admin/categories.php` | gestione categorie |
| `admin/results.php` | calcolo, validazione, pubblicazione risultati + link pubblico copiabile |
| `admin/voters.php` | gestione lista elettori |
| `admin/dedup.php` | revisione coda deduplicazione |
| `admin/import_forms_csv.php` | importazione CSV |
| `admin/users.php` | gestione utenti admin (solo superadmin) — crea/disabilita/elimina utenti, assegna eventi per ruolo, **rivede le richieste di accesso pubbliche** (`access_requests`) con azione "Crea account" (prefill nome/email) o "Ignora" |

## Logica modelli di voto

Tutti e 6 i modelli scrivono in `votes.value` un valore numerico calcolato al momento del voto. `Results::compute()` fa `SUM(value)` universal — il modello specifico è già "cotto" nel value.

Modelli: `Single`, `Multiple`, `Borda`, `Open`, `Proportional`, `Weighted`.

## Logica promozione candidati (ultima feature aggiunta)

`Results::promote(fromRoundId, toRoundId)` promuove candidati per categoria in base a `round_category_map`:
- `advancement_mode = 'auto'` → promuove top-N (dove N = `advancement_count`)
- `advancement_mode = 'all'` → promuove tutti
- `advancement_mode = 'none'` / `'manual'` → nessuna promozione automatica
- Guard `promotion_confirmed = 1` per evitare doppia promozione

## Branding

- **Colore primario**: `#2f6ad9` (variabile CSS `--e-primary` in `app.css`)
- **Accento**: `#60a5fa`, **Background**: `#f5f8ff`, **Testo**: `#0f172a`
- **Preset default**: `electus` in `Theme.php` (sostituisce `saas`)
- **Icone app**: `icons/electus-{32,64,120,180,256,512,1024}.png` — PNG 8-bit RGBA
- **Favicon**: presente in tutti gli head (`<link rel="icon" ...>` + `apple-touch-icon`) nei template e nelle pagine standalone

### Entry point HTML con `<head>` standalone (tutti hanno favicon)

| File | Tipo |
|---|---|
| `templates/admin/layout.php` | layout admin (tutte le pagine admin) |
| `templates/public/layout.php` | layout pubblico (vote/event, cast, confirm, verify) |
| `admin/login.php` | pagina login standalone |
| `vote/results.php` | due head inline (risultati non pubblicati + risultati TV) |
| `index.php` | landing page marketing |
| `richiedi-accesso.php` | form pubblico richiesta account |

## Stato attuale (agosto 2026)

### Completato

- [x] i18n completo IT/EN/FR admin panel
- [x] CatTerm — terminologia categorie configurabile per evento
- [x] events-edit.php — upload logo da disco (max 4MB), riordino campi, temi quadrati, fix Quill height
- [x] candidates.php — collapse 10 per categoria, batch exclude/delete/merge, rename in round aperti
- [x] results.php — box stat candidati unici, link pubblico risultati con copia + apertura
- [x] rounds.php — link votazione copiabile per round, box stat migliorati
- [x] Logica promozione per-categoria via `round_category_map`
- [x] Branding aggiornato: colore `#2f6ad9`, preset `electus` come default, favicon su tutti gli head
- [x] Landing page `index.php` — pagina pubblica marketing del prodotto
- [x] Richiesta accesso pubblica (`richiedi-accesso.php` + tabella `access_requests`) — nessun signup self-service, la richiesta va rivista dal superadmin in `admin/users.php` che crea l'account (honeypot anti-spam, notifica email al superadmin)

### Fix noti

- **Quill height in flex layout**: `.ql-container { height: auto !important }` + `.ql-editor { min-height: 120px }` in `assets/css/app.css`. Quill imposta `height: 100%` internamente che collassa con `min-height` sul parent flex.
- **Rename candidato in round aperti**: pulsante matita ora sempre visibile (rimossa guard `<?php if (!$isOpen): ?>`).

## Roadmap

### Priorità 1 — Blockers produzione
- [x] Pagina pubblica risultati (`vote/results.php`) — redesign completato
- [x] Gestione utenti admin (`admin/users.php`) — crea/invita utenti, assegna ruoli per evento
- [x] Import CSV elettori (`admin/voters.php`) — caricamento lista pre-registrata

### Priorità 2 — Importante
- [x] Rate limiting login (`admin/login.php`) — protezione brute-force via sessione
- [x] Redesign pagina pubblica evento (`vote/event.php`) — look professionale per i votanti
- [x] Impostazioni espanse (`admin/settings.php`) — lingua default, test email, info sistema
- [PENDING] **Email bulk ai votanti** — invio link di voto massivo da `admin/voters.php`.
  **In attesa deliberata**: il sistema funziona senza — l'admin può copiare il link dal round
  e inviarlo manualmente. Da implementare quando la base clienti lo richiederà concretamente.

### Priorità 3 — Futuro
- [PENDING] **Notifiche email al termine votazione** — stesso meccanismo della email bulk (P2.1).
  Da implementare insieme o subito dopo la P2.1, condividono la stessa infrastruttura Mailer.
- [x] API pubblica risultati (`vote/api/results.php`) — endpoint JSON pubblico con CORS
- [x] Dashboard analytics per evento (`admin/analytics.php`) — grafici partecipazione e voti
- [x] Scheduled publish risultati (data/ora automatica)
- [x] Dedup automatica migliorata — soundex, soglia adattiva, UI per confidenza
- [x] Multi-round pipeline UI — bridge visivo tra turni con pills avanzamento, badge stato promozione e pulsante "Esegui promozione"

## Note deploy

- Config in `config/config.php` (fuori repo)
- Uploads logo in `assets/uploads/logos/` (fuori repo)
- Dopo modifiche PHP fare curl OPcache clear se debug=false
- Branch unico: `main`, remote: `origin`
- SSH GitHub configurata: `~/.ssh/github_electus` (ed25519), host key in `known_hosts`
