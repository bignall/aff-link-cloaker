<?php
/**
 * Plugin Name:       Affiliate Link Cloaker
 * Description:       Import offers from affiliate networks (starting with WarriorPlus) and turn them into short, cloaked, trackable redirect links you can use on this site or anywhere else.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Arbe
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aff-link-cloaker
 *
 * @package AffLinkCloaker
 */

defined( 'ABSPATH' ) || exit;

define( 'ALC_VERSION', '0.1.0' );
define( 'ALC_FILE', __FILE__ );
define( 'ALC_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALC_URL', plugin_dir_url( __FILE__ ) );

require_once ALC_DIR . 'includes/class-alc-settings.php';
require_once ALC_DIR . 'includes/class-alc-clicks.php';
require_once ALC_DIR . 'includes/class-alc-post-type.php';
require_once ALC_DIR . 'includes/class-alc-redirector.php';
require_once ALC_DIR . 'includes/class-alc-shortcode.php';
require_once ALC_DIR . 'includes/class-alc-importer.php';
require_once ALC_DIR . 'includes/class-alc-admin.php';
require_once ALC_DIR . 'includes/sources/interface-alc-source.php';
require_once ALC_DIR . 'includes/sources/class-alc-source-registry.php';
require_once ALC_DIR . 'includes/sources/class-alc-warriorplus-source.php';
require_once ALC_DIR . 'includes/class-alc-plugin.php';

register_activation_hook( __FILE__, array( 'ALC_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ALC_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'ALC_Plugin', 'instance' ) );
