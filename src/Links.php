<?php
/**
 * The "Affiliate Link" post type, its meta and lookups.
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker;

final class Links {

	public const POST_TYPE = 'alc_link';
	public const TAXONOMY  = 'alc_link_category';

	/** Offer states that mean the affiliate link no longer pays. */
	public const DEAD_STATUSES = array( 'BANNED', 'DENIED', 'PAUSED', 'REQUEST' );

	public const META = array(
		'target_url'     => '_alc_target_url',     // The real affiliate URL; where the cloaked link goes.
		'source'         => '_alc_source',         // e.g. "warriorplus", or "" for manual links.
		'offer_id'       => '_alc_offer_id',       // Network offer ID, e.g. "offer_jkkla93".
		'request_id'     => '_alc_request_id',     // WarriorPlus affiliate request ID, e.g. "affreq_f81ks3".
		'request_status' => '_alc_request_status', // ACTIVE, PAUSED, BANNED, DENIED, REQUEST.
		'offer_status'   => '_alc_offer_status',   // active / inactive.
		'vendor'         => '_alc_vendor',
		'synced_at'      => '_alc_synced_at',
		'redirect_type'  => '_alc_redirect_type',
	);

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_action( 'before_delete_post', array( self::class, 'onDelete' ) );
	}

	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Affiliate Links', 'aff-link-cloaker' ),
					'singular_name' => __( 'Affiliate Link', 'aff-link-cloaker' ),
					'add_new_item'  => __( 'Add Affiliate Link', 'aff-link-cloaker' ),
					'edit_item'     => __( 'Edit Affiliate Link', 'aff-link-cloaker' ),
					'search_items'  => __( 'Search Affiliate Links', 'aff-link-cloaker' ),
					'not_found'     => __( 'No affiliate links found.', 'aff-link-cloaker' ),
					'menu_name'     => __( 'Affiliate Links', 'aff-link-cloaker' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-admin-links',
				'supports'        => array( 'title', 'excerpt' ),
				'rewrite'         => false,
				'query_var'       => false,
				'capability_type' => 'post',
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Link Categories', 'aff-link-cloaker' ),
					'singular_name' => __( 'Link Category', 'aff-link-cloaker' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'hierarchical'      => true,
				'rewrite'           => false,
			)
		);
	}

	public static function meta( int $post_id, string $key ): string {
		return isset( self::META[ $key ] ) ? (string) get_post_meta( $post_id, self::META[ $key ], true ) : '';
	}

	public static function setMeta( int $post_id, string $key, string|int $value ): void {
		if ( isset( self::META[ $key ] ) ) {
			update_post_meta( $post_id, self::META[ $key ], $value );
		}
	}

	/** The public cloaked URL, e.g. https://example.com/go/my-offer/ */
	public static function cloakedUrl( int $post_id, string $subid = '' ): string {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type || '' === $post->post_name ) {
			return '';
		}
		$subid = sanitize_title( $subid );
		if ( get_option( 'permalink_structure' ) ) {
			$path = Settings::get( 'prefix' ) . '/' . $post->post_name . ( '' !== $subid ? '/' . $subid : '' );
			return home_url( user_trailingslashit( $path ) );
		}
		$args = array( 'alc' => $post->post_name );
		if ( '' !== $subid ) {
			$args['alc_subid'] = $subid;
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	/** Whether the network says this link no longer earns (paused, banned, offer inactive...). */
	public static function isDead( int $post_id ): bool {
		return in_array( self::meta( $post_id, 'request_status' ), self::DEAD_STATUSES, true )
			|| 'inactive' === self::meta( $post_id, 'offer_status' );
	}

	/**
	 * Where a click should go, or '' if nowhere valid.
	 */
	public static function destination( int $post_id, string $subid = '' ): string {
		$url = self::meta( $post_id, 'target_url' );

		$fallback = (string) Settings::get( 'inactive_url' );
		if ( '' !== $fallback && self::isDead( $post_id ) ) {
			$url = $fallback;
		} elseif ( '' !== $subid ) {
			// Let the network record the sub-ID where it supports one (e.g. JVZoo's ?tid=).
			$source = App::instance()->sources->get( self::meta( $post_id, 'source' ) );
			if ( $source ) {
				$url = $source->applySubId( $url, $subid );
			}
		}

		/**
		 * Filter the destination before redirecting, e.g. to append a network-specific sub-ID.
		 *
		 * @param string $url     Destination.
		 * @param int    $post_id Link ID.
		 * @param string $subid   Sub-ID from the cloaked URL, or ''.
		 */
		$url = (string) apply_filters( 'alc_destination_url', $url, $post_id, $subid );

		return self::isValidDestination( $url ) ? $url : '';
	}

	/** Absolute http(s) URL with a host. No DNS lookup: we redirect to it, we don't fetch it. */
	public static function isValidDestination( string $url ): bool {
		if ( '' === $url || preg_match( '/[\s<>"]/', $url ) ) {
			return false;
		}
		$parts = wp_parse_url( $url );
		return is_array( $parts )
			&& isset( $parts['scheme'], $parts['host'] )
			&& in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true )
			&& preg_match( '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$/i', $parts['host'] );
	}

	public static function findBySlug( string $slug ): ?\WP_Post {
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'name'             => sanitize_title( $slug ),
				'post_status'      => 'publish',
				'numberposts'      => 1,
				'suppress_filters' => true,
			)
		);
		return $posts[0] ?? null;
	}

	/** Find an existing link for an offer: by network offer ID first, then by its affiliate URL. */
	public static function findExisting( string $source, string $offer_id, string $url ): int {
		$queries = array();
		if ( '' !== $offer_id ) {
			$queries[] = array(
				array( 'key' => self::META['source'], 'value' => $source ),
				array( 'key' => self::META['offer_id'], 'value' => $offer_id ),
			);
		}
		if ( '' !== $url ) {
			$queries[] = array( array( 'key' => self::META['target_url'], 'value' => $url ) );
		}
		foreach ( $queries as $meta_query ) {
			$ids = get_posts(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					'numberposts' => 1,
					'fields'      => 'ids',
					'meta_query'  => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			);
			if ( $ids ) {
				return (int) $ids[0];
			}
		}
		return 0;
	}

	public static function onDelete( int $post_id ): void {
		if ( self::POST_TYPE === get_post_type( $post_id ) ) {
			Clicks::deleteForLink( $post_id );
		}
	}
}
