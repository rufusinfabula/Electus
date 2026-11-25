# Electus — Documento tecnico

**Versione software:** 0.6.1 (`src/Core/Version.php`) · **Ultimo aggiornamento:** novembre 2026

---

## 1. Obiettivi e principi di progetto

Electus è una piattaforma web per gestire votazioni ed elezioni: assemblee, premi, sondaggi, nomine di cariche. Il progetto segue quattro principi.

1. **Semplicità di deploy.** PHP + MySQL/MariaDB, nessun framework, nessun processo in background. Gira su un hosting condiviso.
2. **Anonimato del voto per costruzione.** L'identità dell'elettore (token/email) e il voto vivono in tabelle senza alcun legame diretto (§6).
3. **Pipeline multi-turno.** Un evento ha più turni; i candidati si promuovono da un turno al successivo con regole per categoria.
4. **Modello di voto intercambiabile.** Sei modelli dietro un'unica interfaccia; i risultati si calcolano con un'aggregazione universale.

## 2. Stack

| Livello | Tecnologia |
|---|---|
| Linguaggio | PHP ≥ 8.3, `declare(strict_types=1)` ovunque |
| Architettura | Pagine PHP + classi statiche (nessun framework MVC), template con `ob_start()` / `ob_get_clean()` |
| Database | MySQL 8 / MariaDB, `utf8mb4`, accesso con PDO e prepared statement |
| Dipendenze PHP | `phpmailer/phpmailer ^6.9` (Composer, PSR-4 `Electus\` → `src/`) |
| Front-end | UIkit 3 (CDN), Chart.js (grafici risultati), Quill (editor rich text) |
| i18n | `lang/it.php`, `en.php`, `fr.php` (402 righe ciascuno), funzione globale `__()` |

Dimensione: circa 9.700 righe PHP (admin ~4.800, modelli e core ~2.300, pagine pubbliche ~1.000, installer ~400).

## 3. Struttura del repository

```
admin/            pagine amministrative (ognuna include bootstrap.php)
assets/css/       app.css (stile globale), uploads/logos/ (loghi caricati)
config/           config.php (non versionato) + config.example.php
icons/            icone applicazione e favicon (32…1024 px)
install/          wizard di installazione (blocco con file .installed)
lang/             traduzioni it / en / fr
sql/              schema.sql + migration_001…006
src/Core/         Auth, CatTerm, Csrf, Database, Flash, Lang, Mailer, Theme, Version
src/Models/       Candidate, Category, Deduplication, Event, Results, Round, Settings, Vote, VoterList
src/VoteModels/   VoteModelInterface, VoteModelFactory + 6 modelli
templates/        layout admin e pubblico, partials
vote/             pagine pubbliche (event, cast, confirm, verify, results) e api/results.php
index.php         landing page di presentazione
richiedi-accesso.php  form pubblico di richiesta account
```

## 4. Modello dati

Schema completo in `sql/schema.sql`. Le migrazioni `migration_001…006` servono solo per aggiornare installazioni esistenti.

| Tabella | Ruolo |
|---|---|
| `users` | utenti del pannello admin; ruolo `superadmin` / `event_manager` / `results_reader`, stato `active` / `disabled`, password bcrypt |
| `user_event_permissions` | assegnazione per evento dei ruoli non-superadmin |
| `access_requests` | richieste pubbliche di account (`pending` / `approved` / `dismissed`) |
| `events` | evento: `slug` univoco, `status` (draft/active/closed/archived), `access_mode`, `email_verification`, `results_public`, tema, `cat_term`, contenuti della pagina pubblica |
| `event_rounds` | turno: `model`, `status`, finestra `opens_at`/`closes_at`, `config` JSON, flag `votes_validated`, `results_released`, `promotion_confirmed`, `scheduled_release_at` |
| `categories` | categorie dell'evento (`sort_order`) |
| `round_category_map` | per (turno, categoria): `advancement_mode` (auto/manual/all/none), `advancement_count`, `next_category_id` |
| `candidates` | candidati per turno e categoria; `canonical_name` per la dedup; `source` (manual/promoted/user_input); `status` (active/merged/excluded) |
| `candidate_aliases` | dizionario persistente alias → nome canonico |
| `dedup_queue` | coda di revisione dei possibili duplicati (turni open) |
| `voter_lists` | elettori: `token` univoco, `token_used`, `approved`, `weight`, `source` |
| `voter_registration_requests` | richieste di registrazione nel modo "con approvazione" |
| `votes` | voti anonimi: `candidate_id`, `category_id`, `value`, `anonymous_id` |
| `results` | snapshot dei risultati calcolati (`total_votes`, `total_points`, `rank`) |
| `app_settings` | impostazioni globali chiave/valore (creata dalla migrazione 001) |

> `app_settings` è presente in `schema.sql` dalla migrazione 006; prima compariva solo nella migrazione 001.

## 5. Flusso applicativo

### 5.1 Ciclo di vita di un turno

```
draft ──► active ──► closed ──► [calcola] ──► [valida] ──► [pubblica]
                                                  │             │
                                                  │             └─ immediata oppure schedulata (scheduled_release_at)
                                                  └─ prerequisito della pubblicazione
                                  [promuovi] ──► turno successivo (candidati source='promoted')
```

- **Calcolo** (`Results::compute`): cancella lo snapshot precedente e ricalcola in una transazione.
- **Validazione** (`Results::validate`): l'admin attesta la correttezza; registra `validated_by` e `validated_at`. `unvalidate` ritira anche la pubblicazione.
- **Pubblicazione** (`Results::publish`): imposta `results_released = 1`. Può essere schedulata.
- **Pubblicazione schedulata**: nessun cron. La prima richiesta HTTP a `vote/results.php` dopo `scheduled_release_at` esegue un `UPDATE ... WHERE votes_validated = 1 AND scheduled_release_at <= NOW()`. Se nessuno visita la pagina, la pubblicazione avviene alla visita successiva.
- **Promozione** (`Results::promote`): in transazione, copia i candidati nel turno di destinazione secondo `round_category_map`. Protetta da `promotion_confirmed` (una sola esecuzione).

### 5.2 Modalità di accesso all'evento (`events.access_mode`)

| Modalità | Comportamento |
|---|---|
| `anonymous` | si vota senza token |
| `voluntary_registration` | l'elettore si registra e riceve un token |
| `mandatory_registration` | serve la registrazione per votare |
| `closed_list` | solo elettori precaricati (import CSV) |
| `registration_with_approval` | registrazione + approvazione dell'admin (`voter_registration_requests`) |

Con `email_verification = 1` il token resta non approvato finché l'elettore non apre il link inviato (`vote/verify.php`).

### 5.3 Flusso di voto (`vote/cast.php`)

1. Risolve evento da token o slug; richiede evento `active`.
2. Se la modalità richiede un token: verifica esistenza, `approved = 1`, `token_used = 0`.
3. Individua il turno (`round_id` o turno attivo), carica categorie e candidati.
4. POST: `Csrf::check()` → `validate()` → `buildVotes()` → `Vote::cast()`.
5. Redirect a `confirm.php`.

## 6. Anonimato e integrità del voto

`Vote::cast()` esegue in **un'unica transazione**:

1. legge il token e controlla `token_used`;
2. imposta `token_used = 1` e `token_used_at = NOW()`;
3. genera `anonymous_id = bin2hex(random_bytes(32))`, **mai derivato da token o email**;
4. inserisce le righe in `votes` con `value × weight`.

Conseguenze:

- **Un token, un voto.** La transazione rende atomici il consumo del token e il salvataggio dei voti: o entrambi o nessuno.
- **Nessun collegamento diretto** tra `voter_lists` e `votes`.
- **Peso dell'elettore** (`voter_lists.weight`) applicato moltiplicando `value` alla scrittura.

**Correlazione temporale (ridotta nella 0.6.1).** Prima `voter_lists.token_used_at` e `votes.voted_at` erano identici al secondo: con pochi voti ravvicinati, chi aveva accesso al database poteva associare un elettore al proprio voto. Ora entrambi sono arrotondati per difetto **allo stesso blocco di 15 minuti** (`Vote::TIME_BUCKET_SECONDS`, calcolato una sola volta per voto). Data e ora restano disponibili per le statistiche orarie e giornaliere; dentro un blocco non è possibile stabilire quale elettore abbia espresso quale voto. Le registrazioni (`voter_lists.created_at`) restano esatte. I dati precedenti si allineano con `sql/migration_006_time_buckets.sql` (irreversibile, fare prima un dump).

Limiti residui: se in un blocco di 15 minuti vota una sola persona, il suo voto è identificabile; e un elettore che si registra e vota subito può essere collegato per vicinanza tra `created_at` e il blocco di voto.

Nel modello di accesso `anonymous` non c'è token, quindi il sistema non protegge dai voti multipli.

## 7. Modelli di voto

Ogni modello implementa `VoteModelInterface` (`renderForm`, `validate`, `buildVotes`) ed è istanziato da `VoteModelFactory::make($round['model'])`. Il modello scrive in `votes.value` un valore numerico già "cotto", quindi `Results::compute()` aggrega allo stesso modo per tutti.

| Modello | Cosa vota l'elettore | `value` scritto | Parametri (`event_rounds.config`) |
|---|---|---|---|
| `single` | un candidato per categoria | 1 | — |
| `multiple` | fino a N candidati per categoria | 1 per scelta | `max_choices` (default 3) |
| `borda` | assegna punti ai candidati | punti assegnati | `borda_mode` = `fixed` (scala, es. `12,10,8,…`, ogni valore usato una sola volta) o `free` (budget di punti, `borda_budget`); `max_candidates` |
| `open` | scrive liberamente un nome | 1 | — (i candidati nascono come `user_input` e passano per la deduplicazione) |
| `proportional` | seleziona candidati | 1 per scelta | — |
| `weighted` | un candidato per categoria | 1 × peso elettore | peso in `voter_lists.weight` |

**Aggregazione.** Per ogni categoria: `total_votes = COUNT(*)`, `total_points = ROUND(SUM(value))`, `rank = RANK() OVER (PARTITION BY category ORDER BY SUM(value) DESC)`. Contano solo i candidati `active`. Pari merito → stesso rank.

`ProportionalModel` scrive `value = 1` per ogni scelta, come `multiple`: il codice non applica alcun metodo di ripartizione dei seggi (d'Hondt, Sainte-Laguë). Vedi §11.

## 8. Candidati e deduplicazione

- `Candidate::sanitizeName()` rimuove prefissi `http(s)://` e `www.` e forza il MAIUSCOLO. Si applica a ogni punto d'ingresso.
- `Candidate::normalize()` produce la chiave `canonical_name`: minuscolo, senza articoli iniziali, senza caratteri non alfanumerici, spazi collassati.
- `Deduplication::checkAndQueue()` (turni `open`) confronta ogni nuovo input con i candidati attivi della categoria e accoda i sospetti in `dedup_queue`. Il punteggio 0–100 combina inclusione di sottostringhe, `similar_text`, `levenshtein` e un bonus `soundex`; la soglia richiesta si adatta alla lunghezza (le stringhe corte sono più rumorose).
- L'admin (`admin/dedup.php`) decide per gruppo: **unisci**, **mantieni** separati o **escludi**. Le unioni alimentano `candidate_aliases`, dizionario riutilizzabile tra edizioni.
- `rescanAll()` rilancia la scansione dopo modifiche all'algoritmo.

## 9. Sicurezza

| Area | Implementazione |
|---|---|
| SQL injection | PDO con prepared statement |
| CSRF | `Csrf::check()` all'inizio di ogni handler POST |
| Password | `password_hash(PASSWORD_BCRYPT)` / `password_verify`; rigenerazione dell'id di sessione al login |
| Brute force sul login | 5 tentativi, blocco di 300 s, **per sessione** (`LOGIN_MAX_ATTEMPTS`, `LOGIN_LOCKOUT_SECONDS`) |
| Autorizzazione | `Auth::requireRole()`, `Auth::requireEventPermission($eventId)`; il superadmin ha accesso totale |
| Ruoli | `superadmin` (tutto, gestione utenti), `event_manager` (gestione dell'evento assegnato), `results_reader` (sola lettura risultati) |
| Anti-spam | honeypot sul form `richiedi-accesso.php`; nessun signup self-service |
| Installer | bloccato da `install/.installed` |
| Upload | loghi su disco, max 4 MB |

**Punti di attenzione**

- Il rate limiting del login è legato alla sessione: chi scarta il cookie lo azzera. Serve un contatore lato server per IP/email (o fail2ban).
- `vote/api/results.php` espone CORS `*` senza autenticazione, ma solo per turni con `results_released = 1`.
- Le pagine non inviano header di sicurezza (CSP, HSTS, X-Frame-Options): da impostare nel web server.
- Il form di voto e il form di richiesta accesso non hanno limiti di frequenza oltre all'honeypot.
- Verificare i flag `Secure` / `HttpOnly` / `SameSite` dei cookie di sessione nella configurazione PHP.

## 10. Configurazione, installazione e deploy

**Installazione**

1. `composer install --no-dev`
2. Aprire `/install/` e completare il wizard (verifica PHP ≥ 8.3, `pdo_mysql`, `mbstring`, `openssl`, `json`, scrittura su `config/`). Crea il database, applica `schema.sql`, genera `config/config.php`, crea il superadmin e scrive `install/.installed`.
3. In alternativa, a mano: copiare `config/config.example.php` in `config/config.php` e importare `sql/schema.sql`.
4. Per installazioni esistenti applicare in ordine `migration_001…006`.

**`config/config.php`** (non versionato)

```php
'db'   => ['host','port','name','user','pass','charset'],
'app'  => ['name','url','timezone','debug','session_name','admin_lang','public_lang'],
'mail' => ['host','port','username','password','encryption','from_email','from_name'],
```

**Operatività**

- Con `debug = false` PHP usa OPcache: dopo un deploy va svuotato. Da CLI non è possibile; si crea un file PHP temporaneo che chiama `opcache_reset()`, lo si richiama via HTTP e lo si cancella.
- Branch unico `main`, remote `origin`.
- I loghi caricati stanno in `assets/uploads/logos/` e non fanno parte del codice.
- Nessun processo schedulato: la pubblicazione programmata scatta alla richiesta HTTP.

## 11. Debito tecnico e miglioramenti consigliati

Ricavati dalla lettura del codice, in ordine di importanza.

1. ~~Correlazione `token_used_at` ↔ `voted_at`~~ — risolto (§6, migrazione 006).
2. ~~`app_settings` assente da `schema.sql`~~ — risolto. Corretto anche un bug più grave: un `;` dentro un commento di `schema.sql` faceva fallire l'installer (che divide lo script con `explode(';')`) a metà dello schema. Rimane la fragilità: un futuro `;` nei commenti romperebbe di nuovo il wizard.
3. **Modello `proportional`**: aggiungere un vero algoritmo di assegnazione dei seggi oppure rinominarlo.
4. **Allineare schema e documentazione**: `candidates.status` è `active/merged/excluded`; la promozione è registrata in `source = 'promoted'`, mentre `CLAUDE.md` cita `promoted` tra gli status.
5. **Rate limiting del login lato server** al posto di quello di sessione.
6. **Header di sicurezza HTTP** e flag dei cookie di sessione.
7. **Messaggi in inglese hard-coded** nei modelli di voto («Select up to…», «Duplicate point values…»): non passano da `__()`.
8. **Nessun test automatico**: mancano test per `Results::compute/promote`, i modelli di voto e la similarità della dedup.
9. **Nessuna integrazione continua** (lint `php -l`, test) e nessun file `LICENSE`.
10. **`cast.php`**: la prima query sul token fa `SELECT vl.*, e.*`, con collisione di nomi di colonna (`id`, `name`, `created_at`). Il risultato viene poi rifatto con `VoterList::findByToken`: la prima query è superflua.
11. **Email bulk e notifiche di fine votazione**: rimandate deliberatamente (roadmap in `CLAUDE.md`).

## 12. Riferimento API pubblica

`GET /vote/api/results.php?slug={slug}&round_id={id}`

| Codice | Corpo |
|---|---|
| 200 | `{event:{name,slug}, round:{id,round_number,label,results_released,computed_at}, categories:[{id,name,candidates:[{rank,name,total_votes,total_points}]}]}` |
| 403 | `{"error":"results_not_released"}` |
| 404 | `{"error":"not_found"}` |

Header: `Content-Type: application/json`, `Access-Control-Allow-Origin: *`, `Cache-Control: public, max-age=60`.
