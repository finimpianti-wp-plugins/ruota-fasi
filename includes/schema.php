<?php
defined('ABSPATH') || exit;

// =============================================================================
// IMPOSTAZIONI — un unico schema da cui derivano default, validazione e
// campi del pannello. Per aggiungere un'opzione basta aggiungerla qui
// (e usarla in assets/ruota.js).
// =============================================================================

function rf_gruppi() {
    return [
        'geometria' => 'Forma e dimensioni',
        'stile'     => 'Colori e tacche',
        'testi'     => 'Numeri e titoli',
        'movimento' => 'Movimento',
    ];
}

function rf_schema() {
    return [
        // --- Forma e dimensioni ---------------------------------------------
        'punto_mobile'      => ['gruppo' => 'geometria', 'tipo' => 'numero', 'default' => 767, 'min' => 320, 'max' => 1600, 'passo' => 1, 'unita' => 'px',
                                'etichetta' => 'Mobile fino a', 'aiuto' => 'Sotto questa larghezza di schermo si usano i valori "mobile" (767 px è la soglia mobile di Elementor).'],
        'diametro'          => ['gruppo' => 'geometria', 'tipo' => 'numero', 'default' => 68, 'min' => 20, 'max' => 100, 'passo' => 1, 'unita' => '%',
                                'etichetta' => 'Diametro', 'aiuto' => 'In percentuale della larghezza del contenitore. La ruota non esce mai dal contenitore.'],
        'diametro_min'      => ['gruppo' => 'geometria', 'tipo' => 'numero', 'default' => 300, 'min' => 100, 'max' => 2000, 'passo' => 10, 'unita' => 'px',
                                'etichetta' => 'Diametro minimo', 'aiuto' => 'Sugli schermi stretti la ruota non scende sotto questa misura (se il contenitore è più stretto, si adatta a lui).'],
        'diametro_max'      => ['gruppo' => 'geometria', 'tipo' => 'numero', 'default' => 1100, 'min' => 200, 'max' => 4000, 'passo' => 10, 'unita' => 'px',
                                'etichetta' => 'Diametro massimo'],
        'visibile'          => ['gruppo' => 'geometria', 'tipo' => 'numero', 'default' => 32, 'min' => 5, 'max' => 100, 'passo' => 1, 'unita' => '%',
                                'etichetta' => 'Parte visibile del cerchio', 'aiuto' => 'Altezza visibile in % del diametro: 50 = mezzo cerchio, 100 = cerchio intero.'],
        'passo'             => ['gruppo' => 'geometria', 'tipo' => 'numero', 'default' => 24, 'min' => 4, 'max' => 90, 'passo' => 1, 'unita' => '°',
                                'etichetta' => 'Distanza tra le fasi', 'aiuto' => 'Angolo tra una fase e la successiva lungo il cerchio.'],
        'spessore_cerchio'  => ['gruppo' => 'geometria', 'tipo' => 'numero', 'default' => 1, 'min' => 0, 'max' => 12, 'passo' => 0.5, 'unita' => 'px',
                                'etichetta' => 'Spessore del cerchio'],
        'spazio_sopra'      => ['gruppo' => 'geometria', 'tipo' => 'numero', 'default' => 20, 'min' => 0, 'max' => 200, 'passo' => 1, 'unita' => 'px',
                                'etichetta' => 'Spazio tra fase attiva e cerchio'],

        // --- Colori e tacche -------------------------------------------------
        'colore_sfondo'     => ['gruppo' => 'stile', 'tipo' => 'colore', 'default' => 'transparent', 'etichetta' => 'Sfondo',
                                'aiuto' => '"transparent" = nessuno sfondo: si vede quello della sezione Elementor.'],
        'colore_cerchio'    => ['gruppo' => 'stile', 'tipo' => 'colore', 'default' => '#012611', 'etichetta' => 'Cerchio'],
        'colore_indicatore' => ['gruppo' => 'stile', 'tipo' => 'colore', 'default' => '#012611', 'etichetta' => 'Tacca della fase attiva'],
        'indicatore_l'      => ['gruppo' => 'stile', 'tipo' => 'numero', 'default' => 4, 'min' => 0, 'max' => 40, 'passo' => 1, 'unita' => 'px', 'etichetta' => 'Tacca attiva: larghezza'],
        'indicatore_h'      => ['gruppo' => 'stile', 'tipo' => 'numero', 'default' => 16, 'min' => 0, 'max' => 80, 'passo' => 1, 'unita' => 'px', 'etichetta' => 'Tacca attiva: altezza'],
        'colore_tacche'     => ['gruppo' => 'stile', 'tipo' => 'colore', 'default' => 'rgba(1,38,17,.22)', 'etichetta' => 'Tacche delle altre fasi'],
        'tacche_l'          => ['gruppo' => 'stile', 'tipo' => 'numero', 'default' => 3, 'min' => 0, 'max' => 30, 'passo' => 1, 'unita' => 'px', 'etichetta' => 'Tacche: larghezza'],
        'tacche_h'          => ['gruppo' => 'stile', 'tipo' => 'numero', 'default' => 12, 'min' => 0, 'max' => 60, 'passo' => 1, 'unita' => 'px', 'etichetta' => 'Tacche: altezza'],
        'scala'             => ['gruppo' => 'stile', 'tipo' => 'scelta', 'default' => 'puntini',
                                'opzioni' => ['puntini' => 'Puntini', 'trattini' => 'Trattini', 'tacche' => 'Tacche radiali', 'nessuna' => 'Nessuna'],
                                'etichetta' => 'Scala graduata', 'aiuto' => 'Il secondo cerchio sottile che gira insieme alla ruota.'],
        'colore_scala'      => ['gruppo' => 'stile', 'tipo' => 'colore', 'default' => 'rgba(1,38,17,.45)', 'etichetta' => 'Scala graduata'],
        'scala_distanza'    => ['gruppo' => 'stile', 'tipo' => 'numero', 'default' => 5, 'min' => -80, 'max' => 80, 'passo' => 1, 'unita' => 'px',
                                'etichetta' => 'Distanza della scala dal cerchio', 'aiuto' => 'Positiva = fuori dal cerchio, negativa = dentro.'],
        'scala_densita'     => ['gruppo' => 'stile', 'tipo' => 'numero', 'default' => 6, 'min' => 2, 'max' => 40, 'passo' => 1, 'unita' => 'px',
                                'etichetta' => 'Distanza tra puntini / tacche'],

        // --- Numeri e titoli -------------------------------------------------
        'numerazione'       => ['gruppo' => 'testi', 'tipo' => 'scelta', 'default' => '01',
                                'opzioni' => ['01' => '01, 02, 03…', '1' => '1, 2, 3…'], 'etichetta' => 'Numerazione automatica',
                                'aiuto' => 'Puoi comunque scrivere un numero diverso per ogni fase.'],
        'posizione'         => ['gruppo' => 'testi', 'tipo' => 'scelta', 'default' => 'sopra',
                                'opzioni' => ['sopra' => 'Sopra il cerchio', 'dentro' => 'Dentro il cerchio'], 'etichetta' => 'Posizione della fase attiva'],
        'colore_numero'     => ['gruppo' => 'testi', 'tipo' => 'colore', 'default' => 'rgba(1,38,17,.52)', 'etichetta' => 'Numero della fase attiva'],
        'dim_numero'        => ['gruppo' => 'testi', 'tipo' => 'numero', 'default' => 44, 'min' => 8, 'max' => 200, 'passo' => 1, 'unita' => 'px', 'etichetta' => 'Numero attivo: dimensione'],
        'peso_numero'       => ['gruppo' => 'testi', 'tipo' => 'scelta', 'default' => '600',
                                'opzioni' => ['300' => 'Leggero', '400' => 'Normale', '500' => 'Medio', '600' => 'Semi grassetto', '700' => 'Grassetto'], 'etichetta' => 'Numero attivo: peso'],
        'colore_titolo'     => ['gruppo' => 'testi', 'tipo' => 'colore', 'default' => 'rgba(1,38,17,.52)', 'etichetta' => 'Titolo della fase attiva'],
        'dim_titolo'        => ['gruppo' => 'testi', 'tipo' => 'numero', 'default' => 22, 'min' => 8, 'max' => 80, 'passo' => 1, 'unita' => 'px', 'etichetta' => 'Titolo: dimensione'],
        'peso_titolo'       => ['gruppo' => 'testi', 'tipo' => 'scelta', 'default' => '400',
                                'opzioni' => ['300' => 'Leggero', '400' => 'Normale', '500' => 'Medio', '600' => 'Semi grassetto', '700' => 'Grassetto'], 'etichetta' => 'Titolo: peso'],
        'titolo_max'        => ['gruppo' => 'testi', 'tipo' => 'numero', 'default' => 220, 'min' => 60, 'max' => 800, 'passo' => 10, 'unita' => 'px',
                                'etichetta' => 'Titolo: larghezza massima', 'aiuto' => 'Oltre questa larghezza il titolo va a capo.'],
        'spazio_titolo'     => ['gruppo' => 'testi', 'tipo' => 'numero', 'default' => 6, 'min' => 0, 'max' => 200, 'passo' => 1, 'unita' => 'px',
                                'etichetta' => 'Spazio tra numero e titolo'],
        'interlinea_titolo' => ['gruppo' => 'testi', 'tipo' => 'numero', 'default' => 1.2, 'min' => 0.8, 'max' => 2.5, 'passo' => 0.05, 'unita' => '',
                                'etichetta' => 'Titolo: interlinea', 'aiuto' => 'Distanza tra le righe dei titoli lunghi (1 = righe attaccate).'],
        'allinea_titolo'    => ['gruppo' => 'testi', 'tipo' => 'scelta', 'default' => 'basso',
                                'opzioni' => ['basso' => 'Alla base del numero', 'centro' => 'Al centro del numero', 'alto' => 'Alla cima del numero'],
                                'etichetta' => 'Titolo: allineamento rispetto al numero',
                                'aiuto' => 'Con le descrizioni lunghe "Alla cima del numero" fa partire il testo all\'altezza del numero e lo fa scendere sotto.'],
        'colore_numeri'     => ['gruppo' => 'testi', 'tipo' => 'colore', 'default' => 'rgba(1,38,17,.3)', 'etichetta' => 'Numeri delle altre fasi'],
        'dim_numeri'        => ['gruppo' => 'testi', 'tipo' => 'numero', 'default' => 24, 'min' => 6, 'max' => 120, 'passo' => 1, 'unita' => 'px', 'etichetta' => 'Altri numeri: dimensione'],
        'titoli_inattivi'   => ['gruppo' => 'testi', 'tipo' => 'si_no', 'default' => 0, 'etichetta' => 'Mostra anche i titoli delle altre fasi'],
        'dissolvenza'       => ['gruppo' => 'testi', 'tipo' => 'numero', 'default' => 35, 'min' => 0, 'max' => 100, 'passo' => 1, 'unita' => '%',
                                'etichetta' => 'Dissolvenza delle fasi lontane', 'aiuto' => 'Quanto perde di opacità ogni fase allontanandosi da quella attiva.'],

        // --- Movimento ------------------------------------------------------
        // Un solo comportamento: quando la ruota arriva in posizione la pagina si
        // ferma, lo scroll fa girare la ruota e, finite le fasi, la pagina riparte.
        'allineamento'      => ['gruppo' => 'movimento', 'tipo' => 'scelta', 'default' => 'basso',
                                'opzioni' => ['basso' => 'Ruota in fondo allo schermo', 'centro' => 'Ruota al centro dello schermo', 'alto' => 'Ruota in alto'],
                                'etichetta' => 'Dove si ferma la pagina',
                                'aiuto' => 'Posizione della ruota sullo schermo nel momento in cui la pagina si ferma e la ruota inizia a girare.'],
        'distanza_alto'     => ['gruppo' => 'movimento', 'tipo' => 'numero', 'default' => 120, 'min' => 0, 'max' => 600, 'passo' => 5, 'unita' => 'px',
                                'etichetta' => 'Distanza dal bordo superiore', 'aiuto' => 'Solo con "Ruota in alto": lascia spazio all\'header del sito se è fisso.'],
        'scroll_fase'       => ['gruppo' => 'movimento', 'tipo' => 'numero', 'default' => 40, 'min' => 10, 'max' => 200, 'passo' => 5, 'unita' => '% schermo',
                                'etichetta' => 'Scroll per ogni fase', 'aiuto' => 'Quanto bisogna scorrere per passare alla fase successiva (100 = un\'altezza di schermo). Più basso = ruota più reattiva.'],
        'rotazione'         => ['gruppo' => 'movimento', 'tipo' => 'scelta', 'default' => 'continua',
                                'opzioni' => ['continua' => 'Continua (segue lo scroll e si assesta sulla fase più vicina)', 'scatti' => 'A scatti (un gesto = una fase)'], 'etichetta' => 'Rotazione'],
        'durata'            => ['gruppo' => 'movimento', 'tipo' => 'numero', 'default' => 600, 'min' => 0, 'max' => 3000, 'passo' => 50, 'unita' => 'ms',
                                'etichetta' => 'Durata della rotazione', 'aiuto' => 'Passaggio tra due fasi con la rotazione a scatti o con la tastiera, e assestamento con quella continua.'],
    ];
}

/**
 * Impostazioni che possono avere un valore diverso su mobile. Il valore mobile è
 * salvato con il suffisso "_m"; vuoto = uguale al desktop.
 */
function rf_chiavi_responsive() {
    return [
        'diametro', 'diametro_min', 'diametro_max', 'visibile', 'passo', 'spessore_cerchio', 'spazio_sopra',
        'indicatore_l', 'indicatore_h', 'tacche_l', 'tacche_h', 'scala_distanza', 'scala_densita',
        'posizione', 'dim_numero', 'dim_titolo', 'titolo_max', 'spazio_titolo', 'interlinea_titolo', 'allinea_titolo',
        'dim_numeri', 'dissolvenza',
        'allineamento', 'distanza_alto', 'scroll_fase',
    ];
}

function rf_default() {
    $def = array_map(fn($s) => $s['default'], rf_schema());
    foreach (rf_chiavi_responsive() as $k) $def[$k . '_m'] = '';
    return $def;
}

function rf_fasi_default() {
    return [
        ['numero' => '', 'titolo' => 'Definizione degli obiettivi'],
        ['numero' => '', 'titolo' => 'Analisi e reverse engineering'],
        ['numero' => '', 'titolo' => 'Progettazione'],
        ['numero' => '', 'titolo' => 'Prototipazione'],
        ['numero' => '', 'titolo' => 'Test e validazione'],
        ['numero' => '', 'titolo' => 'Produzione'],
    ];
}

/** Colore CSS ammesso: esadecimale, rgb(a), hsl(a) o "transparent". */
function rf_colore_valido($v) {
    $v = trim((string) $v);
    if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $v)) return $v;
    if (preg_match('/^(rgb|rgba|hsl|hsla)\(\s*[0-9.,%\s\/deg-]+\)$/i', $v)) return $v;
    if (strtolower($v) === 'transparent') return 'transparent';
    return null;
}

/** Valida le impostazioni ricevute dal pannello rispetto allo schema. */
function rf_sanifica_config($in) {
    $out = [];
    foreach (rf_schema() as $k => $s) {
        $v = $in[$k] ?? null;
        switch ($s['tipo']) {
            case 'numero':
                $n = is_numeric($v) ? (float) $v : $s['default'];
                $out[$k] = max($s['min'], min($s['max'], $n));
                break;
            case 'colore':
                $out[$k] = rf_colore_valido($v) ?? $s['default'];
                break;
            case 'scelta':
                $out[$k] = array_key_exists((string) $v, $s['opzioni']) ? (string) $v : $s['default'];
                break;
            case 'si_no':
                $out[$k] = !empty($v) ? 1 : 0;
                break;
            case 'testo':
                // selettori CSS: lettere, numeri e i simboli usati nei selettori
                $out[$k] = substr(preg_replace('/[^\w\s#.\-\[\]=:>"\'()*,]/u', '', (string) $v), 0, 200);
                break;
        }
    }

    // Valori mobile: vuoto = uguale al desktop, altrimenti stesse regole del valore desktop
    $schema = rf_schema();
    foreach (rf_chiavi_responsive() as $k) {
        $s = $schema[$k];
        $v = $in[$k . '_m'] ?? '';
        $m = '';
        if ($v !== '' && $v !== null) {
            if ($s['tipo'] === 'numero' && is_numeric($v))                          $m = max($s['min'], min($s['max'], (float) $v));
            elseif ($s['tipo'] === 'scelta' && array_key_exists((string) $v, $s['opzioni'])) $m = (string) $v;
        }
        $out[$k . '_m'] = $m;
    }
    return $out;
}

/** Titolo di una fase ripulito: testo semplice, anche su più righe (a capo manuali). */
function rf_pulisci_titolo($t) {
    return trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', sanitize_textarea_field((string) $t))));
}

function rf_sanifica_fasi($titoli, $numeri, $titoli_lingue = null, $fasi_salvate = []) {
    $pulisci = fn($t) => rf_pulisci_titolo(wp_unslash((string) $t));
    // Titoli nelle altre lingue: se il form non li contiene (traduttore non attivo) restano quelli salvati
    $tieni = !is_array($titoli_lingue);
    $fasi  = [];
    foreach ((array) $titoli as $i => $t) {
        $t = $pulisci($t);
        if ($t === '') continue;
        $fase = ['numero' => sanitize_text_field(wp_unslash($numeri[$i] ?? '')), 'titolo' => $t];
        $traduzioni = [];
        if ($tieni) {
            foreach ((array) $fasi_salvate as $salvata) {
                if (($salvata['titolo'] ?? null) === $t && !empty($salvata['titoli'])) { $traduzioni = (array) $salvata['titoli']; break; }
            }
        } else {
            foreach ($titoli_lingue as $lingua => $valori) {
                $lingua = sanitize_key((string) $lingua);
                $tl     = $pulisci(((array) $valori)[$i] ?? '');
                if ($lingua !== '' && $tl !== '') $traduzioni[$lingua] = $tl;
            }
        }
        if ($traduzioni) $fase['titoli'] = $traduzioni;
        $fasi[] = $fase;
    }
    return $fasi;
}

// -----------------------------------------------------------------------------
// Lingue (facoltativo, con "Elementor Page Translator"): senza il traduttore la
// ruota funziona come prima, solo con i titoli italiani.
// -----------------------------------------------------------------------------

/** Lingue diverse da quella predefinita: [ codice => nome ]. */
function rf_lingue() {
    if (!function_exists('ept_languages') || !function_exists('ept_default_language')) return [];
    $out = [];
    foreach (ept_languages() as $codice => $l) {
        if ($codice !== ept_default_language()) $out[(string) $codice] = (string) $l['name'];
    }
    return $out;
}

/** Fasi con i titoli nella lingua della pagina (vuoto = titolo italiano). */
function rf_fasi_lingua($fasi) {
    if (!function_exists('ept_current_language')) return $fasi;
    $lingua = ept_current_language();
    foreach ($fasi as &$f) {
        if (!empty($f['titoli'][$lingua])) $f['titolo'] = $f['titoli'][$lingua];
        unset($f['titoli']);
    }
    unset($f);
    return $fasi;
}

/** Versione del formato delle impostazioni salvate (per adeguare le ruote salvate prima di un aggiornamento) */
const RF_CONFIG_VERSIONE = 2;

/** Impostazioni di una ruota, completate con i default (anche per opzioni aggiunte in futuro). */
function rf_config($id) {
    $cfg = get_post_meta($id, '_rf_config', true);
    return rf_sanifica_config(rf_migra_config(is_array($cfg) ? $cfg : []) + rf_default());
}

/** Adegua impostazioni salvate (o esportate) con una versione precedente del formato. */
function rf_migra_config($cfg) {
    // Versione 1 → 2: titoli più grandi e scroll per fase più corto (la pagina ora
    // si ferma del tutto). Si aggiornano solo i valori rimasti quelli predefiniti.
    if ($cfg && (int) ($cfg['_v'] ?? 1) < 2) {
        if ((float) ($cfg['dim_titolo'] ?? 0) == 18)  $cfg['dim_titolo']  = 22;
        if ((float) ($cfg['titolo_max'] ?? 0) == 180) $cfg['titolo_max']  = 220;
        if ((float) ($cfg['scroll_fase'] ?? 0) == 60) $cfg['scroll_fase'] = 40;
        if ((float) ($cfg['durata'] ?? 0) == 700)     $cfg['durata']      = 600;
        if ((float) ($cfg['diametro'] ?? 0) > 100)    $cfg['diametro']    = 100;
    }
    return $cfg;
}

function rf_fasi($id) {
    $fasi = get_post_meta($id, '_rf_fasi', true);
    return is_array($fasi) ? $fasi : [];
}
