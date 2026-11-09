<?php
/**
 * Plugin Name:       Affiliate Link Cloaker
 * Description:       Import your approved affiliate offers (WarriorPlus first) and turn them into short, cloaked, trackable redirect links you can use on this site or anywhere else.
 * Version:           0.2.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Arbe
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aff-link-cloaker
 *
 * @package AffLinkCloaker
 */

defined( 'ABSPATH' ) || exit;

// Keep this file PHP 5.6-compatible so old hosts get a notice instead of a parse error.
if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Affiliate Link Cloaker requires PHP 8.1 or newer.', 'aff-link-cloaker' ) . '</p></div>';
		}
	);
	return;
}

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Affiliate Link Cloaker is missing its dependencies. Run "composer install" in the plugin folder, or install a release zip.', 'aff-link-cloaker' ) . '</p></div>';
		}
	);
	return;
}

if ( file_exists( __DIR__ . '/vendor-prefixed/autoload.php' ) ) {
	require_once __DIR__ . '/vendor-prefixed/autoload.php';
}
require_once __DIR__ . '/vendor/autoload.php';

define( 'ALC_FILE', __FILE__ );

AffLinkCloaker\Bootstrap::plugin( __FILE__ )->boot();
