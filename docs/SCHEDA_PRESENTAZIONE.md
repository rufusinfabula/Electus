# Electus

### Votazioni ed elezioni trasparenti, semplici da organizzare.

Electus è un sistema **open source** per gestire elezioni, assemblee, premi e sondaggi: dalla raccolta delle candidature alla pubblicazione dei risultati, in un solo posto.

---

## Per chi è

- **Associazioni e ONG**: elezione del direttivo, assemblee dei soci.
- **Premi e concorsi**: candidature aperte, preselezione, finale.
- **Enti, scuole, comitati**: consultazioni e scelte collettive.
- **Community e gruppi**: sondaggi e nomine.

## Cosa fa

| | |
|---|---|
| **6 modelli di voto** | singolo, multiplo, Borda, nome libero, proporzionale, ponderato |
| **Più turni** | candidature → semifinale → finale, con promozione automatica dei migliori per categoria |
| **Candidature libere** | gli elettori scrivono i nomi; Electus riconosce le varianti e propone di unirle |
| **Elettori sotto controllo** | liste chiuse, registrazione con approvazione, verifica email, import CSV, peso del voto |
| **Un voto per persona** | link personale a uso singolo |
| **Risultati sotto controllo** | calcolo → convalida → pubblicazione, anche a data e ora programmate |
| **Pagine pubbliche** | pagina evento, scheda di voto, risultati con grafici, API JSON |
| **Su misura** | logo, colori, terminologia (Categoria, Carica, Seggio, Premio…) |
| **Tre lingue** | italiano, inglese, francese |

## Perché Electus

1. **Voto anonimo per progetto.** L'identità dell'elettore e le sue scelte stanno in tabelle separate; ogni voto riceve un codice casuale.
2. **Un voto, una volta.** Il link personale si consuma nello stesso istante in cui il voto viene registrato, in un'unica operazione.
3. **Nessun risultato senza convalida.** Un amministratore deve attestarli, e resta traccia di chi e quando.
4. **Semplice da installare.** PHP e MySQL, nessun servizio aggiuntivo: funziona su un hosting condiviso con un wizard di installazione.
5. **Open source.** Il codice è ispezionabile e adattabile.

## Come funziona, in 5 passi

1. **Crea l'evento** e scegli come identificare gli elettori.
2. **Definisci categorie e turni**, scegliendo il modello di voto.
3. **Carica candidati ed elettori** (anche da CSV).
4. **Apri il turno** e condividi il link.
5. **Calcola, convalida, pubblica.** Promuovi i finalisti al turno successivo.

## Accesso con garanzie

Nessuna registrazione libera: gli account amministratore si richiedono con un modulo e li crea il responsabile del sistema. Ruoli distinti: **superadmin**, **event manager**, **results reader**.

## Dati tecnici

| | |
|---|---|
| Tecnologia | PHP 8.3, MySQL/MariaDB, UIkit 3, Chart.js |
| Dimensioni | circa 9.700 righe di codice, 15 tabelle |
| Versione | 0.6.1 |
| Sito | https://electus.spazioumano.org |
| Codice | https://github.com/rufusinfabula/Electus |

## Stato del progetto

Funzionante in produzione e in sviluppo attivo. In programma: invio massivo dei link di voto via email e notifiche di fine votazione.

---

*Electus — un progetto di Spazio Umano.*
