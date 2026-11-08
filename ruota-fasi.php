<?php
/**
 * Plugin Name: Ruota Fasi
 * Description: Ruota animata con le fasi di sviluppo di un progetto. Shortcode: [ruota_fasi id="123"]
 * Version: 1.6
 * Author: Finimpianti
 * Text Domain: ruota-fasi
 * Domain Path: /languages
 * Update URI: https://github.com/finimpianti-wp-plugins/ruota-fasi
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') || exit;

define('RF_VERSION', '1.6');
define('RF_FILE',    __FILE__);
define('RF_CPT',     'rf_ruota');

add_action('init', function () {
    load_plugin_textdomain('ruota-fasi', false, dirname(plugin_basename(RF_FILE)) . '/languages');
});

require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/cpt.php';
require_once __DIR__ . '/includes/shortcode.php';

// Aggiornamenti da GitHub: ogni release del repository (con lo zip del plugin) compare in
// Bacheca -> Aggiornamenti e si installa con un clic. Il repository si può cambiare definendo
// RF_UPDATE_REPO in wp-config.php; APGR_GITHUB_TOKEN (facoltativo) serve solo per repository
// privati o per superare i limiti di richieste di GitHub.
if ( ! defined( 'RF_UPDATE_REPO' ) ) {
	define( 'RF_UPDATE_REPO', 'https://github.com/finimpianti-wp-plugins/ruota-fasi/' );
}
require_once __DIR__ . '/lib/plugin-update-checker/plugin-update-checker.php';
$rf_aggiornamenti = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( RF_UPDATE_REPO, __FILE__, 'ruota-fasi' );
$rf_aggiornamenti->getVcsApi()->enableReleaseAssets( '/^ruota\-fasi-[0-9][0-9.]*\.zip$/' );
if ( defined( 'APGR_GITHUB_TOKEN' ) && APGR_GITHUB_TOKEN ) {
	$rf_aggiornamenti->setAuthentication( APGR_GITHUB_TOKEN );
}
