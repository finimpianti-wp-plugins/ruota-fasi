<?php
/**
 * Plugin Name: Ruota Fasi Finimpianti
 * Description: Ruota animata con le fasi di sviluppo di un progetto. Shortcode: [ruota_fasi id="123"]
 * Version: 1.4
 * Author: Finimpianti
 * Text Domain: ruota-fasi
 * Domain Path: /languages
 * Update URI: false
 */

defined('ABSPATH') || exit;

define('RF_VERSION', '1.4');
define('RF_FILE',    __FILE__);
define('RF_CPT',     'rf_ruota');

add_action('init', function () {
    load_plugin_textdomain('ruota-fasi', false, dirname(plugin_basename(RF_FILE)) . '/languages');
});

require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/cpt.php';
require_once __DIR__ . '/includes/shortcode.php';
