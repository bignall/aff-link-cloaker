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

	/**
	 * Click counts, filtered and grouped. Backs alc_click_counts().
	 *
	 * @param array<string, mixed> $args See alc_click_counts().
	 * @return list<array<string, int|string>>
	 */
	public static function grouped( array $args = array() ): array {
		global $wpdb;

		$columns = array(
			'link'  => 'link_id',
			'subid' => 'subid',
			'day'   => 'DATE(clicked_at)',
		);
		$aliases = array(
			'link'  => 'link_id',
			'subid' => 'subid',
			'day'   => 'day',
		);
		$group   = array_values( array_intersect( array_keys( $columns ), (array) ( $args['group_by'] ?? array() ) ) );

		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['link_id'] ) ) {
			$where[]  = 'link_id = %d';
			$params[] = (int) $args['link_id'];
		}
		if ( isset( $args['subid'] ) && '' !== $args['subid'] ) {
			$where[]  = 'subid = %s';
			$params[] = (string) $args['subid'];
		} elseif ( isset( $args['subid_prefix'] ) && '' !== $args['subid_prefix'] ) {
			$where[]  = 'subid LIKE %s';
			$params[] = $wpdb->esc_like( (string) $args['subid_prefix'] ) . '%';
		}
		foreach ( array( 'since' => '>=', 'until' => '<=' ) as $key => $op ) {
			$time = self::boundary( (string) ( $args[ $key ] ?? '' ), 'until' === $key );
			if ( '' !== $time ) {
				$where[]  = "clicked_at {$op} %s";
				$params[] = $time;
			}
		}

		$select = array();
		foreach ( $group as $g ) {
			$select[] = $columns[ $g ] . ' AS ' . $aliases[ $g ];
		}
		$select[] = 'COUNT(*) AS clicks';

		$table = self::table();
		$sql   = 'SELECT ' . implode( ', ', $select ) . " FROM {$table} WHERE " . implode( ' AND ', $where );
		if ( $group ) {
			$by   = implode( ', ', array_map( static fn ( string $g ): string => $aliases[ $g ], $group ) );
			$sql .= " GROUP BY {$by} ORDER BY {$by}";
		}
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$rows = (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

		$out = array();
		foreach ( $rows as $row ) {
			$item = array();
			foreach ( $group as $g ) {
				$item[ $aliases[ $g ] ] = 'link' === $g ? (int) $row['link_id'] : (string) $row[ $aliases[ $g ] ];
			}
			$item['clicks'] = (int) $row['clicks'];
			$out[]          = $item;
		}
		return $out;
	}

	/** A date or datetime as a UTC datetime; a bare "until" date covers that whole day. */
	private static function boundary( string $value, bool $end ): string {
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return $value . ( $end ? ' 23:59:59' : ' 00:00:00' );
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ) {
			return $value;
		}
		return '';
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
