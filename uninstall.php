<?php
/**
 * Remove plugin data on uninstall.
 *
 * @package AffLinkCloaker
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$alc_ids = get_posts(
	array(
		'post_type'   => 'alc_link',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);
foreach ( $alc_ids as $alc_id ) {
	wp_delete_post( $alc_id, true );
}

register_taxonomy( 'alc_link_category', 'alc_link' );
$alc_terms = get_terms( array( 'taxonomy' => 'alc_link_category', 'hide_empty' => false, 'fields' => 'ids' ) );
if ( ! is_wp_error( $alc_terms ) ) {
	foreach ( $alc_terms as $alc_term ) {
		wp_delete_term( $alc_term, 'alc_link_category' );
	}
}

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}alc_clicks" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

delete_option( 'alc_settings' );
delete_option( 'alc_db_version' );
delete_option( 'alc_flush_rewrites' );
