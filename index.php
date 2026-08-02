<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Electus — Sistema di gestione votazioni ed elezioni</title>
    <meta name="description" content="Electus è il sistema professionale per gestire votazioni, elezioni e sondaggi. Multi-turno, multi-modello, multi-cliente.">
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/electus-32.png">
    <link rel="icon" type="image/png" sizes="64x64" href="/icons/electus-64.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/electus-180.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/uikit@3/dist/css/uikit.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        :root {
            --p: #2f6ad9;
            --p-dark: #1d4faa;
            --p-light: #ebf2ff;
            --p-mid: #dbeafe;
            --text: #0f172a;
            --muted: #64748b;
            --radius: 14px;
            --e-primary: #2f6ad9; /* alias per i componenti e-footer-* */
        }
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; color: var(--text); background: #fff; margin: 0; }

        /* ── NAV ── */
        .l-nav { background: var(--p); padding: 0 24px; height: 60px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .l-nav-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .l-logo-img { width: 32px; height: 32px; border-radius: 8px; background: #fff; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .l-logo-img img { width: 28px; height: 28px; object-fit: contain; }
        .l-logo-text { color: #fff; font-family: 'Recursive', cursive; font-weight: 900; font-size: 1.1rem; letter-spacing: -.01em; }
        .l-hero-brand { display: inline-flex; align-items: center; gap: 22px; background: #fff; border-radius: 26px; padding: 22px 40px; box-shadow: 0 10px 40px rgba(0,0,0,.22); margin-bottom: 32px; }
        .l-hero-brand-icon { width: 88px; height: 88px; background: var(--p); border-radius: 20px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; }
        .l-hero-brand-icon img { width: 64px; height: 64px; object-fit: contain; display: block; }
        .l-hero-brand-name { font-family: 'Recursive', cursive; font-weight: 900; font-size: 4rem; line-height: 88px; color: var(--p); letter-spacing: -.03em; }
        .l-nav-actions { display: flex; align-items: center; gap: 18px; }
        .l-nav-link { color: rgba(255,255,255,.9); font-weight: 600; font-size: .9rem; text-decoration: none; }
        .l-nav-link:hover { color: #fff; }
        .l-nav-cta { background: #fff; color: var(--p); border-radius: 8px; padding: 7px 18px; font-weight: 600; font-size: .9rem; text-decoration: none; transition: opacity .15s; }
        .l-nav-cta:hover { opacity: .85; }

        /* ── HERO ── */
        .l-hero { background: linear-gradient(135deg, var(--p) 0%, var(--p-dark) 100%); padding: 96px 24px 80px; text-align: center; }
        .l-hero-badge { display: inline-block; background: rgba(255,255,255,.18); color: #fff; border-radius: 99px; padding: 5px 16px; font-size: .8rem; font-weight: 600; letter-spacing: .04em; margin-bottom: 28px; }
        .l-hero h1 { color: #fff; font-size: clamp(2rem, 5vw, 3.2rem); font-weight: 800; margin: 0 0 20px; letter-spacing: -.02em; line-height: 1.15; }
        .l-hero p { color: rgba(255,255,255,.82); font-size: 1.15rem; max-width: 540px; margin: 0 auto 40px; line-height: 1.6; }
        .l-hero-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn-primary { background: #fff; color: var(--p); padding: 13px 28px; border-radius: 10px; font-weight: 700; font-size: 1rem; text-decoration: none; border: none; cursor: pointer; transition: transform .15s, box-shadow .15s; box-shadow: 0 4px 14px rgba(0,0,0,.15); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,0,0,.2); }
        .btn-outline { background: transparent; color: #fff; padding: 13px 28px; border-radius: 10px; font-weight: 600; font-size: 1rem; text-decoration: none; border: 2px solid rgba(255,255,255,.5); transition: border-color .15s, background .15s; }
        .btn-outline:hover { border-color: #fff; background: rgba(255,255,255,.08); }

        /* ── STATS BAR ── */
        .l-stats { background: var(--p-light); border-bottom: 1px solid var(--p-mid); padding: 20px 24px; }
        .l-stats-inner { max-width: 900px; margin: 0 auto; display: flex; justify-content: center; gap: 48px; flex-wrap: wrap; }
        .l-stat { text-align: center; }
        .l-stat-n { font-size: 2rem; font-weight: 800; color: var(--p); line-height: 1; }
        .l-stat-l { font-size: .8rem; color: var(--muted); font-weight: 500; margin-top: 4px; }

        /* ── SECTION ── */
        .l-section { padding: 72px 24px; max-width: 1080px; margin: 0 auto; }
        .l-section-title { font-size: 1.9rem; font-weight: 800; letter-spacing: -.02em; margin: 0 0 10px; }
        .l-section-sub { color: var(--muted); font-size: 1.05rem; margin: 0 0 48px; line-height: 1.5; }

        /* ── FEATURE GRID ── */
        .l-features { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .l-feat { background: #f8faff; border: 1px solid #e0ebfa; border-radius: var(--radius); padding: 28px 24px; }
        .l-feat-icon { width: 44px; height: 44px; background: var(--p-mid); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; }
        .l-feat-icon svg { width: 22px; height: 22px; stroke: var(--p); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .l-feat h3 { font-size: 1rem; font-weight: 700; margin: 0 0 8px; }
        .l-feat p { font-size: .9rem; color: var(--muted); margin: 0; line-height: 1.55; }

        /* ── MODELS ── */
        .l-models { display: flex; flex-wrap: wrap; gap: 10px; }
        .l-model-chip { background: var(--p-light); color: var(--p); border: 1px solid var(--p-mid); border-radius: 99px; padding: 8px 18px; font-size: .88rem; font-weight: 600; }

        /* ── HOW IT WORKS ── */
        .l-steps { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0; counter-reset: steps; }
        .l-step { padding: 28px 24px; position: relative; }
        .l-step-n { width: 36px; height: 36px; background: var(--p); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: .9rem; margin-bottom: 14px; }
        .l-step h3 { font-size: 1rem; font-weight: 700; margin: 0 0 8px; }
        .l-step p { font-size: .88rem; color: var(--muted); margin: 0; line-height: 1.5; }

        /* ── CTA BANNER ── */
        .l-cta { background: linear-gradient(135deg, var(--p) 0%, var(--p-dark) 100%); border-radius: var(--radius); padding: 56px 40px; text-align: center; margin: 0 24px 72px; max-width: 1032px; margin-left: auto; margin-right: auto; }
        .l-cta h2 { color: #fff; font-size: 1.8rem; font-weight: 800; margin: 0 0 12px; }
        .l-cta p { color: rgba(255,255,255,.8); font-size: 1rem; margin: 0 0 32px; }

        /* ── FOOTER ── */
        .l-footer { background: #f8faff; border-top: 1px solid #e8edf5; padding: 32px 24px; text-align: center; }
        .l-footer-logo { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; margin-bottom: 12px; }
        .l-footer-logo-mark { width: 28px; height: 28px; background: var(--p); border-radius: 7px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .l-footer-logo-mark img { width: 24px; height: 24px; object-fit: contain; }
        .l-footer-text { font-size: .82rem; color: var(--muted); }
        .l-footer a { color: var(--p); text-decoration: none; }
        .l-footer a:hover { text-decoration: underline; }

        /* ── DIVIDER ── */
        .l-divider { border: none; border-top: 1px solid #e8edf5; margin: 0; }

        @media (max-width: 640px) {
            .l-hero { padding: 64px 20px 56px; }
            .l-cta { padding: 40px 24px; margin: 0 16px 56px; }
            .l-stats-inner { gap: 28px; }
            .l-nav { padding: 0 14px; }
            .l-nav-actions { gap: 10px; }
            .l-nav-link { display: none; }
        }
    </style>
</head>
<body>

<!-- Nav -->
<nav class="l-nav">
    <a href="/" class="l-nav-logo">
        <div class="l-logo-img">
            <img src="/icons/electus-64.png" alt="Electus">
        </div>
        <span class="l-logo-text">Electus</span>
    </a>
    <div class="l-nav-actions">
        <a href="/richiedi-accesso.php" class="l-nav-link">Richiedi accesso</a>
        <a href="/admin/" class="l-nav-cta">Accedi</a>
    </div>
</nav>

<!-- Hero -->
<section class="l-hero">
    <div style="margin-bottom:32px">
        <div class="l-hero-brand">
            <div class="l-hero-brand-icon">
                <img src="/icons/electus-120.png" alt="Electus">
            </div>
            <span class="l-hero-brand-name">Electus</span>
        </div>
    </div>
    <div class="l-hero-badge">Sistema di votazione<br>professionale</div>
    <h1>Vota. Gestisci.<br>Pubblica i risultati.</h1>
    <p>Electus è la piattaforma completa per elezioni, sondaggi e votazioni. Multi-turno, multi-modello, dalla registrazione degli elettori al risultato finale.</p>
    <div class="l-hero-actions">
        <a href="/richiedi-accesso.php" class="btn-primary">Richiedi accesso</a>
        <a href="/admin/" class="btn-outline">Accedi all'area admin</a>
    </div>
    <a href="#funzionalita" style="display:inline-block;margin-top:28px;color:rgba(255,255,255,.75);font-size:.85rem;text-decoration:underline;">Scopri le funzionalità &darr;</a>
</section>

<!-- Stats bar -->
<div class="l-stats">
    <div class="l-stats-inner">
        <div class="l-stat">
            <div class="l-stat-n">6+</div>
            <div class="l-stat-l">Modelli di voto</div>
        </div>
        <div class="l-stat">
            <div class="l-stat-n">3</div>
            <div class="l-stat-l">Lingue (IT / EN / FR)</div>
        </div>
        <div class="l-stat">
            <div class="l-stat-n">∞</div>
            <div class="l-stat-l">Turni per evento</div>
        </div>
        <div class="l-stat">
            <div class="l-stat-n">100%</div>
            <div class="l-stat-l">Voto anonimo</div>
        </div>
    </div>
</div>

<!-- Features -->
<div id="funzionalita" class="l-section">
    <h2 class="l-section-title">Tutto quello che serve</h2>
    <p class="l-section-sub">Dalla gestione degli elettori alla pubblicazione dei risultati, Electus copre ogni fase del processo elettorale.</p>

    <div class="l-features">
        <div class="l-feat">
            <div class="l-feat-icon">
                <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
            </div>
            <h3>Pipeline multi-turno</h3>
            <p>Crea sequenze di turni con promozione automatica dei candidati per categoria. Configura soglie e modalità di avanzamento per ogni categoria.</p>
        </div>
        <div class="l-feat">
            <div class="l-feat-icon">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
            </div>
            <h3>Gestione elettori</h3>
            <p>Lista chiusa con token sicuri, auto-registrazione con verifica email, approvazione manuale. Voto anonimo garantito: il voto non è mai collegabile all'identità.</p>
        </div>
        <div class="l-feat">
            <div class="l-feat-icon">
                <svg viewBox="0 0 24 24"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
            </div>
            <h3>Risultati in tempo reale</h3>
            <p>Calcola, valida e pubblica i risultati. Esporta in CSV o JSON. Grafici a barre per ogni categoria con link pubblico direttamente condivisibile. API JSON pubblica per integrazioni esterne (dashboard, siti cliente).</p>
        </div>
        <div class="l-feat">
            <div class="l-feat-icon">
                <svg viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
            </div>
            <h3>Gestione candidati avanzata</h3>
            <p>Deduplica automatica, operazioni batch (escludi, elimina, unisci), importazione da CSV. Supporto round aperti con inserimento libero da parte degli elettori.</p>
        </div>
        <div class="l-feat">
            <div class="l-feat-icon">
                <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
            </div>
            <h3>Branding per evento</h3>
            <p>Logo personalizzato, tema grafico scelto tra 9 preset o con colori custom, terminologia configurabile (Categoria / Scelta / Premio / Seggio…).</p>
        </div>
        <div class="l-feat">
            <div class="l-feat-icon">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 010 20M12 2a15.3 15.3 0 000 20"/></svg>
            </div>
            <h3>Multilingua</h3>
            <p>Interfaccia admin e pagine pubbliche in italiano, inglese e francese. Ogni elettore sceglie la propria lingua al momento del voto.</p>
        </div>
    </div>
</div>

<hr class="l-divider">

<!-- Vote models -->
<div class="l-section">
    <h2 class="l-section-title">6 modelli di voto</h2>
    <p class="l-section-sub">Ogni votazione è diversa. Electus si adatta con sei modelli distinti, selezionabili per ogni turno.</p>
    <div class="l-models">
        <span class="l-model-chip">Singolo (1 voto per elettore)</span>
        <span class="l-model-chip">Multiplo (N preferenze)</span>
        <span class="l-model-chip">Borda (punti decrescenti)</span>
        <span class="l-model-chip">Aperto (candidature libere)</span>
        <span class="l-model-chip">Proporzionale (voti percentualizzati)</span>
        <span class="l-model-chip">Pesato (peso per elettore)</span>
    </div>
</div>

<hr class="l-divider">

<!-- How it works -->
<div class="l-section">
    <h2 class="l-section-title">Come funziona</h2>
    <p class="l-section-sub">Dalla creazione dell'evento al risultato finale in pochi passaggi.</p>
    <div class="l-steps">
        <div class="l-step">
            <div class="l-step-n">1</div>
            <h3>Crea l'evento</h3>
            <p>Definisci nome, slug pubblico, verifica email, branding e lingua. L'evento è subito accessibile online.</p>
        </div>
        <div class="l-step">
            <div class="l-step-n">2</div>
            <h3>Configura i turni</h3>
            <p>Aggiungi uno o più turni con modello di voto, categorie e candidati. Imposta la pipeline di promozione tra turni.</p>
        </div>
        <div class="l-step">
            <div class="l-step-n">3</div>
            <h3>Apri la votazione</h3>
            <p>Condividi il link pubblico (o per token privati). Gli elettori si registrano e votano dal browser, da qualsiasi dispositivo.</p>
        </div>
        <div class="l-step">
            <div class="l-step-n">4</div>
            <h3>Pubblica i risultati</h3>
            <p>Valida i voti, calcola i risultati, esporta CSV/JSON e pubblica la pagina pubblica con un clic.</p>
        </div>
    </div>
</div>

<!-- CTA -->
<div class="l-cta">
    <h2>Pronto a iniziare?</h2>
    <p>Richiedi un account per creare il tuo primo evento di voto.</p>
    <div class="l-hero-actions" style="justify-content:center">
        <a href="/richiedi-accesso.php" class="btn-primary">Richiedi accesso</a>
        <a href="/admin/" class="btn-outline">Accedi all'area admin</a>
    </div>
</div>

<!-- Footer -->
<?php require __DIR__ . '/templates/_footer.php' ?>

<script src="https://cdn.jsdelivr.net/npm/uikit@3/dist/js/uikit.min.js"></script>
</body>
</html>
