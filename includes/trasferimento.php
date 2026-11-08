<?php
defined('ABSPATH') || exit;

// =============================================================================
// ESPORTA, IMPORTA E DUPLICA — una ruota (nome, fasi con i titoli per lingua,
// impostazioni) si scarica in un file .json e si importa in un altro sito come
// nuova ruota in bozza; "Duplica" fa lo stesso dentro lo stesso sito.
// =============================================================================

const RF_FORMATO_FILE   = 'ruota-fasi';
const RF_VERSIONE_FILE  = 1;
const RF_FILE_MAX_BYTES = 1048576; // 1 MB: una ruota occupa pochi kB
const RF_FASI_MAX       = 200;

/** Link firmato (nonce) a un'azione su una ruota. */
function rf_url_azione($azione, $id) {
    return wp_nonce_url(admin_url('admin-post.php?action=' . $azione . '&ruota=' . (int) $id), $azione . '_' . (int) $id);
}

/** L'utente può creare ruote? */
function rf_puo_creare() {
    $tipo = get_post_type_object(RF_CPT);
    return $tipo && current_user_can($tipo->cap->create_posts);
}

/** Dati di una ruota pronti per il file di esportazione. */
function rf_dati_ruota($id) {
    $cfg = rf_config($id);
    $cfg['_v'] = RF_CONFIG_VERSIONE;
    return [
        'formato'    => RF_FORMATO_FILE,
        'versione'   => RF_VERSIONE_FILE,
        'plugin'     => RF_VERSION,
        'esportata'  => gmdate('c'),
        'nome'       => (string) get_post_field('post_title', $id, 'raw'),
        'fasi'       => array_values(rf_fasi($id)),
        'impostazioni' => $cfg,
    ];
}

/**
 * Controlla i dati letti da un file e li riporta alle stesse regole del
 * salvataggio dal pannello. Restituisce i dati puliti o una WP_Error.
 */
function rf_valida_dati($dati) {
    if (!is_array($dati) || ($dati['formato'] ?? '') !== RF_FORMATO_FILE) {
        return new WP_Error('rf_formato', 'Il file non è un\'esportazione di Ruota Fasi.');
    }
    if ((int) ($dati['versione'] ?? 0) > RF_VERSIONE_FILE) {
        return new WP_Error('rf_versione', 'Il file è stato creato con una versione più recente di Ruota Fasi: aggiorna il plugin e riprova.');
    }
    if (!isset($dati['fasi']) || !is_array($dati['fasi'])) {
        return new WP_Error('rf_fasi', 'Il file non contiene le fasi della ruota.');
    }

    $fasi = [];
    foreach (array_slice($dati['fasi'], 0, RF_FASI_MAX) as $f) {
        if (!is_array($f) || !is_scalar($f['titolo'] ?? null)) continue;
        $titolo = rf_pulisci_titolo($f['titolo']);
        if ($titolo === '') continue;
        $fase = ['numero' => sanitize_text_field(is_scalar($f['numero'] ?? null) ? (string) $f['numero'] : ''), 'titolo' => $titolo];
        $titoli = [];
        foreach ((array) ($f['titoli'] ?? []) as $lingua => $t) {
            $lingua = sanitize_key((string) $lingua);
            $t      = is_scalar($t) ? rf_pulisci_titolo($t) : '';
            if ($lingua !== '' && $t !== '') $titoli[$lingua] = $t;
        }
        if ($titoli) $fase['titoli'] = $titoli;
        $fasi[] = $fase;
    }
    if (!$fasi) {
        return new WP_Error('rf_fasi', 'Il file non contiene nessuna fase valida.');
    }

    // Impostazioni: stessa correzione delle ruote salvate con versioni precedenti, poi
    // validazione sullo schema (valori mancanti o non validi = valori iniziali).
    $cfg = rf_migra_config(is_array($dati['impostazioni'] ?? null) ? $dati['impostazioni'] : []);
    $cfg = rf_sanifica_config($cfg + rf_default());
    $cfg['_v'] = RF_CONFIG_VERSIONE;

    $nome = is_scalar($dati['nome'] ?? null) ? sanitize_text_field((string) $dati['nome']) : '';
    return ['nome' => $nome !== '' ? $nome : 'Ruota importata', 'fasi' => $fasi, 'impostazioni' => $cfg];
}

/** Crea una nuova ruota in bozza. Restituisce l'ID o una WP_Error. */
function rf_crea_ruota($nome, $fasi, $cfg) {
    $id = wp_insert_post(wp_slash([
        'post_type'   => RF_CPT,
        'post_status' => 'draft',
        'post_title'  => $nome,
    ]), true);
    if (is_wp_error($id)) return $id;
    update_post_meta($id, '_rf_fasi', wp_slash($fasi));
    update_post_meta($id, '_rf_config', wp_slash($cfg));
    return $id;
}

/** Ruota su cui agire, con controllo del nonce e dei permessi. */
function rf_ruota_richiesta($azione) {
    $id = isset($_GET['ruota']) ? absint($_GET['ruota']) : 0;
    check_admin_referer($azione . '_' . $id);
    $post = $id ? get_post($id) : null;
    if (!$post || $post->post_type !== RF_CPT || !current_user_can('edit_post', $id)) {
        wp_die('Ruota non trovata o permessi insufficienti.', 403);
    }
    return $post;
}

// --- Esporta --------------------------------------------------------------------
add_action('admin_post_rf_esporta', function () {
    $post = rf_ruota_richiesta('rf_esporta');
    $nome = sanitize_title($post->post_title);
    $file = 'ruota-' . ($nome !== '' ? $nome : $post->ID) . '.json';
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('X-Content-Type-Options: nosniff');
    echo wp_json_encode(rf_dati_ruota($post->ID), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

// --- Duplica --------------------------------------------------------------------
add_action('admin_post_rf_duplica', function () {
    $post = rf_ruota_richiesta('rf_duplica');
    if (!rf_puo_creare()) wp_die('Non hai i permessi per creare ruote.', 403);
    $cfg = rf_config($post->ID);
    $cfg['_v'] = RF_CONFIG_VERSIONE;
    $nome = trim($post->post_title) !== '' ? $post->post_title : 'Ruota ' . $post->ID;
    $id   = rf_crea_ruota($nome . ' (copia)', rf_fasi($post->ID), $cfg);
    if (is_wp_error($id)) wp_die(esc_html($id->get_error_message()));
    wp_safe_redirect(add_query_arg('rf_msg', 'duplicata', get_edit_post_link($id, 'url')));
    exit;
});

// --- Importa --------------------------------------------------------------------
add_action('admin_menu', function () {
    add_submenu_page('edit.php?post_type=' . RF_CPT, 'Importa ruota', 'Importa ruota', 'edit_pages', 'rf-importa', 'rf_pagina_importa');
});

function rf_pagina_importa() {
    $errori = [
        'file'     => 'Scegli un file .json esportato da Ruota Fasi.',
        'caricamento' => 'Il file non è stato caricato correttamente: riprova.',
        'grande'   => 'Il file è troppo grande per essere un\'esportazione di Ruota Fasi.',
        'json'     => 'Il file non è leggibile (non è un file .json valido).',
        'permessi' => 'Non hai i permessi per creare ruote.',
    ];
    $codice = isset($_GET['rf_errore']) ? sanitize_key(wp_unslash($_GET['rf_errore'])) : '';
    $testo  = $errori[$codice] ?? (isset($_GET['rf_testo']) ? sanitize_text_field(wp_unslash($_GET['rf_testo'])) : '');
    ?>
    <div class="wrap">
        <h1>Importa ruota</h1>
        <?php if ($codice !== '' && $testo !== ''): ?>
            <div class="notice notice-error"><p><?= esc_html($testo) ?></p></div>
        <?php endif; ?>
        <p>Scegli un file <code>.json</code> creato con <strong>Esporta</strong> (in questo sito o in un altro con Ruota Fasi).</p>
        <p>Viene creata una <strong>nuova ruota in bozza</strong> con nome, fasi (anche i titoli nelle altre lingue) e impostazioni del file: nessuna ruota esistente viene modificata. Controlla l'anteprima, premi <strong>Pubblica</strong> e usa il nuovo shortcode.</p>
        <form method="post" enctype="multipart/form-data" action="<?= esc_url(admin_url('admin-post.php')) ?>">
            <input type="hidden" name="action" value="rf_importa">
            <?php wp_nonce_field('rf_importa'); ?>
            <p><input type="file" name="rf_file" accept=".json,application/json" required></p>
            <?php submit_button('Importa', 'primary', 'submit', false); ?>
        </form>
    </div>
    <?php
}

add_action('admin_post_rf_importa', function () {
    check_admin_referer('rf_importa');
    $torna = function ($errore, $testo = '') {
        $args = ['post_type' => RF_CPT, 'page' => 'rf-importa', 'rf_errore' => $errore];
        if ($testo !== '') $args['rf_testo'] = rawurlencode($testo);
        wp_safe_redirect(add_query_arg($args, admin_url('edit.php')));
        exit;
    };
    if (!current_user_can('edit_pages') || !rf_puo_creare()) $torna('permessi');

    $file = $_FILES['rf_file'] ?? null;
    if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['size']) || is_array($file['error'])) $torna('file');
    if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) $torna('file');
    if ((int) $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) $torna('caricamento');
    if ((int) $file['size'] > RF_FILE_MAX_BYTES) $torna('grande');

    $dati = json_decode((string) file_get_contents($file['tmp_name']), true);
    if (!is_array($dati)) $torna('json');
    $dati = rf_valida_dati($dati);
    if (is_wp_error($dati)) $torna('dati', $dati->get_error_message());

    $id = rf_crea_ruota($dati['nome'], $dati['fasi'], $dati['impostazioni']);
    if (is_wp_error($id)) $torna('dati', $id->get_error_message());
    wp_safe_redirect(add_query_arg('rf_msg', 'importata', get_edit_post_link($id, 'url')));
    exit;
});

// --- Link nell'elenco e avvisi ---------------------------------------------------
add_filter('post_row_actions', function ($azioni, $post) {
    if ($post->post_type !== RF_CPT || $post->post_status === 'trash' || !current_user_can('edit_post', $post->ID)) return $azioni;
    $azioni['rf_esporta'] = '<a href="' . esc_url(rf_url_azione('rf_esporta', $post->ID)) . '">Esporta</a>';
    if (rf_puo_creare()) {
        $azioni['rf_duplica'] = '<a href="' . esc_url(rf_url_azione('rf_duplica', $post->ID)) . '">Duplica</a>';
    }
    return $azioni;
}, 10, 2);

// Pulsante "Importa ruota" accanto a "Nuova ruota" in cima all'elenco
add_action('admin_print_footer_scripts-edit.php', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== RF_CPT || !rf_puo_creare()) return;
    $url = admin_url('edit.php?post_type=' . RF_CPT . '&page=rf-importa');
    ?>
    <script>
    (function () {
        var nuova = document.querySelector('.wrap .page-title-action');
        if (!nuova) return;
        var a = document.createElement('a');
        a.className = 'page-title-action';
        a.href = <?= wp_json_encode($url) ?>;
        a.textContent = 'Importa ruota';
        nuova.insertAdjacentElement('afterend', a);
    })();
    </script>
    <?php
});

add_action('admin_notices', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== RF_CPT || $screen->base !== 'post' || !isset($_GET['rf_msg'])) return;
    $msg = sanitize_key(wp_unslash($_GET['rf_msg']));
    $testi = [
        'importata' => 'Ruota importata in bozza. Controlla l\'anteprima e premi Pubblica: poi usa lo shortcode qui a destra.',
        'duplicata' => 'Copia creata in bozza. Modificala come vuoi e premi Pubblica: poi usa lo shortcode qui a destra.',
    ];
    if (isset($testi[$msg])) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($testi[$msg]) . '</p></div>';
});
