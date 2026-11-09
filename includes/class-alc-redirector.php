<?php
/**
 * Handles /{prefix}/{slug}[/{subid}] requests and redirects to the affiliate URL.
 *
 * @package AffLinkCloaker
 */

defined( 'ABSPATH' ) || exit;

class ALC_Redirector {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 0 );
	}

	public static function add_rewrite_rules() {
		$prefix = preg_quote( ALC_Settings::get( 'prefix' ), '#' );
		add_rewrite_rule( '^' . $prefix . '/([^/]+)/([^/]+)/?$', 'index.php?alc=$matches[1]&alc_subid=$matches[2]', 'top' );
		add_rewrite_rule( '^' . $prefix . '/([^/]+)/?$', 'index.php?alc=$matches[1]', 'top' );
	}

	public static function maybe_flush() {
		if ( get_option( 'alc_flush_rewrites' ) ) {
			delete_option( 'alc_flush_rewrites' );
			flush_rewrite_rules();
		}
	}

	public static function query_vars( $vars ) {
		$vars[] = 'alc';
		$vars[] = 'alc_subid';
		return $vars;
	}

	public static function handle() {
		$slug = get_query_var( 'alc' );
		if ( ! $slug ) {
			return;
		}

		$post  = ALC_Post_Type::find_by_slug( $slug );
		$subid = sanitize_text_field( (string) get_query_var( 'alc_subid' ) );
		$url   = $post ? ALC_Post_Type::destination( $post->ID, $subid ) : '';
		if ( '' === $url ) {
			self::not_found();
			return;
		}

		if ( ALC_Settings::get( 'pass_query' ) && ! empty( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$extra = array_diff_key( wp_unslash( $_GET ), array_flip( array( 'alc', 'alc_subid' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$extra = array_filter( $extra, 'is_scalar' );
			if ( $extra ) {
				$url = add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $extra ) ), $url );
			}
		}

		$settings = ALC_Settings::all();
		$is_head = isset( $_SERVER['REQUEST_METHOD'] ) && 'HEAD' === $_SERVER['REQUEST_METHOD'];
		if ( $settings['track_clicks'] && ! $is_head && ! ( $settings['ignore_bots'] && ALC_Clicks::is_bot() ) ) {
			ALC_Clicks::record( $post->ID, $subid );
		}

		do_action( 'alc_before_redirect', $post->ID, $url, $subid );

		$type = (int) ALC_Post_Type::get_meta( $post->ID, 'redirect_type' );
		if ( ! array_key_exists( $type, ALC_Settings::redirect_types() ) ) {
			$type = (int) $settings['redirect_type'];
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		// wp_redirect (not wp_safe_redirect): affiliate destinations are external by design.
		wp_redirect( esc_url_raw( $url ), $type, 'Affiliate Link Cloaker' ); // phpcs:ignore WordPress.Security.SafeRedirect
		exit;
	}

	/** Render the theme's 404 for unknown or broken links. */
	private static function not_found() {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}
