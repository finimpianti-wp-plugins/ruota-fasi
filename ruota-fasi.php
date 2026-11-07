<?php
/**
 * Plugin Name: Ruota Fasi Finimpianti
 * Description: Ruota animata con le fasi di sviluppo di un progetto. Shortcode: [ruota_fasi id="123"]
 * Version: 1.3
 * Author: Finimpianti
 * Update URI: false
 */

defined('ABSPATH') || exit;

define('RF_VERSION', '1.3');
define('RF_FILE',    __FILE__);
define('RF_CPT',     'rf_ruota');

require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/cpt.php';
require_once __DIR__ . '/includes/shortcode.php';
