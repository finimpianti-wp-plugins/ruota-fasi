<?php
defined('ABSPATH') || exit;

// =============================================================================
// SHORTCODE [ruota_fasi id="123"]
// Senza id viene mostrata la ruota pubblicata più recente.
// =============================================================================

add_shortcode('ruota_fasi', 'rf_shortcode');

function rf_shortcode($atts) {
    static $istanza = 0;
    $atts = shortcode_atts(['id' => 0], $atts, 'ruota_fasi');
    $id   = absint($atts['id']);

    if (!$id) {
        $ultime = get_posts(['post_type' => RF_CPT, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids']);
        $id = $ultime ? (int) $ultime[0] : 0;
    }

    $post = $id ? get_post($id) : null;
    $puo_vedere = current_user_can('edit_posts');
    if (!$post || $post->post_type !== RF_CPT || ($post->post_status !== 'publish' && !$puo_vedere)) {
        return $puo_vedere ? '<p style="color:#B3261E">[ruota_fasi] ' . esc_html(sprintf(/* translators: %s: wheel ID. */ __('Ruota non trovata o non pubblicata (id %s).', 'ruota-fasi'), $atts['id'])) . '</p>' : '';
    }

    $fasi = rf_fasi($id);
    if (!$fasi) return $puo_vedere ? '<p style="color:#B3261E">[ruota_fasi] ' . esc_html__('La ruota non ha fasi.', 'ruota-fasi') . '</p>' : '';

    $url = plugin_dir_url(RF_FILE) . 'assets/';
    $dir = plugin_dir_path(RF_FILE) . 'assets/';
    wp_enqueue_style('rf-ruota', $url . 'ruota.css', [], filemtime($dir . 'ruota.css'));
    wp_enqueue_script('rf-ruota', $url . 'ruota.js', [], filemtime($dir . 'ruota.js'), true);

    $cfg = rf_config($id);
    $cfg['fasi'] = $fasi;
    $istanza++;

    // L'elenco ordinato è il contenuto accessibile (screen reader, motori di
    // ricerca, browser senza JavaScript); la ruota grafica viene disegnata da ruota.js
    ob_start(); ?>
    <div class="rf-ruota" id="rf-<?= (int) $id ?>-<?= $istanza ?>" data-config="<?= esc_attr(wp_json_encode($cfg)) ?>">
        <ol class="rf-elenco">
            <?php foreach ($fasi as $i => $f): ?>
                <li><span class="rf-elenco-num"><?= esc_html($f['numero'] !== '' ? $f['numero'] : ($cfg['numerazione'] === '01' ? sprintf('%02d', $i + 1) : $i + 1)) ?></span> <?= esc_html($f['titolo']) ?></li>
            <?php endforeach; ?>
        </ol>
    </div>
    <?php
    return ob_get_clean();
}
