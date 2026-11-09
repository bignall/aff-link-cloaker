<?php
/**
 * Recording and counting clicks.
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker;

final class Clicks {

	private static function table(): string {
		return ( new ClicksTable() )->tableName();
	}

	/** Record a click. IPs are stored only as a salted hash. */
	public static function record( int $link_id, string $subid = '' ): void {
		global $wpdb;

		$ip  = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$ref = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ) );
		$ua  = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'link_id'    => $link_id,
				'clicked_at' => current_time( 'mysql', true ),
				'subid'      => substr( $subid, 0, 100 ),
				'referrer'   => substr( $ref, 0, 255 ),
				'ip_hash'    => '' !== $ip ? hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ) : '',
				'user_agent' => substr( $ua, 0, 255 ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		update_post_meta( $link_id, '_alc_clicks', (int) get_post_meta( $link_id, '_alc_clicks', true ) + 1 );
	}

	public static function count( int $link_id, int $days = 0 ): int {
		global $wpdb;
		if ( $days <= 0 ) {
			return (int) get_post_meta( $link_id, '_alc_clicks', true );
		}
		$table = self::table();
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE link_id = %d AND clicked_at >= %s", $link_id, $since ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	public static function deleteForLink( int $link_id ): void {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'link_id' => $link_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function shouldCount(): bool {
		$settings = Settings::all();
		if ( ! $settings['track_clicks'] || 'HEAD' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return false;
		}
		return ! ( $settings['ignore_bots'] && self::isBot() );
	}

	public static function isBot(): bool {
		$ua = strtolower( (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '' === $ua ) {
			return true;
		}
		return (bool) preg_match( '/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|curl|wget|python-requests|headless/', $ua );
	}
}
