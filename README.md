# Electus

**Sistema open source per votazioni ed elezioni.** Dalla raccolta delle candidature alla pubblicazione dei risultati, con voto anonimo, più turni e sei modelli di voto.

🌐 Demo / sito: <https://electus.spazioumano.org>

![PHP](https://img.shields.io/badge/PHP-%E2%89%A58.3-777bb4) ![MySQL](https://img.shields.io/badge/MySQL%2FMariaDB-required-00758f) ![Version](https://img.shields.io/badge/version-0.6.1-2f6ad9) ![License](https://img.shields.io/badge/license-AGPL--3.0-blue)

## Funzionalità

- **Sei modelli di voto**: singolo, multiplo, Borda (scala fissa o budget libero), aperto (nome libero), proporzionale, ponderato.
- **Pipeline multi-turno**: promozione dei candidati da un turno al successivo, per categoria (primi N, tutti, manuale).
- **Deduplicazione dei candidati** nei turni aperti: similarità (sottostringhe, Levenshtein, Soundex), coda di revisione, dizionario di alias.
- **Gestione elettori**: lista chiusa, registrazione volontaria/obbligatoria/con approvazione, verifica email, import CSV, peso del voto.
- **Un token, un voto**: consumo del token e salvataggio dei voti in un'unica transazione; il voto riceve un identificativo casuale non derivato dall'identità.
- **Risultati controllati**: calcolo → convalida → pubblicazione (immediata o programmata), pagina pubblica con grafici, esportazione CSV e API JSON.
- **Personalizzazione**: logo, temi, terminologia per evento (Categoria, Carica, Seggio, Premio…).
- **Pannello admin** con ruoli (`superadmin`, `event_manager`, `results_reader`), analytics di partecipazione, rate limiting del login e protezione CSRF.
- **Interfaccia in italiano, inglese e francese.**

## Requisiti

- PHP ≥ 8.3 con `pdo_mysql`, `mbstring`, `openssl`, `json`
- MySQL 8 o MariaDB
- Composer
- Un server web (Apache/Nginx) e, per le email, un server SMTP

## Installazione

```bash
git clone git@github.com:rufusinfabula/Electus.git
cd Electus
composer install --no-dev
```

Poi apri `https://tuo-dominio/install/` e segui il wizard: controlla i requisiti, crea il database, applica lo schema, genera `config/config.php` e crea il primo superadmin.

**Installazione manuale**

```bash
cp config/config.example.php config/config.php   # modifica DB, URL, SMTP
mysql -u <utente> -p <database> < sql/schema.sql
```

Per aggiornare un'installazione esistente applica in ordine le migrazioni `sql/migration_001…006`.

> Dopo l'installazione verifica che `install/.installed` esista: blocca una nuova esecuzione del wizard.

## Come si usa (in breve)

1. Un superadmin crea gli account (le richieste arrivano dal modulo `/richiedi-accesso.php`).
2. Si crea un **evento**, si sceglie la modalità di accesso degli elettori e si definiscono le **categorie**.
3. Si creano uno o più **turni**, ciascuno con il suo modello di voto, e si caricano **candidati** ed **elettori**.
4. Si attiva il turno e si condivide il link di voto: `/vote/cast.php?token=…&round_id=…` oppure `/vote/event.php?slug=…`.
5. A voto chiuso: **Calcola → Convalida → Pubblica**, poi eventualmente **Promuovi** al turno successivo.

Guida completa: [Manuale utente](docs/MANUALE_UTENTE.md).

## API pubblica

```
GET /vote/api/results.php?slug={slug}&round_id={id}
```

Restituisce i risultati di un turno già pubblicato (`200`), `403 results_not_released` se non ancora pubblicati, `404 not_found` se evento o turno non esistono. Risposta JSON con CORS aperto e cache di 60 secondi.

## Struttura del progetto

```
admin/          pannello di amministrazione
vote/           pagine pubbliche (evento, voto, conferma, risultati) e API
src/Core/       Auth, Csrf, Database, Mailer, Theme, CatTerm, Lang, Flash
src/Models/     Event, Round, Candidate, Results, Vote, VoterList, Deduplication…
src/VoteModels/ i sei modelli di voto
sql/            schema e migrazioni
lang/           traduzioni it / en / fr
templates/      layout condivisi
install/        wizard di installazione
```

## Documentazione

| Documento | Contenuto |
|---|---|
| [Documento tecnico](docs/DOCUMENTO_TECNICO.md) | architettura, modello dati, anonimato, sicurezza, debito tecnico |
| [Manuale utente](docs/MANUALE_UTENTE.md) | guida per amministratori ed elettori |
| [Scheda di presentazione](docs/SCHEDA_PRESENTAZIONE.md) | sintesi del prodotto |

## Sicurezza

- Non versionare mai `config/config.php` né credenziali (il file è nel `.gitignore`).
- Imposta nel web server gli header di sicurezza (HSTS, CSP, X-Frame-Options) e i flag dei cookie di sessione.
- Il rate limiting del login è per sessione: affiancalo a fail2ban o a regole del web server per una protezione completa.
- Per segnalare una vulnerabilità scrivi in privato al maintainer prima di aprire una issue pubblica.

## Stato e roadmap

Versione 0.6.1, in sviluppo attivo. In programma: invio massivo dei link di voto via email e notifiche di fine votazione.

## Contribuire

Le pull request sono benvenute. Usa `declare(strict_types=1)`, PDO con prepared statement, `Csrf::check()` in ogni handler POST, e aggiungi ogni nuova stringa in `lang/it.php`, `lang/en.php` e `lang/fr.php`.

## Licenza

Electus — sistema open source per votazioni ed elezioni.
Copyright © 2026 Fabio Ranfi.

Distribuito secondo i termini della **GNU Affero General Public License v3.0** (o, a scelta di chi lo usa, una versione successiva) — testo completo in [LICENSE](LICENSE). In breve: si può usare, modificare e ridistribuire liberamente; chi fa girare una versione modificata come servizio raggiungibile in rete deve mettere a disposizione di chi lo usa il codice sorgente di quella versione, comprese le modifiche.
