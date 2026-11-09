<?php
/**
 * Click logging in a custom table.
 *
 * @package AffLinkCloaker
 */

defined( 'ABSPATH' ) || exit;

class ALC_Clicks {

	const DB_VERSION = '1';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'alc_clicks';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				link_id bigint(20) unsigned NOT NULL,
				clicked_at datetime NOT NULL,
				subid varchar(100) NOT NULL DEFAULT '',
				referrer varchar(255) NOT NULL DEFAULT '',
				ip_hash char(64) NOT NULL DEFAULT '',
				user_agent varchar(255) NOT NULL DEFAULT '',
				PRIMARY KEY  (id),
				KEY link_id (link_id),
				KEY clicked_at (clicked_at)
			) {$charset};"
		);

		update_option( 'alc_db_version', self::DB_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'alc_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Record a click. IPs are stored only as a salted hash.
	 */
	public static function record( $link_id, $subid = '' ) {
		global $wpdb;

		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ref = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$ua  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$wpdb->insert(
			self::table(),
			array(
				'link_id'    => (int) $link_id,
				'clicked_at' => current_time( 'mysql', true ),
				'subid'      => substr( $subid, 0, 100 ),
				'referrer'   => substr( $ref, 0, 255 ),
				'ip_hash'    => $ip ? hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ) : '',
				'user_agent' => substr( $ua, 0, 255 ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		$total = (int) get_post_meta( $link_id, '_alc_clicks', true );
		update_post_meta( $link_id, '_alc_clicks', $total + 1 );
	}

	public static function count( $link_id, $days = 0 ) {
		global $wpdb;
		$table = self::table();
		if ( $days > 0 ) {
			$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE link_id = %d AND clicked_at >= %s", $link_id, $since ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return (int) get_post_meta( $link_id, '_alc_clicks', true );
	}

	public static function delete_for_link( $link_id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'link_id' => (int) $link_id ), array( '%d' ) );
	}

	public static function is_bot() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '' === $ua ) {
			return true;
		}
		return (bool) preg_match( '/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|curl|wget|python-requests|headless/i', $ua );
	}
}
