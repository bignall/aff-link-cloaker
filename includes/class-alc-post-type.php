<?php
/**
 * The "Affiliate Link" custom post type and its meta.
 *
 * @package AffLinkCloaker
 */

defined( 'ABSPATH' ) || exit;

class ALC_Post_Type {

	const POST_TYPE = 'alc_link';
	const TAXONOMY  = 'alc_link_category';

	/**
	 * Meta keys. A link either has a source + offer_id (destination is rebuilt
	 * from the source on every click, so changing your affiliate ID updates
	 * every link) or a manual target_url.
	 */
	const META = array(
		'target_url'    => '_alc_target_url',
		'source'        => '_alc_source',
		'offer_id'      => '_alc_offer_id',
		'offer_url'     => '_alc_offer_url',
		'image'         => '_alc_image',
		'vendor'        => '_alc_vendor',
		'redirect_type' => '_alc_redirect_type',
	);

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete' ) );
	}

	public static function register() {
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

	public static function get_meta( $post_id, $key ) {
		return isset( self::META[ $key ] ) ? (string) get_post_meta( $post_id, self::META[ $key ], true ) : '';
	}

	public static function set_meta( $post_id, $key, $value ) {
		if ( isset( self::META[ $key ] ) ) {
			update_post_meta( $post_id, self::META[ $key ], $value );
		}
	}

	/** The public, cloaked URL, e.g. https://example.com/go/my-offer/ */
	public static function cloaked_url( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return '';
		}
		$prefix = ALC_Settings::get( 'prefix' );
		if ( get_option( 'permalink_structure' ) ) {
			return home_url( user_trailingslashit( $prefix . '/' . $post->post_name ) );
		}
		return add_query_arg( 'alc', $post->post_name, home_url( '/' ) );
	}

	/**
	 * Resolve the real affiliate destination.
	 *
	 * @param int    $post_id
	 * @param string $subid   Optional tracking sub-ID.
	 * @return string Empty string if nothing usable is configured.
	 */
	public static function destination( $post_id, $subid = '' ) {
		$source_slug = self::get_meta( $post_id, 'source' );
		$offer_id    = self::get_meta( $post_id, 'offer_id' );
		$url         = '';

		if ( $source_slug && $offer_id ) {
			$source = ALC_Plugin::instance()->sources->get( $source_slug );
			if ( $source ) {
				$url = $source->build_affiliate_url( $offer_id, $subid );
			}
		}

		if ( '' === $url ) {
			$url = self::get_meta( $post_id, 'target_url' );
		}

		/**
		 * Filter the final destination before redirecting.
		 */
		$url = apply_filters( 'alc_destination_url', $url, $post_id, $subid );

		return self::is_valid_destination( $url ) ? $url : '';
	}

	/** Absolute http(s) URL with a host. (No DNS lookup: we redirect, we don't fetch.) */
	public static function is_valid_destination( $url ) {
		if ( ! is_string( $url ) || '' === $url || preg_match( '/[\s<>"]/', $url ) ) {
			return false;
		}
		$parts = wp_parse_url( $url );
		return isset( $parts['scheme'], $parts['host'] )
			&& in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true )
			&& '' !== $parts['host'];
	}

	public static function find_by_slug( $slug ) {
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'name'             => sanitize_title( $slug ),
				'post_status'      => 'publish',
				'numberposts'      => 1,
				'suppress_filters' => true,
			)
		);
		return $posts ? $posts[0] : null;
	}

	public static function find_by_offer( $source, $offer_id ) {
		$posts = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array( 'key' => self::META['source'], 'value' => $source ),
					array( 'key' => self::META['offer_id'], 'value' => $offer_id ),
				),
			)
		);
		return $posts ? (int) $posts[0] : 0;
	}

	public static function on_delete( $post_id ) {
		if ( self::POST_TYPE === get_post_type( $post_id ) ) {
			ALC_Clicks::delete_for_link( $post_id );
		}
	}
}
