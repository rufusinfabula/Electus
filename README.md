# Electus

### Sistema open source di gestione votazioni ed elezioni.

## Cos'è

Electus è una piattaforma per la creazione e gestione di eventi di voto (assemblee, elezioni, premi, sondaggi) con pannello di amministrazione completo e pagine pubbliche per i votanti. Supporta pipeline multi-turno con promozione automatica dei candidati, sei modelli di voto diversi, deduplicazione candidati, liste elettori con verifica via token/email, e pubblicazione risultati (anche schedulata).

## Funzionalità principali

- **Pannello admin** completo (eventi, turni, categorie, candidati, elettori, risultati, analytics)
- **6 modelli di voto**: Single, Multiple, Borda, Open, Proportional, Weighted
- **Pipeline multi-turno** con promozione candidati configurabile per categoria (auto/manuale/tutti)
- **Deduplicazione candidati** per i round a voto libero (open)
- **Liste elettori** con token di accesso, verifica email, import CSV
- **Risultati pubblici** con pagina JSON API, grafici (Chart.js), pubblicazione schedulata
- **Terminologia configurabile** per evento (Categoria/Scelta/Premio/Seggio/...)
- **i18n** completo: italiano, inglese, francese
- **Rate limiting** login e protezione CSRF
- **Branding personalizzabile** per evento (logo, temi)

## Stack tecnico

- **PHP 8.3** (`strict_types=1`), nessun framework MVC
- **PDO** raw per l'accesso al database (MySQL/MariaDB)
- **Composer** — autoload PSR-4, dipendenza [PHPMailer](https://github.com/PHPMailer/PHPMailer)
- **UIkit 3** per la UI admin
- **Chart.js** per i grafici dei risultati
- **Quill** come editor rich text

## Struttura del progetto

```
admin/          pagine amministrative
assets/         CSS globale, upload loghi
config/         configurazione (non versionata)
lang/           traduzioni it / en / fr
src/
  Core/         Auth, Csrf, Database, Mailer, Theme, ...
  Models/       Candidate, Category, Event, Results, Round, Vote, ...
  VoteModels/   implementazione dei 6 modelli di voto
sql/            schema e migrazioni
vote/           pagine pubbliche di voto e risultati
templates/      layout condivisi
```

## Requisiti

- PHP >= 8.3 con estensione PDO (MySQL)
- MySQL / MariaDB
- Composer

## Installazione

```bash
composer install
```

Copiare/creare `config/config.php` con i parametri di connessione al database, l'URL dell'applicazione e le impostazioni email, quindi importare lo schema:

```bash
mysql -u <user> -p <database> < sql/schema.sql
```

Applicare in ordine le migrazioni presenti in `sql/` (`migration_001_...` → `migration_005_...`).

## Licenza

Progetto distribuito come open source.
