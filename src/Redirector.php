<?php
/**
 * Serves /{prefix}/{slug}[/{subid}]/ by redirecting to the affiliate URL.
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker;

final class Redirector {

	public static function init(): void {
		add_action( 'init', array( self::class, 'addRewriteRules' ) );
		add_filter( 'query_vars', array( self::class, 'queryVars' ) );
		add_action( 'template_redirect', array( self::class, 'handle' ), 0 );
	}

	public static function addRewriteRules(): void {
		$prefix = preg_quote( (string) Settings::get( 'prefix' ), '#' );
		add_rewrite_rule( '^' . $prefix . '/([^/]+)/([^/]+)/?$', 'index.php?alc=$matches[1]&alc_subid=$matches[2]', 'top' );
		add_rewrite_rule( '^' . $prefix . '/([^/]+)/?$', 'index.php?alc=$matches[1]', 'top' );
	}

	/**
	 * @param list<string> $vars Public query vars.
	 * @return list<string>
	 */
	public static function queryVars( array $vars ): array {
		$vars[] = 'alc';
		$vars[] = 'alc_subid';
		return $vars;
	}

	public static function handle(): void {
		$slug = (string) get_query_var( 'alc' );
		if ( '' === $slug ) {
			return;
		}

		$post  = Links::findBySlug( $slug );
		$subid = sanitize_text_field( (string) get_query_var( 'alc_subid' ) );
		$url   = $post ? Links::destination( $post->ID, $subid ) : '';
		if ( '' === $url ) {
			self::notFound();
			return;
		}

		if ( Settings::get( 'pass_query' ) && ! empty( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$extra = array_diff_key( wp_unslash( $_GET ), array_flip( array( 'alc', 'alc_subid' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$extra = array_filter( $extra, 'is_scalar' );
			if ( $extra ) {
				$url = add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $extra ) ), $url );
			}
		}

		if ( Clicks::shouldCount() ) {
			Clicks::record( $post->ID, $subid );
		}

		do_action( 'alc_before_redirect', $post->ID, $url, $subid );

		$type = (int) Links::meta( $post->ID, 'redirect_type' );
		if ( ! array_key_exists( $type, Settings::redirectTypes() ) ) {
			$type = (int) Settings::get( 'redirect_type' );
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		// wp_redirect, not wp_safe_redirect: affiliate destinations are external by design.
		wp_redirect( esc_url_raw( $url ), $type, 'Affiliate Link Cloaker' ); // phpcs:ignore WordPress.Security.SafeRedirect
		exit;
	}

	/** Render the theme's 404 for unknown or broken links. */
	private static function notFound(): void {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}
