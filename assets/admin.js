/* ============================================================
   Ruota Fasi — pannello di modifica
   ============================================================ */

(function ($) {
    'use strict';

    const $fasi   = $('#rf-fasi');
    const box     = document.getElementById('rf-anteprima');
    const cursore = document.getElementById('rf-anteprima-progresso');
    const btnPlay = document.getElementById('rf-anteprima-riproduci');
    if (!box || !$fasi.length) return;

    let istanza = null;
    let timerAgg = null;
    let timerPlay = null;

    // ------------------------------------------------------------------
    // Lettura delle impostazioni dal form
    // ------------------------------------------------------------------
    function leggiConfig() {
        const cfg = {};
        document.querySelectorAll('[data-rf]').forEach(el => {
            const k = el.dataset.rf;
            if (el.type === 'checkbox')    cfg[k] = el.checked ? 1 : 0;
            else if (el.type === 'number') cfg[k] = el.value === '' ? (el.dataset.default === '' ? '' : 0) : parseFloat(el.value);
            else                           cfg[k] = el.value;
        });
        cfg.fasi = [];
        $fasi.children('li').each(function () {
            const titolo = $(this).find('.rf-tit-input').val().trim();
            if (titolo) cfg.fasi.push({ numero: $(this).find('.rf-num-input').val().trim(), titolo: titolo });
        });
        return cfg;
    }

    // ------------------------------------------------------------------
    // Anteprima
    // ------------------------------------------------------------------
    function aggiornaAnteprima() {
        const cfg = leggiConfig();
        const p = istanza ? istanza.p : parseFloat(cursore.value) || 0;
        if (istanza) istanza.distruggi();
        istanza = null;
        box.innerHTML = '';
        if (!cfg.fasi.length) {
            box.innerHTML = '<p class="rf-vuota">Aggiungi almeno una fase con un titolo per vedere l\'anteprima.</p>';
            return;
        }
        istanza = window.RuotaFasi.crea(box, cfg, { anteprima: true, progresso: p });
        cursore.max = Math.max(0, cfg.fasi.length - 1);
        cursore.value = istanza.p;
    }

    function pianifica() {
        clearTimeout(timerAgg);
        timerAgg = setTimeout(aggiornaAnteprima, 60);
    }

    cursore.addEventListener('input', () => {
        fermaRiproduzione();
        if (istanza) istanza.imposta(parseFloat(cursore.value));
    });

    // Riproduzione: passa da una fase all'altra come farebbe il sito
    function fermaRiproduzione() {
        clearInterval(timerPlay);
        timerPlay = null;
        btnPlay.textContent = '▶ Riproduci';
    }
    btnPlay.addEventListener('click', () => {
        if (timerPlay) { fermaRiproduzione(); return; }
        if (!istanza) return;
        btnPlay.textContent = '■ Ferma';
        const cfg = leggiConfig();
        const passo = () => {
            if (!istanza) return;
            const prossima = istanza.attiva + 1 >= istanza.n ? 0 : istanza.attiva + 1;
            istanza.anima(prossima, cfg.durata);
            cursore.value = prossima;
        };
        passo();
        timerPlay = setInterval(passo, Math.max(900, cfg.durata + 700));
    });

    // Sfondo dell'anteprima (solo visivo, non salvato)
    const sfondo = document.getElementById('rf-anteprima-sfondo');
    if (sfondo) {
        const applicaSfondo = () => {
            const c = box.closest('.rf-anteprima-cornice');
            c.style.background = sfondo.value;
            try { localStorage.setItem('rf-sfondo-anteprima', sfondo.value); } catch (e) { /* ignorato */ }
        };
        try { const salvato = localStorage.getItem('rf-sfondo-anteprima'); if (salvato) sfondo.value = salvato; } catch (e) { /* ignorato */ }
        sfondo.addEventListener('input', applicaSfondo);
        applicaSfondo();
    }

    // Larghezza desktop / mobile
    document.querySelectorAll('.rf-dispositivo').forEach(b => {
        b.addEventListener('click', () => {
            document.querySelectorAll('.rf-dispositivo').forEach(x => x.classList.toggle('attivo', x === b));
            box.style.width = b.dataset.larghezza ? b.dataset.larghezza + 'px' : '';
            box.closest('.rf-anteprima-cornice').classList.toggle('mobile', !!b.dataset.larghezza);
            aggiornaAnteprima();
        });
    });

    // ------------------------------------------------------------------
    // Campi
    // ------------------------------------------------------------------
    const impostazioni = document.querySelector('.rf-impostazioni');

    // Cursore ↔ campo numerico
    impostazioni.addEventListener('input', e => {
        const t = e.target;
        if (t.dataset.collegato) {
            const n = document.getElementById(t.dataset.collegato);
            n.value = t.value;
        } else if (t.type === 'number') {
            const riga = t.closest('.rf-numero'); // i campi mobile non hanno il cursore
            const r = riga && riga.querySelector('input[type="range"]');
            if (r) r.value = t.value;
        }
        if (t.closest('.rf-colore')) aggiornaCampione(t.closest('.rf-colore'), t);
        pianifica();
    });
    impostazioni.addEventListener('change', pianifica);

    // Colori: campione + selettore nativo (che scrive un esadecimale)
    function aggiornaCampione(campo, sorgente) {
        const testo = campo.querySelector('input[type="text"]');
        if (sorgente && sorgente.type === 'color') testo.value = sorgente.value;
        campo.querySelector('.rf-campione span').style.background = testo.value;
    }

    // Schede
    document.querySelectorAll('.rf-scheda').forEach(s => {
        s.addEventListener('click', () => {
            document.querySelectorAll('.rf-scheda').forEach(x => x.classList.toggle('attiva', x === s));
            document.querySelectorAll('.rf-gruppo').forEach(g => { g.hidden = g.dataset.gruppo !== s.dataset.gruppo; });
        });
    });

    // Ripristino dei valori iniziali
    document.getElementById('rf-ripristina').addEventListener('click', () => {
        if (!window.confirm('Riportare tutte le impostazioni di aspetto e movimento ai valori iniziali? Le fasi non vengono toccate.')) return;
        document.querySelectorAll('[data-rf]').forEach(el => {
            if (el.type === 'checkbox') el.checked = el.dataset.default === '1';
            else el.value = el.dataset.default;
            if (el.type === 'number') {
                const riga = el.closest('.rf-numero');
                const r = riga && riga.querySelector('input[type="range"]');
                if (r) r.value = el.value;
            }
            if (el.closest('.rf-colore')) aggiornaCampione(el.closest('.rf-colore'));
        });
        aggiornaAnteprima();
    });

    // ------------------------------------------------------------------
    // Fasi: aggiungi, elimina, riordina
    // ------------------------------------------------------------------
    function rinumera() {
        const formato = (document.querySelector('[data-rf="numerazione"]') || {}).value;
        $fasi.children('li').each(function (i) {
            const auto = formato === '1' ? String(i + 1) : String(i + 1).padStart(2, '0');
            $(this).find('.rf-num-auto').text(auto);
            $(this).find('.rf-num-input').attr('placeholder', auto);
        });
    }

    $('#rf-aggiungi-fase').on('click', () => {
        const $nuova = $fasi.children('li').first().clone();
        $nuova.find('input, textarea').val('');
        $fasi.append($nuova);
        rinumera();
        $nuova.find('.rf-tit-input').trigger('focus');
        pianifica();
    });

    $fasi.on('click', '.rf-elimina', function () {
        const $riga = $(this).closest('li');
        if ($fasi.children('li').length === 1) { $riga.find('input, textarea').val(''); }
        else $riga.remove();
        rinumera();
        pianifica();
    });
    $fasi.on('click', '.rf-su', function () {
        const $riga = $(this).closest('li');
        $riga.prev().before($riga);
        rinumera(); pianifica();
    });
    $fasi.on('click', '.rf-giu', function () {
        const $riga = $(this).closest('li');
        $riga.next().after($riga);
        rinumera(); pianifica();
    });
    $fasi.on('input', 'input, textarea', pianifica);

    if ($.fn.sortable) {
        $fasi.sortable({ handle: '.rf-maniglia', axis: 'y', update: () => { rinumera(); pianifica(); } });
    }

    $(document).on('change', '[data-rf="numerazione"]', rinumera);

    // Le fasi vuote non vengono salvate: avviso prima di pubblicare senza fasi
    $('#post').on('submit', () => {
        if (!leggiConfig().fasi.length) {
            return window.confirm('La ruota non ha fasi con un titolo: nel sito non verrà mostrata. Salvare comunque?');
        }
    });

    // ------------------------------------------------------------------
    // Copia dello shortcode
    // ------------------------------------------------------------------
    $(document).on('click', '.rf-copia', function () {
        const el = this;
        const testo = el.textContent;
        const fatto = () => {
            el.classList.add('copiato');
            el.dataset.originale = testo;
            el.textContent = 'Copiato!';
            setTimeout(() => { el.textContent = testo; el.classList.remove('copiato'); }, 1200);
        };
        if (navigator.clipboard) navigator.clipboard.writeText(testo).then(fatto, () => {});
        else {
            const r = document.createRange(); r.selectNodeContents(el);
            const s = window.getSelection(); s.removeAllRanges(); s.addRange(r);
            document.execCommand('copy'); fatto();
        }
    });

    rinumera();
    aggiornaAnteprima();
})(jQuery);
