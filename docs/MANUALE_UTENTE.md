# Electus — Manuale utente

Questo manuale spiega come usare Electus. Ha due parti: la **Parte A** è per chi organizza una votazione (amministratori), la **Parte B** per chi vota.

---

# Parte A — Per gli amministratori

## A1. Ruoli

| Ruolo | Cosa può fare |
|---|---|
| **Superadmin** | tutto: crea e gestisce utenti, vede ogni evento, rivede le richieste di accesso |
| **Event manager** | gestisce gli eventi che gli sono stati assegnati: turni, candidati, elettori, risultati |
| **Results reader** | legge i risultati degli eventi assegnati; non modifica nulla |

## A2. Ottenere un account

Electus non ha registrazione libera. Si compila il modulo **Richiedi accesso** (`/richiedi-accesso.php`) con nome, email, organizzazione e un messaggio. Il superadmin riceve una notifica email, rivede la richiesta in **Utenti** e crea l'account oppure la ignora.

## A3. Accedere

1. Apri `/admin/login.php` e inserisci email e password.
2. Dopo **5 tentativi sbagliati** l'accesso si blocca per 5 minuti; la pagina mostra il conto alla rovescia.
3. Cambia la lingua del pannello (italiano, inglese, francese) dal menu in alto a destra.

## A4. Creare un evento

**Eventi → Nuovo evento.**

| Campo | Note |
|---|---|
| Nome e slug | lo slug compone l'indirizzo pubblico: `/vote/event.php?slug=…` |
| Descrizione | editor di testo formattato |
| Logo | caricamento da disco, massimo 4 MB |
| Terminologia | come chiamare le "categorie": Categoria, Scelta, Carica, Seggio, Posto, Premio, Ruolo, Proposta, Incarico. Cambia le etichette in tutta l'interfaccia |
| Tema | colori della pagina pubblica |
| Modalità di accesso | vedi A5 |
| Verifica email | se attiva, l'elettore deve confermare l'indirizzo prima di votare |
| Risultati pubblici | se attiva, i risultati pubblicati sono visibili a tutti |
| Stato | Bozza → Attivo → Chiuso → Archiviato. Si vota solo se l'evento è **Attivo** |

## A5. Scegliere come identificare gli elettori

| Modalità | Quando usarla |
|---|---|
| **Anonima** | sondaggi aperti. Nessun controllo sui voti multipli |
| **Registrazione volontaria** | chi vuole si registra e riceve un link personale |
| **Registrazione obbligatoria** | serve la registrazione per votare |
| **Lista chiusa** | assemblee di soci: carichi tu l'elenco degli aventi diritto |
| **Registrazione con approvazione** | gli interessati si iscrivono e tu approvi caso per caso |

Con la verifica email attiva, il link di voto funziona solo dopo che l'elettore ha cliccato il link di conferma ricevuto per email.

## A6. Preparare le categorie

**Categorie** dal menu dell'evento. Aggiungi, rinomina, riordina. Le categorie sono i "capitoli" della scheda: per esempio le cariche di un direttivo o le sezioni di un premio.

## A7. Creare i turni

**Panoramica → Nuovo turno.** Un evento può avere più turni (per esempio proposte → semifinale → finale).

1. Dai un'etichetta e, se vuoi, descrizione, istruzioni e riquadro informativo per gli elettori.
2. Scegli il **modello di voto**:

| Modello | Cosa fa l'elettore | Quando usarlo |
|---|---|---|
| **Singolo** | sceglie un candidato per categoria | elezione classica |
| **Multiplo** | sceglie fino a N candidati | consiglio con più posti |
| **Borda** | assegna punti: scala fissa (es. 12-10-8-…) o budget libero | graduatorie e premi |
| **Aperto** | scrive liberamente un nome | raccolta di candidature o proposte |
| **Proporzionale** | seleziona candidati | selezione multipla, un punto per scelta |
| **Ponderato** | sceglie un candidato; il voto vale quanto il **peso** dell'elettore | soci con quote diverse |

3. Imposta, se vuoi, apertura e chiusura automatiche.
4. Per ogni categoria decidi **che cosa succede ai candidati dopo il turno**:

| Modalità | Effetto |
|---|---|
| **Auto** | passano i primi N |
| **Tutti** | passano tutti i candidati attivi |
| **Manuale** / **Nessuno** | nessuna promozione automatica |

Puoi anche far confluire una categoria in un'altra nel turno successivo.

5. Quando il turno è pronto, portalo a **Attivo**. La pagina Panoramica mostra il **link di voto** da copiare e inviare.

## A8. Gestire i candidati

**Candidati** (per turno).

- Aggiungi a mano o importa da CSV (**Importa CSV**).
- I nomi sono normalizzati: scritti in maiuscolo, senza `http://` o `www.`.
- Ogni categoria mostra i primi 10; espandila per vederli tutti.
- Azioni di gruppo: **escludi**, **elimina**, **unisci**.
- Il pulsante a matita rinomina un candidato, anche a turno aperto.

### Duplicati nei turni "Aperti"

Quando gli elettori scrivono i nomi a mano, arrivano varianti: «Mario Rossi», «mario rossi», «M. Rossi». Electus le confronta e le mette in una coda in **Deduplica**, con un punteggio di somiglianza.

Per ogni gruppo scegli:

- **Unisci**: i voti confluiscono su un solo nome; l'alias viene ricordato per le edizioni future.
- **Mantieni**: sono persone/cose diverse.
- **Escludi**: voce non valida.

## A9. Gestire gli elettori

**Elettori** (menu dell'evento).

- **Importa CSV**: file con due colonne, `email` e `nome`. Scarica il modello dal link nella finestra di importazione. Le righe con indirizzo non valido sono saltate; puoi scegliere se sovrascrivere i nomi esistenti.
- Ogni elettore ha un **token** personale e un **peso** (1 di default, usato dal modello Ponderato).
- Il pannello mostra registrati, votanti, partecipazione, sospesi e richieste in attesa.
- **Richieste in attesa**: approvale o respingile.
- Puoi eliminare un elettore che non ha ancora votato.

> L'invio massivo dei link via email non è ancora disponibile. Copia il link del turno e invialo con la tua posta, oppure usa i link personali degli elettori.

## A10. Seguire la partecipazione

**Analisi** (analytics) mostra grafici di partecipazione e voti nel tempo.

## A11. Risultati: calcola, convalida, pubblica

**Risultati** (per turno). L'ordine è importante.

1. **Calcola.** Elabora i voti e crea la classifica. Puoi ricalcolare quante volte vuoi.
2. **Convalida.** Attesti che i voti sono corretti. Electus registra chi e quando. Senza convalida non si può pubblicare.
3. **Pubblica.** Rende i risultati visibili agli elettori, subito oppure a un **giorno e ora programmati**.
4. **Promuovi.** Se hai impostato l'avanzamento, il pulsante **Esegui promozione** copia i candidati al turno successivo. Si può fare una sola volta per turno.

Se scopri un errore, **Annulla convalida**: i risultati tornano non pubblicati.

Dalla stessa pagina copi il **link pubblico** dei risultati, apri la pagina e scarichi il CSV.

> **Pubblicazione programmata.** Non usa un cron: scatta alla prima visita alla pagina dei risultati dopo l'ora fissata. Se vuoi puntualità, apri tu la pagina all'ora prevista.

## A12. Gestione utenti (solo superadmin)

**Utenti**: crea, disabilita, elimina, reimposta la password. Assegna a ciascun utente gli eventi e il ruolo (Event manager o Results reader). Qui trovi anche le **richieste di accesso** con le azioni **Crea account** (nome e email già compilati) e **Ignora**.

## A13. Impostazioni

- **Tema** del pannello (palette predefinite; quella standard è *Electus*).
- **Lingua** predefinita di amministrazione.
- **Email di prova** per verificare la configurazione di posta.
- **Informazioni di sistema**.

## A14. Checklist prima del voto

- [ ] Evento in stato **Attivo**
- [ ] Categorie e candidati controllati (nessun duplicato)
- [ ] Modello di voto e parametri verificati con una prova
- [ ] Elettori caricati e link di voto pronto
- [ ] Email di prova ricevuta (se usi la verifica email)
- [ ] Testo informativo e link alla privacy compilati
- [ ] Chiaro chi calcola, convalida e pubblica

## A15. Problemi frequenti

| Problema | Causa probabile | Soluzione |
|---|---|---|
| «Event not found or not active» | evento non Attivo | porta l'evento allo stato Attivo |
| L'elettore vede «già votato» | il token è stato consumato | verifica in **Elettori**; il voto non si può ripetere |
| Impossibile pubblicare | risultati non convalidati | calcola e convalida prima |
| «Promotion already confirmed» | promozione già eseguita | non si ripete; correggi manualmente i candidati del turno successivo |
| Pubblicazione programmata in ritardo | nessuno ha visitato la pagina | apri la pagina dei risultati |
| Accesso bloccato | 5 tentativi errati | attendi 5 minuti |
| L'email non arriva | SMTP non configurato | usa **Impostazioni → Email di prova** |

---

# Parte B — Per chi vota

## B1. Come si vota

1. **Apri il link** ricevuto dall'organizzatore.
2. Se richiesto, **registrati** (nome, email) e, se previsto, **conferma l'indirizzo** cliccando il link ricevuto per email.
3. Nella pagina di voto vedi le categorie e i candidati. Segui le istruzioni del turno:
   - **scelta singola**: un solo candidato per categoria;
   - **scelta multipla**: fino al numero indicato;
   - **punti**: assegna i punti secondo la scala o il budget indicato; ogni valore della scala si usa una sola volta;
   - **nome libero**: scrivi il nome della persona o proposta.
4. **Conferma.** Il voto **non si può modificare** dopo l'invio.
5. Compare la pagina di conferma. Se i risultati sono pubblici, trovi il pulsante **Vedi risultati**.

## B2. Il tuo voto è anonimo?

L'indirizzo email serve solo a impedire che la stessa persona voti due volte. Il sistema non salva alcun collegamento diretto tra la tua email e le scelte espresse: il voto riceve un identificativo casuale generato al momento dell'invio.

## B3. Domande frequenti

**Ho perso il link.** Chiedi all'organizzatore di rinviarlo.

**Mi dice che ho già votato.** Il link personale funziona una sola volta. Se pensi sia un errore, scrivi all'organizzatore.

**Il voto si chiude?** La pagina indica quando termina. Dopo la chiusura non è più possibile votare.

**Posso cambiare lingua?** Sì: italiano, inglese e francese.

**Dove vedo i risultati?** Alla pagina pubblica dei risultati, quando l'organizzatore li pubblica.
