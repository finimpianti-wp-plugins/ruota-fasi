/* ============================================================
   Ruota Fasi — motore
   Disegna la ruota in SVG e la fa girare con lo scroll: quando la
   ruota arriva in posizione la pagina si ferma, lo scroll verso il
   basso la fa avanzare e verso l'alto tornare indietro; finite le
   fasi la pagina riprende a scorrere.
   Usato sia nel sito sia nell'anteprima del pannello.
   ============================================================ */

(function () {
    'use strict';

    const NS = 'http://www.w3.org/2000/svg';
    const ridotto = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };

    const GESTO_NUOVO   = 200; // ms senza eventi di scroll = nuovo gesto
    const DELTA_MAX     = 120; // px: limite per singolo evento (smorza l'inerzia dei trackpad)
    const SOGLIA_SCATTO = 40;  // px di scroll accumulati per passare di fase nella rotazione a scatti
    const SOGLIA_FASE   = 0.12; // frazione di fase oltre la quale, a gesto finito, si passa alla fase successiva
    const ASSORBI_MS    = 400; // finite le fasi, per quanto lo scroll viene ancora "assorbito" (inerzia del trackpad)
    const PASSAGGIO_MS  = 600; // dopo un evento bloccato, per quanto è il plugin a far scorrere la pagina (vedi wheel)

    const easing = t => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
    const limita = (v, min, max) => Math.max(min, Math.min(max, v));

    function svg(tag, attr, padre) {
        const el = document.createElementNS(NS, tag);
        for (const k in attr) el.setAttribute(k, attr[k]);
        if (padre) padre.appendChild(el);
        return el;
    }

    function html(tag, classe, padre) {
        const el = document.createElement(tag);
        if (classe) el.className = classe;
        if (padre) padre.appendChild(el);
        return el;
    }

    function attributi(el, attr) {
        for (const k in attr) el.setAttribute(k, attr[k]);
    }

    function numeroFase(cfg, i) {
        const f = cfg.fasi[i];
        if (f.numero) return f.numero;
        return cfg.numerazione === '01' ? String(i + 1).padStart(2, '0') : String(i + 1);
    }

    // Scroll immediato anche se il tema imposta "scroll-behavior: smooth"
    function scorriSubito(y) {
        try { window.scrollTo({ top: y, left: window.pageXOffset, behavior: 'instant' }); }
        catch (e) { window.scrollTo(window.pageXOffset, y); }
    }

    // Una sola ruota alla volta può tenere ferma la pagina
    let ruotaAttiva = null;

    // Impostazioni effettive: su mobile i valori "chiave_m" non vuoti sostituiscono quelli desktop
    function configEfficace(base, mobile) {
        const c = Object.assign({}, base);
        if (mobile) {
            for (const k in base) {
                if (k.slice(-2) === '_m' && base[k] !== '' && base[k] !== null && base[k] !== undefined) c[k.slice(0, -2)] = base[k];
            }
        }
        return c;
    }

    class Ruota {
        /**
         * @param {HTMLElement} root  contenitore .rf-ruota
         * @param {Object} cfg         impostazioni (vedi includes/schema.php) + fasi
         * @param {Object} opz         { anteprima: true } nel pannello: nessun evento di pagina
         */
        constructor(root, cfg, opz) {
            this.root   = root;
            this.opz    = opz || {};
            this.base   = cfg;                     // impostazioni salvate (desktop + "_m" mobile)
            this.mobile = this.eMobile();
            this.cfg    = configEfficace(cfg, this.mobile); // impostazioni in uso (ricalcolate in misura())
            this.n      = cfg.fasi.length;
            this.p      = limita(this.opz.progresso || 0, 0, this.n - 1); // posizione disegnata (0 = prima fase in cima)
            this.obiettivo = this.p;                                      // posizione verso cui sta girando
            this.attiva = -1;
            this.pulizia = [];

            this.costruisci();
            this.misura();
            this.disegna(false);
            if (!this.opz.anteprima) this.collega();
        }

        // ---------------------------------------------------------------------
        // Struttura
        // ---------------------------------------------------------------------
        costruisci() {
            const cfg = this.cfg;
            this.root.classList.add('rf-pronta');

            this.stage = html('div', 'rf-stage', this.root);

            this.etichetta = html('div', 'rf-attiva', this.stage);
            this.etichetta.setAttribute('aria-live', 'polite');
            // numero e titolo in una riga: il titolo si allinea al numero dentro la riga
            this.riga  = html('div', 'rf-attiva-riga', this.etichetta);
            this.etNum = html('span', 'rf-attiva-num', this.riga);
            this.etTit = html('span', 'rf-attiva-tit', this.riga);

            this.svg = svg('svg', { class: 'rf-svg', 'aria-hidden': 'true', focusable: 'false' }, this.stage);
            this.gRot       = svg('g', { class: 'rf-rot' }, this.svg);
            this.scala      = svg('circle', { class: 'rf-scala', fill: 'none' }, this.gRot);
            this.gFasi      = svg('g', { class: 'rf-fasi' }, this.gRot);
            this.cerchio    = svg('circle', { class: 'rf-cerchio', fill: 'none' }, this.svg);
            this.indicatore = svg('rect', { class: 'rf-indicatore' }, this.svg);

            this.fasi = cfg.fasi.map((f, i) => {
                const g     = svg('g', { class: 'rf-fase', 'data-i': i }, this.gFasi);
                const tacca = svg('rect', { class: 'rf-tacca' }, g);
                const testo = svg('text', { class: 'rf-num', 'text-anchor': 'middle' }, g);
                svg('tspan', {}, testo).textContent = numeroFase(cfg, i);
                let tTit = null;
                if (cfg.titoli_inattivi) {
                    tTit = svg('tspan', { class: 'rf-num-tit' }, testo);
                    tTit.textContent = ' ' + f.titolo.replace(/\s*\n\s*/g, ' ');
                }
                return { g, tacca, testo, tTit };
            });
        }

        // ---------------------------------------------------------------------
        // Geometria (ricalcolata a ogni ridimensionamento).
        // Le dimensioni dei testi sono sempre quelle impostate; il cerchio si
        // adatta alla larghezza del contenitore senza mai uscirne.
        // ---------------------------------------------------------------------
        // Mobile: nel sito in base alla larghezza della finestra (soglia "Mobile fino a"),
        // nell'anteprima del pannello in base alla larghezza del riquadro (pulsante Mobile)
        eMobile() {
            if (this.opz.anteprima) return (this.root.clientWidth || 800) < 600;
            const soglia = this.base.punto_mobile || 767;
            return window.matchMedia ? window.matchMedia('(max-width: ' + soglia + 'px)').matches : window.innerWidth <= soglia;
        }

        misura() {
            this.mobile = this.eMobile();
            this.cfg = configEfficace(this.base, this.mobile);
            this.root.classList.toggle('rf-mobile', this.mobile);
            const cfg = this.cfg;
            const W   = this.stage.clientWidth || this.root.clientWidth || 800;
            const dimNumeri = cfg.dim_numeri;

            // Etichetta della fase attiva (variabili sul contenitore, usate anche nel CSS)
            const v = this.root.style;
            v.setProperty('--rf-dim-num', cfg.dim_numero + 'px');
            v.setProperty('--rf-dim-tit', cfg.dim_titolo + 'px');
            v.setProperty('--rf-col-num', cfg.colore_numero);
            v.setProperty('--rf-col-tit', cfg.colore_titolo);
            v.setProperty('--rf-peso-num', cfg.peso_numero);
            v.setProperty('--rf-peso-tit', cfg.peso_titolo);
            v.setProperty('--rf-tit-max', Math.min(cfg.titolo_max, Math.max(80, W - cfg.dim_numero * 2)) + 'px');
            v.setProperty('--rf-tit-lh', cfg.interlinea_titolo);
            v.setProperty('--rf-gap', cfg.spazio_titolo + 'px');
            v.setProperty('--rf-tit-allinea', { alto: 'flex-start', centro: 'center', basso: 'flex-end' }[cfg.allinea_titolo] || 'flex-end');
            v.background = cfg.colore_sfondo || 'transparent';
            this.etTit.dataset.allinea = cfg.allinea_titolo;

            // Diametro: % del contenitore, tra minimo e massimo, mai più largo del
            // contenitore (lasciando spazio ai numeri ruotati sui lati)
            const margineLati = dimNumeri * 0.9;
            let D = Math.max(W * cfg.diametro / 100, cfg.diametro_min);
            D = Math.min(D, cfg.diametro_max, W - 2 * margineLati);
            D = Math.max(D, 40);
            const R = D / 2;

            // Altezza reale dell'etichetta più alta (i titoli lunghi vanno a capo)
            const altezzaEt = this.misuraEtichetta();

            const fuori = Math.max(cfg.tacche_h, cfg.indicatore_h / 2, Math.max(0, cfg.scala_distanza) + 3) + 8 + dimNumeri * 1.15;
            const etich = cfg.posizione === 'sopra' ? altezzaEt + cfg.spazio_sopra + cfg.indicatore_h / 2 : 0;
            const top   = Math.ceil(Math.max(fuori, etich) + 4);

            const visibile = D * cfg.visibile / 100;
            const H  = Math.ceil(top + visibile + (cfg.visibile >= 100 ? fuori : 0));
            const cx = W / 2;
            const cy = top + R;

            this.geo = { W, H, R, cx, cy, top };

            attributi(this.svg, { width: W, height: H, viewBox: '0 0 ' + W + ' ' + H });
            this.stage.style.height = H + 'px';

            // Cerchio principale
            attributi(this.cerchio, { cx, cy, r: R, stroke: cfg.colore_cerchio, 'stroke-width': cfg.spessore_cerchio });
            this.cerchio.style.display = cfg.spessore_cerchio > 0 ? '' : 'none';

            // Scala graduata (gira con la ruota)
            const den = cfg.scala_densita;
            const st = {
                puntini:  { w: 2, dash: '0 ' + den, cap: 'round' },
                trattini: { w: 1, dash: (den * 0.6) + ' ' + (den * 0.4), cap: 'butt' },
                tacche:   { w: 6, dash: '1 ' + (den - 1), cap: 'butt' },
            }[cfg.scala];
            this.scala.style.display = st ? '' : 'none';
            if (st) {
                attributi(this.scala, { cx, cy, r: Math.max(1, R + cfg.scala_distanza), stroke: cfg.colore_scala,
                    'stroke-width': st.w, 'stroke-dasharray': st.dash, 'stroke-linecap': st.cap });
            }

            // Tacca fissa della fase attiva, in cima
            attributi(this.indicatore, { x: cx - cfg.indicatore_l / 2, y: cy - R - cfg.indicatore_h / 2,
                width: cfg.indicatore_l, height: cfg.indicatore_h, fill: cfg.colore_indicatore, rx: Math.min(1, cfg.indicatore_l / 4) });

            // Fasi: tacca appena fuori dal cerchio e numero sopra, ruotati sulla circonferenza
            const yTacca = cy - R - cfg.tacche_h - Math.max(0, cfg.spessore_cerchio / 2);
            const yTesto = yTacca - 8;
            this.fasi.forEach((f, i) => {
                f.g.setAttribute('transform', 'rotate(' + (i * cfg.passo) + ' ' + cx + ' ' + cy + ')');
                attributi(f.tacca, { x: cx - cfg.tacche_l / 2, y: yTacca, width: cfg.tacche_l, height: cfg.tacche_h, fill: cfg.colore_tacche });
                attributi(f.testo, { x: cx, y: yTesto, fill: cfg.colore_numeri, 'font-size': dimNumeri });
                if (f.tTit) f.tTit.setAttribute('font-size', Math.max(9, dimNumeri * 0.5));
            });

            // Posizione dell'etichetta
            const e = this.etichetta.style;
            if (cfg.posizione === 'sopra') {
                e.top = '0';
                e.height = Math.max(0, cy - R - cfg.indicatore_h / 2 - cfg.spazio_sopra) + 'px';
                this.etichetta.classList.remove('rf-dentro');
            } else {
                e.top = (cy - R + cfg.indicatore_h / 2 + cfg.spazio_sopra) + 'px';
                e.height = 'auto';
                this.etichetta.classList.add('rf-dentro');
            }
        }

        // Altezza massima dell'etichetta tra tutte le fasi, con le dimensioni reali dei testi
        misuraEtichetta() {
            const e = this.etichetta;
            const prima = [this.etNum.textContent, this.etTit.textContent, e.style.height];
            e.style.height = 'auto';
            let max = 0;
            this.cfg.fasi.forEach((f, i) => {
                this.etNum.textContent = numeroFase(this.cfg, i);
                this.etTit.textContent = f.titolo;
                max = Math.max(max, e.offsetHeight);
            });
            [this.etNum.textContent, this.etTit.textContent, e.style.height] = prima;
            return max || this.cfg.dim_numero * 1.1;
        }

        // ---------------------------------------------------------------------
        // Disegno della posizione corrente
        // ---------------------------------------------------------------------
        disegna(animaEtichetta) {
            const cfg = this.cfg;
            const { cx, cy } = this.geo;
            this.gRot.setAttribute('transform', 'rotate(' + (-this.p * cfg.passo) + ' ' + cx + ' ' + cy + ')');

            const diss = cfg.dissolvenza / 100;
            this.fasi.forEach((f, i) => {
                const d = Math.abs(i - this.p);
                const lontananza = limita(1 - diss * Math.max(0, d - 1), 0, 1);
                // vicino alla cima il numero sparisce: lo mostra l'etichetta della fase attiva
                const vicino = limita((d - 0.15) / 0.45, 0, 1);
                f.testo.style.opacity = (vicino * lontananza).toFixed(3);
                f.tacca.style.opacity = lontananza.toFixed(3);
            });

            const attiva = limita(Math.round(this.p), 0, this.n - 1);
            if (attiva !== this.attiva) {
                const prima = this.attiva;
                this.attiva = attiva;
                this.aggiornaEtichetta(animaEtichetta && prima !== -1);
                this.root.dispatchEvent(new CustomEvent('rf:fase', { detail: { indice: attiva } }));
            }
        }

        aggiornaEtichetta(anima) {
            const imposta = () => {
                this.etNum.textContent = numeroFase(this.cfg, this.attiva);
                this.etTit.textContent = this.cfg.fasi[this.attiva].titolo;
            };
            clearTimeout(this.timerEt);
            if (!anima || ridotto.matches) { imposta(); this.etichetta.classList.remove('rf-cambio'); return; }
            this.etichetta.classList.add('rf-cambio');
            this.timerEt = setTimeout(() => { imposta(); this.etichetta.classList.remove('rf-cambio'); }, 170);
        }

        // Posiziona direttamente (anteprima)
        imposta(p) {
            this.ferma();
            this.p = this.obiettivo = limita(p, -0.4, this.n - 0.6);
            this.disegna(true);
        }

        ferma() {
            cancelAnimationFrame(this.raf);
            this.inseguendo = false;
        }

        // Rotazione animata verso una posizione
        anima(verso, durata) {
            this.ferma();
            this.obiettivo = verso;
            const da = this.p;
            const distanza = Math.abs(verso - da);
            if (ridotto.matches || durata <= 0 || distanza < 0.001) { this.p = verso; this.disegna(true); return; }
            const tot = durata * Math.min(1 + (distanza - 1) * 0.35, 2.5);
            const t0  = performance.now();
            const passo = t => {
                const k = Math.min(1, (t - t0) / tot);
                this.p = da + (verso - da) * easing(k);
                this.disegna(true);
                if (k < 1) this.raf = requestAnimationFrame(passo);
            };
            this.raf = requestAnimationFrame(passo);
        }

        // Inseguimento morbido di un obiettivo che cambia di continuo (scroll)
        insegui(obiettivo) {
            this.obiettivo = obiettivo;
            if (ridotto.matches) { this.ferma(); this.p = obiettivo; this.disegna(true); return; }
            if (this.inseguendo) return;
            cancelAnimationFrame(this.raf);
            this.inseguendo = true;
            const passo = () => {
                const diff = this.obiettivo - this.p;
                if (Math.abs(diff) < 0.002) {
                    this.p = this.obiettivo;
                    this.disegna(true);
                    this.inseguendo = false;
                    return;
                }
                this.p += diff * 0.2;
                this.disegna(true);
                this.raf = requestAnimationFrame(passo);
            };
            this.raf = requestAnimationFrame(passo);
        }

        // ---------------------------------------------------------------------
        // Eventi di pagina
        // ---------------------------------------------------------------------
        ascolta(el, ev, fn, opz) {
            el.addEventListener(ev, fn, opz);
            this.pulizia.push(() => el.removeEventListener(ev, fn, opz));
        }

        collega() {
            if ('ResizeObserver' in window) {
                let ultimaW = 0;
                const ro = new ResizeObserver(() => {
                    const w = this.root.clientWidth;
                    if (Math.abs(w - ultimaW) < 1) return;
                    ultimaW = w;
                    this.misura();
                    this.disegna(false);
                    if (this.bloccata) scorriSubito(this.yBlocco = this.puntoBlocco());
                });
                ro.observe(this.root);
                this.pulizia.push(() => ro.disconnect());
            }

            // Passaggio desktop ↔ mobile (es. rotazione del tablet) anche senza cambio di larghezza del contenitore
            this.ascolta(window, 'resize', () => {
                if (this.eMobile() === this.mobile) return;
                this.misura();
                this.disegna(false);
                if (this.bloccata) scorriSubito(this.yBlocco = this.puntoBlocco());
            });

            // Nell'editor di Elementor la pagina non viene bloccata: la ruota resta sulla prima fase
            const editor = document.body.classList.contains('elementor-editor-active')
                || (window.elementorFrontend && elementorFrontend.isEditMode && elementorFrontend.isEditMode());
            if (editor) return;

            this.collegaScroll();
        }

        // ---------------------------------------------------------------------
        // Blocco della pagina
        // ---------------------------------------------------------------------

        // Posizione di scroll (in px dall'inizio della pagina) in cui la pagina si ferma
        puntoBlocco() {
            const cfg = this.cfg;
            const r   = this.stage.getBoundingClientRect();
            const y   = window.pageYOffset;
            const vh  = window.innerHeight;
            let punto;
            if (cfg.allineamento === 'alto')        punto = r.top + y - cfg.distanza_alto;
            else if (cfg.allineamento === 'centro') punto = r.top + y - (vh - r.height) / 2;
            else                                    punto = r.bottom + y - vh;           // ruota intera, in fondo allo schermo
            const massimo = document.documentElement.scrollHeight - vh;
            return Math.round(limita(punto, 0, Math.max(0, massimo)));
        }

        haFasiVerso(giu) {
            return giu ? this.obiettivo < this.n - 1 - 0.01 : this.obiettivo > 0.01;
        }

        blocca(punto) {
            this.bloccata = true;
            this.yBlocco = punto;
            ruotaAttiva = this;
            this.root.classList.add('rf-in-rotazione');
            // Sui dispositivi touch i nuovi swipe vanno solo alla ruota: senza questo Safari
            // (iPhone) può far scorrere la pagina anche se lo scroll viene annullato
            document.documentElement.classList.add('rf-pagina-ferma');
            if (Math.abs(window.pageYOffset - punto) > 0.5) scorriSubito(punto);
        }

        sblocca() {
            this.bloccata = false;
            this.bordoDa = null;
            if (ruotaAttiva === this) ruotaAttiva = null;
            this.root.classList.remove('rf-in-rotazione');
            document.documentElement.classList.remove('rf-pagina-ferma');
            this.assesta(0);
        }

        // Con la rotazione continua, a gesto finito la ruota si ferma su una fase: quella
        // successiva nella direzione del gesto se si è mossa almeno un po' (anche uno
        // swipe corto su uno schermo alto fa avanzare), altrimenti quella di partenza
        assesta(ritardo) {
            if (this.cfg.rotazione !== 'continua') return;
            clearTimeout(this.timerAssesta);
            const fine = () => {
                const mosso = this.obiettivo - (this.inizioGesto ?? this.obiettivo);
                let f = Math.round(this.obiettivo);
                if (mosso > SOGLIA_FASE)       f = Math.ceil(this.obiettivo - 0.001);
                else if (mosso < -SOGLIA_FASE) f = Math.floor(this.obiettivo + 0.001);
                f = limita(f, 0, this.n - 1);
                this.inizioGesto = f;
                if (Math.abs(f - this.obiettivo) > 0.001 || Math.abs(f - this.p) > 0.001) this.anima(f, this.cfg.durata * 0.6);
            };
            if (ritardo) this.timerAssesta = setTimeout(fine, ritardo); else fine();
        }

        /**
         * Gestisce un movimento di scroll di d px (positivo = verso il basso) da
         * rotella, trackpad o dito. Ritorna true se il movimento è stato usato
         * dalla ruota (la pagina allora non deve muoversi).
         */
        gestisci(d, tempo) {
            if (ruotaAttiva && ruotaAttiva !== this) return false;
            const giu = d > 0;
            const nuovoGesto = tempo - (this.ultimoEvento || 0) > GESTO_NUOVO;
            this.ultimoEvento = tempo;
            if (nuovoGesto) {
                this.accumulo = 0;
                this.scattoNelGesto = false;
                if (!this.inseguendo) this.inizioGesto = Math.round(this.obiettivo);
            }

            if (!this.bloccata) {
                // La pagina si ferma se questo movimento raggiunge o supera il punto di
                // blocco e la ruota ha ancora fasi in quella direzione
                if (!this.haFasiVerso(giu)) return false;
                const y = window.pageYOffset;
                const punto = this.puntoBlocco();
                const raggiunge = giu ? (y <= punto + 1 && y + d >= punto - 1) : (y >= punto - 1 && y + d <= punto + 1);
                if (!raggiunge) return false;
                this.blocca(punto);
                this.giraDi(y + d - punto, true);
                return true;
            }

            // Pagina ferma: finite le fasi in questa direzione la pagina riparte.
            // Per un istante lo scroll viene ancora assorbito, così l'inerzia del gesto
            // che ha portato all'ultima fase non fa scappare la pagina; poi riparte anche
            // se l'utente continua a scorrere senza pause.
            if (!this.haFasiVerso(giu)) {
                if (this.bordoDa == null) this.bordoDa = tempo;
                if (nuovoGesto || tempo - this.bordoDa > ASSORBI_MS) { this.sblocca(); return false; }
                return true;
            }
            this.bordoDa = null;
            this.giraDi(d, nuovoGesto);
            return true;
        }

        // Fa girare la ruota in base a d px di scroll
        giraDi(d, nuovoGesto) {
            const cfg = this.cfg;

            if (cfg.rotazione === 'scatti') {
                // un gesto = una fase
                this.accumulo = (this.accumulo || 0) + d;
                if (this.scattoNelGesto || Math.abs(this.accumulo) < SOGLIA_SCATTO) return;
                this.scattoNelGesto = true;
                this.anima(limita(Math.round(this.obiettivo) + (this.accumulo > 0 ? 1 : -1), 0, this.n - 1), cfg.durata);
                return;
            }

            const perFase = window.innerHeight * cfg.scroll_fase / 100;
            d = limita(d, -DELTA_MAX, DELTA_MAX);
            this.insegui(limita(this.obiettivo + d / perFase, 0, this.n - 1));
            this.assesta(160);
        }

        // Scorrimento morbido della pagina fatto dal plugin (vedi gestione della rotella)
        scorriPagina(d) {
            const massimo = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
            const base = this.animPagina ? this.yPagina : window.pageYOffset;
            this.yPagina = limita(base + d, 0, massimo);
            if (this.animPagina) return;
            const passo = () => {
                const y = window.pageYOffset;
                const diff = this.yPagina - y;
                if (Math.abs(diff) < 1 || this.bloccata) {
                    if (!this.bloccata) scorriSubito(this.yPagina);
                    this.animPagina = null;
                    return;
                }
                scorriSubito(y + diff * 0.3);
                this.animPagina = requestAnimationFrame(passo);
            };
            this.animPagina = requestAnimationFrame(passo);
        }

        collegaScroll() {
            // Posizione iniziale: se la pagina è già oltre il punto di blocco (es. ricarica
            // a metà pagina) la ruota è già all'ultima fase
            this.p = this.obiettivo = window.pageYOffset > this.puntoBlocco() + 1 ? this.n - 1 : 0;
            this.disegna(false);
            this.ultimoY = window.pageYOffset;

            // Rotella e trackpad
            this.ascolta(window, 'wheel', e => {
                if (e.ctrlKey || e.defaultPrevented) return; // zoom
                let d = e.deltaY;
                if (e.deltaMode === 1) d *= 32;
                else if (e.deltaMode === 2) d *= window.innerHeight;
                if (!d || Math.abs(e.deltaX) > Math.abs(d)) return;
                if (this.gestisci(d, e.timeStamp)) {
                    if (e.cancelable) e.preventDefault();
                    this.ultimoBloccato = e.timeStamp;
                    return;
                }
                // Chrome ed Edge raggruppano gli eventi della rotella in una "sequenza" che si
                // chiude solo dopo una pausa o un movimento del mouse: se la sequenza era
                // iniziata mentre la ruota bloccava la pagina, il browser non la fa scorrere
                // nemmeno quando il blocco finisce. In quel caso la pagina la muove il plugin.
                if (this.ultimoBloccato != null && e.timeStamp - this.ultimoBloccato < PASSAGGIO_MS && e.cancelable) {
                    e.preventDefault();
                    this.ultimoBloccato = e.timeStamp;
                    this.scorriPagina(d);
                }
            }, { passive: false });

            // Dito (telefono e tablet)
            let toccoY = null;
            let toccoBloccato = false; // in questo tocco la ruota ha già usato lo scroll
            this.ascolta(window, 'touchstart', e => {
                toccoY = e.touches.length === 1 ? e.touches[0].clientY : null;
                toccoBloccato = this.bloccata;
                this.ultimoEvento = 0; // ogni tocco è un nuovo gesto
            }, { passive: true });
            this.ascolta(window, 'touchmove', e => {
                if (toccoY === null || e.touches.length !== 1) return;
                const y = e.touches[0].clientY;
                const d = toccoY - y;
                toccoY = y;
                if (Math.abs(d) < 0.5) return;
                if (this.gestisci(d, e.timeStamp || performance.now())) {
                    toccoBloccato = true;
                    if (e.cancelable) e.preventDefault();
                    return;
                }
                // Finite le fasi a dito ancora appoggiato: il browser non riprende lo scroll
                // di un tocco già bloccato, quindi è il plugin a far scorrere la pagina col dito
                if (toccoBloccato) {
                    if (e.cancelable) e.preventDefault();
                    const massimo = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
                    scorriSubito(limita(window.pageYOffset + d, 0, massimo));
                }
            }, { passive: false });
            this.ascolta(window, 'touchend', () => { toccoY = null; toccoBloccato = false; }, { passive: true });

            // Tastiera: frecce, pagina su/giù, spazio (una fase per pressione)
            this.ascolta(window, 'keydown', e => {
                if (!this.bloccata || e.defaultPrevented || e.altKey || e.ctrlKey || e.metaKey) return;
                const t = e.target;
                if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
                const tasti = { ArrowDown: true, PageDown: true, ArrowUp: false, PageUp: false, ' ': !e.shiftKey, Spacebar: !e.shiftKey };
                if (!(e.key in tasti)) return;
                const giu = tasti[e.key];
                if (!this.haFasiVerso(giu)) { this.sblocca(); return; }
                e.preventDefault();
                this.anima(limita(Math.round(this.obiettivo) + (giu ? 1 : -1), 0, this.n - 1), this.cfg.durata);
            });

            // Movimenti non intercettati (barra di scorrimento, tasti, salti a un'ancora,
            // inerzia del dito): la pagina viene riportata al punto di blocco; i salti
            // lunghi vengono lasciati passare e la ruota va direttamente alla fine o all'inizio
            this.ascolta(window, 'scroll', () => {
                const y = window.pageYOffset;
                const salto = window.innerHeight * 0.75;
                if (this.bloccata) {
                    const diff = y - this.yBlocco;
                    if (Math.abs(diff) > 1) {
                        if (Math.abs(diff) > salto) { this.sblocca(); this.anima(diff > 0 ? this.n - 1 : 0, 0); }
                        else scorriSubito(this.yBlocco);
                    }
                } else if (!ruotaAttiva) {
                    const prima = this.ultimoY;
                    const giu = y > prima;
                    const punto = this.puntoBlocco();
                    // anche partendo esattamente dal punto di blocco (es. ruota già in posizione all'apertura)
                    const attraversa = giu ? (prima <= punto + 1 && y > punto + 1) : (prima >= punto - 1 && y < punto - 1);
                    if (attraversa && this.haFasiVerso(giu)) {
                        if (Math.abs(y - prima) > salto) this.anima(giu ? this.n - 1 : 0, 0);
                        else this.blocca(punto);
                    }
                }
                this.ultimoY = window.pageYOffset;
            }, { passive: true });

            this.ascolta(window, 'resize', () => {
                if (this.bloccata) scorriSubito(this.yBlocco = this.puntoBlocco());
            });

            this.pulizia.push(() => {
                if (ruotaAttiva === this) ruotaAttiva = null;
                document.documentElement.classList.remove('rf-pagina-ferma');
            });
        }

        distruggi() {
            this.ferma();
            cancelAnimationFrame(this.animPagina);
            clearTimeout(this.timerEt);
            clearTimeout(this.timerAssesta);
            this.pulizia.forEach(f => f());
            this.stage.remove();
            this.root.classList.remove('rf-pronta', 'rf-in-rotazione');
        }
    }

    function avvia() {
        document.querySelectorAll('.rf-ruota[data-config]:not(.rf-pronta)').forEach(el => {
            try {
                const cfg = JSON.parse(el.getAttribute('data-config'));
                if (cfg && cfg.fasi && cfg.fasi.length) el.ruotaFasi = new Ruota(el, cfg);
            } catch (err) {
                console.error('[ruota-fasi] impossibile inizializzare la ruota', el, err);
            }
        });
    }

    window.RuotaFasi = {
        crea: (el, cfg, opz) => new Ruota(el, cfg, opz),
        avvia: avvia,
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', avvia);
    else avvia();
})();
