<?php
defined('ABSPATH') || exit;

// =============================================================================
// ADMIN — ogni ruota è un contenuto "Ruote fasi" (non pubblico) con fasi,
// impostazioni e anteprima dal vivo.
// =============================================================================

add_action('init', function () {
    register_post_type(RF_CPT, [
        'labels' => [
            'name'               => 'Ruote fasi',
            'singular_name'      => 'Ruota fasi',
            'menu_name'          => 'Ruote fasi',
            'add_new'            => 'Nuova ruota',
            'add_new_item'       => 'Nuova ruota',
            'edit_item'          => 'Modifica ruota',
            'new_item'           => 'Nuova ruota',
            'search_items'       => 'Cerca ruote',
            'not_found'          => 'Nessuna ruota',
            'not_found_in_trash' => 'Nessuna ruota nel cestino',
            'all_items'          => 'Tutte le ruote',
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_position' => 31,
        'menu_icon'     => 'dashicons-update',
        'supports'      => ['title'],
        'capability_type' => 'page',
    ]);
});

add_filter('enter_title_here', function ($testo, $post) {
    return $post->post_type === RF_CPT ? 'Nome della ruota (solo per te, non viene mostrato)' : $testo;
}, 10, 2);

// --- Colonna shortcode nell'elenco ------------------------------------------
add_filter('manage_' . RF_CPT . '_posts_columns', function ($col) {
    return array_slice($col, 0, 2, true) + ['rf_shortcode' => 'Shortcode', 'rf_fasi' => 'Fasi'] + array_slice($col, 2, null, true);
});
add_action('manage_' . RF_CPT . '_posts_custom_column', function ($col, $id) {
    if ($col === 'rf_shortcode') echo '<code class="rf-copia" title="Clicca per copiare">[ruota_fasi id="' . (int) $id . '"]</code>';
    if ($col === 'rf_fasi')      echo count(rf_fasi($id));
}, 10, 2);

// --- Script e stili -----------------------------------------------------------
add_action('admin_enqueue_scripts', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== RF_CPT) return;
    $url = plugin_dir_url(RF_FILE) . 'assets/';
    $dir = plugin_dir_path(RF_FILE) . 'assets/';
    wp_enqueue_style('rf-ruota', $url . 'ruota.css', [], filemtime($dir . 'ruota.css'));
    wp_enqueue_style('rf-admin', $url . 'admin.css', [], filemtime($dir . 'admin.css'));
    wp_enqueue_script('rf-ruota', $url . 'ruota.js', [], filemtime($dir . 'ruota.js'), true);
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_script('rf-admin', $url . 'admin.js', ['rf-ruota', 'jquery', 'jquery-ui-sortable'], filemtime($dir . 'admin.js'), true);
});

// --- Box della pagina di modifica --------------------------------------------
add_action('add_meta_boxes_' . RF_CPT, function ($post) {
    add_meta_box('rf_anteprima', 'Anteprima', 'rf_box_anteprima', RF_CPT, 'normal', 'high');
    add_meta_box('rf_fasi', 'Fasi', 'rf_box_fasi', RF_CPT, 'normal', 'high');
    add_meta_box('rf_impostazioni', 'Aspetto e movimento', 'rf_box_impostazioni', RF_CPT, 'normal', 'default');
    add_meta_box('rf_shortcode', 'Inserisci nella pagina', 'rf_box_shortcode', RF_CPT, 'side', 'high');
});

function rf_box_shortcode($post) {
    $sc = '[ruota_fasi id="' . (int) $post->ID . '"]';
    ?>
    <p>Copia questo shortcode in un widget <strong>Shortcode</strong> di Elementor (o in qualsiasi pagina):</p>
    <p><code class="rf-copia rf-shortcode-grande" title="Clicca per copiare"><?= esc_html($sc) ?></code></p>
    <?php if ($post->post_status !== 'publish'): ?>
        <p class="rf-nota">La ruota viene mostrata nel sito solo dopo aver premuto <strong>Pubblica</strong>.</p>
    <?php endif; ?>
    <p class="rf-nota">Le modifiche si vedono nel sito dopo aver premuto <strong>Aggiorna</strong>.</p>
    <?php if ($post->post_status !== 'auto-draft'): ?>
        <p class="rf-azioni-ruota">
            <a class="button" href="<?= esc_url(rf_url_azione('rf_esporta', $post->ID)) ?>" title="Scarica un file con fasi e impostazioni, da importare in un altro sito">Esporta</a>
            <a class="button" href="<?= esc_url(rf_url_azione('rf_duplica', $post->ID)) ?>" title="Crea una copia di questa ruota (in bozza)">Duplica</a>
        </p>
        <p class="rf-nota">Esporta e Duplica usano l'ultima versione salvata: premi prima <strong>Aggiorna</strong> se hai modifiche in sospeso.</p>
    <?php endif; ?>
    <?php
}

function rf_box_anteprima($post) {
    ?>
    <div class="rf-anteprima-barra">
        <div class="rf-dispositivi" role="group" aria-label="Larghezza anteprima">
            <button type="button" class="button rf-dispositivo attivo" data-larghezza="">Desktop</button>
            <button type="button" class="button rf-dispositivo" data-larghezza="390">Mobile</button>
        </div>
        <label class="rf-cursore">
            <span>Fase</span>
            <input type="range" id="rf-anteprima-progresso" min="0" max="5" step="0.01" value="0">
        </label>
        <button type="button" class="button" id="rf-anteprima-riproduci">▶ Riproduci</button>
        <label class="rf-sfondo-anteprima" title="Solo per l'anteprima: non viene salvato. Utile per vedere le ruote chiare su sezioni scure.">
            <span>Sfondo anteprima</span>
            <input type="color" id="rf-anteprima-sfondo" value="#ffffff">
        </label>
    </div>
    <div class="rf-anteprima-cornice">
        <div class="rf-anteprima rf-ruota" id="rf-anteprima"></div>
    </div>
    <p class="rf-nota">Trascina il cursore "Fase" per simulare lo scroll della pagina. L'anteprima si aggiorna mentre modifichi le impostazioni.</p>
    <?php
}

function rf_box_fasi($post) {
    wp_nonce_field('rf_salva', 'rf_nonce');
    $fasi = $post->post_status === 'auto-draft' ? rf_fasi_default() : rf_fasi($post->ID);
    if (!$fasi) $fasi = [['numero' => '', 'titolo' => '']];
    ?>
    <p class="rf-nota">Trascina le righe per cambiare l'ordine. Il numero si genera da solo: compilalo solo se vuoi un numero diverso (es. un anno, "1928"). Il titolo può essere anche una descrizione di più righe. Le fasi senza titolo non vengono salvate.</p>
    <?php if (rf_lingue()): ?>
        <p class="rf-nota">Sotto ogni titolo italiano c'è il titolo nelle altre lingue: la ruota è una sola (stesso shortcode, stile e ordine delle fasi per tutte le lingue) e sulle pagine di ogni lingua mostra i titoli di quella lingua. Se un titolo è vuoto si usa quello italiano.</p>
    <?php endif; ?>
    <ol class="rf-fasi" id="rf-fasi">
        <?php foreach ($fasi as $f): ?>
            <li class="rf-fase-riga">
                <span class="rf-maniglia dashicons dashicons-menu" title="Trascina per riordinare"></span>
                <span class="rf-num-auto"></span>
                <input type="text" class="rf-num-input" name="rf_numeri[]" value="<?= esc_attr($f['numero']) ?>" placeholder="auto" aria-label="Numero (facoltativo)">
                <div class="rf-tit-gruppo">
                    <textarea class="rf-tit-input" name="rf_titoli[]" rows="1" placeholder="Titolo o descrizione della fase (Invio per andare a capo)" aria-label="Titolo"><?= esc_textarea($f['titolo']) ?></textarea>
                    <?php foreach (rf_lingue() as $lingua => $nome_lingua): ?>
                        <label class="rf-tit-lingua">
                            <span><?= esc_html(strtoupper($lingua)) ?></span>
                            <textarea name="rf_titoli_lingue[<?= esc_attr($lingua) ?>][]" rows="1" placeholder="<?= esc_attr($nome_lingua . ' — vuoto = come l\'italiano') ?>" aria-label="<?= esc_attr('Titolo in ' . $nome_lingua) ?>"><?= esc_textarea($f['titoli'][$lingua] ?? '') ?></textarea>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button-link rf-su" title="Sposta su" aria-label="Sposta su">▲</button>
                <button type="button" class="button-link rf-giu" title="Sposta giù" aria-label="Sposta giù">▼</button>
                <button type="button" class="button-link rf-elimina" title="Elimina fase" aria-label="Elimina fase"><span class="dashicons dashicons-trash"></span></button>
            </li>
        <?php endforeach; ?>
    </ol>
    <p><button type="button" class="button" id="rf-aggiungi-fase"><span class="dashicons dashicons-plus-alt2"></span> Aggiungi fase</button></p>
    <?php
}

function rf_box_impostazioni($post) {
    $cfg    = rf_config($post->ID);
    $schema = rf_schema();
    $resp   = rf_chiavi_responsive();
    ?>
    <div class="rf-impostazioni">
        <div class="rf-schede" role="tablist">
            <?php $primo = true; foreach (rf_gruppi() as $g => $nome): ?>
                <button type="button" class="rf-scheda <?= $primo ? 'attiva' : '' ?>" data-gruppo="<?= esc_attr($g) ?>" role="tab"><?= esc_html($nome) ?></button>
            <?php $primo = false; endforeach; ?>
            <button type="button" class="button-link rf-ripristina" id="rf-ripristina" title="Riporta tutte le impostazioni ai valori iniziali">Ripristina valori iniziali</button>
        </div>
        <p class="rf-legenda">
            <span class="dashicons dashicons-desktop"></span> Desktop
            <span class="dashicons dashicons-smartphone"></span> Mobile (sotto la larghezza "Mobile fino a"): lascia vuoto per usare lo stesso valore del desktop.
            Per vedere i valori mobile usa il pulsante <strong>Mobile</strong> dell'anteprima.
        </p>

        <?php $primo = true; foreach (rf_gruppi() as $g => $nome): ?>
            <div class="rf-gruppo" data-gruppo="<?= esc_attr($g) ?>" <?= $primo ? '' : 'hidden' ?>>
                <?php foreach ($schema as $k => $s): if ($s['gruppo'] !== $g) continue;
                    $id = 'rf-' . $k; $v = $cfg[$k]; $responsive = in_array($k, $resp, true); ?>
                    <div class="rf-campo rf-campo-<?= esc_attr($s['tipo']) ?> <?= $responsive ? 'rf-responsive' : '' ?>" data-chiave="<?= esc_attr($k) ?>">
                        <?php if ($s['tipo'] === 'si_no'): ?>
                            <label class="rf-check">
                                <input type="hidden" name="rf_cfg[<?= esc_attr($k) ?>]" value="0">
                                <input type="checkbox" id="<?= esc_attr($id) ?>" name="rf_cfg[<?= esc_attr($k) ?>]" value="1" data-rf="<?= esc_attr($k) ?>" data-default="<?= esc_attr($s['default']) ?>" <?php checked($v, 1); ?>>
                                <?= esc_html($s['etichetta']) ?>
                            </label>
                        <?php else: ?>
                            <label for="<?= esc_attr($id) ?>"><?= esc_html($s['etichetta']) ?></label>
                            <?php if ($s['tipo'] === 'numero'): ?>
                                <div class="rf-numero">
                                    <?php if ($responsive): ?><span class="dashicons dashicons-desktop rf-disp" title="Desktop"></span><?php endif; ?>
                                    <input type="range" min="<?= esc_attr($s['min']) ?>" max="<?= esc_attr($s['max']) ?>" step="<?= esc_attr($s['passo']) ?>" value="<?= esc_attr($v) ?>" data-collegato="<?= esc_attr($id) ?>" aria-hidden="true" tabindex="-1">
                                    <input type="number" id="<?= esc_attr($id) ?>" name="rf_cfg[<?= esc_attr($k) ?>]" min="<?= esc_attr($s['min']) ?>" max="<?= esc_attr($s['max']) ?>" step="<?= esc_attr($s['passo']) ?>" value="<?= esc_attr($v) ?>" data-rf="<?= esc_attr($k) ?>" data-default="<?= esc_attr($s['default']) ?>">
                                    <span class="rf-unita"><?= esc_html($s['unita']) ?></span>
                                </div>
                            <?php elseif ($s['tipo'] === 'colore'): ?>
                                <div class="rf-colore">
                                    <span class="rf-campione"><span style="background: <?= esc_attr($v) ?>"></span><input type="color" aria-label="Scegli colore" tabindex="-1"></span>
                                    <input type="text" id="<?= esc_attr($id) ?>" name="rf_cfg[<?= esc_attr($k) ?>]" value="<?= esc_attr($v) ?>" data-rf="<?= esc_attr($k) ?>" data-default="<?= esc_attr($s['default']) ?>" spellcheck="false">
                                </div>
                            <?php elseif ($s['tipo'] === 'testo'): ?>
                                <input type="text" id="<?= esc_attr($id) ?>" name="rf_cfg[<?= esc_attr($k) ?>]" value="<?= esc_attr($v) ?>" data-rf="<?= esc_attr($k) ?>" data-default="<?= esc_attr($s['default']) ?>" spellcheck="false" class="widefat">
                            <?php elseif ($s['tipo'] === 'scelta'): ?>
                                <div class="rf-scelta">
                                    <?php if ($responsive): ?><span class="dashicons dashicons-desktop rf-disp" title="Desktop"></span><?php endif; ?>
                                    <select id="<?= esc_attr($id) ?>" name="rf_cfg[<?= esc_attr($k) ?>]" data-rf="<?= esc_attr($k) ?>" data-default="<?= esc_attr($s['default']) ?>">
                                        <?php foreach ($s['opzioni'] as $ov => $ol): ?>
                                            <option value="<?= esc_attr($ov) ?>" <?php selected((string) $v, (string) $ov); ?>><?= esc_html($ol) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <?php if ($responsive): $km = $k . '_m'; $vm = $cfg[$km]; ?>
                                <div class="rf-mobile">
                                    <span class="dashicons dashicons-smartphone rf-disp" title="Mobile"></span>
                                    <?php if ($s['tipo'] === 'numero'): ?>
                                        <input type="number" name="rf_cfg[<?= esc_attr($km) ?>]" value="<?= esc_attr($vm) ?>" min="<?= esc_attr($s['min']) ?>" max="<?= esc_attr($s['max']) ?>" step="<?= esc_attr($s['passo']) ?>" placeholder="come desktop" data-rf="<?= esc_attr($km) ?>" data-default="" aria-label="<?= esc_attr($s['etichetta']) ?> su mobile">
                                        <span class="rf-unita"><?= esc_html($s['unita']) ?></span>
                                    <?php else: ?>
                                        <select name="rf_cfg[<?= esc_attr($km) ?>]" data-rf="<?= esc_attr($km) ?>" data-default="" aria-label="<?= esc_attr($s['etichetta']) ?> su mobile">
                                            <option value="">Come desktop</option>
                                            <?php foreach ($s['opzioni'] as $ov => $ol): ?>
                                                <option value="<?= esc_attr($ov) ?>" <?php selected((string) $vm, (string) $ov); ?>><?= esc_html($ol) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (!empty($s['aiuto'])): ?><p class="rf-aiuto"><?= esc_html($s['aiuto']) ?></p><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($g === 'stile' || $g === 'testi'): ?>
                    <p class="rf-nota rf-nota-colori">I colori accettano codici esadecimali (<code>#012611</code>) o con trasparenza (<code>rgba(1,38,17,.52)</code> = verde scuro al 52%).</p>
                <?php endif; ?>
            </div>
        <?php $primo = false; endforeach; ?>
    </div>
    <?php
}

// --- Salvataggio ----------------------------------------------------------------
add_action('save_post_' . RF_CPT, function ($id) {
    if (!isset($_POST['rf_nonce']) || !wp_verify_nonce($_POST['rf_nonce'], 'rf_salva')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $id)) return;

    // wp_slash: update_post_meta() toglie le barre rovesciate, che invece vanno conservate nei titoli
    update_post_meta($id, '_rf_fasi', wp_slash(rf_sanifica_fasi($_POST['rf_titoli'] ?? [], $_POST['rf_numeri'] ?? [], $_POST['rf_titoli_lingue'] ?? null, rf_fasi($id))));
    $cfg = rf_sanifica_config(wp_unslash((array) ($_POST['rf_cfg'] ?? [])));
    $cfg['_v'] = RF_CONFIG_VERSIONE;
    update_post_meta($id, '_rf_config', wp_slash($cfg));
});
