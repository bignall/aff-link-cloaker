<?php
/**
 * [alc_link] shortcode.
 *
 * Usage:
 *   [alc_link slug="my-offer"]Get it now[/alc_link]
 *   [alc_link id="42" subid="sidebar" class="button"]
 *   [alc_link slug="my-offer" url_only="1"]  -> prints just the cloaked URL
 *
 * @package AffLinkCloaker
 */

defined( 'ABSPATH' ) || exit;

class ALC_Shortcode {

	public static function init() {
		add_shortcode( 'alc_link', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts, $content = '' ) {
		$atts = shortcode_atts(
			array(
				'id'       => 0,
				'slug'     => '',
				'subid'    => '',
				'class'    => '',
				'url_only' => '',
			),
			$atts,
			'alc_link'
		);

		$post = $atts['id'] ? get_post( (int) $atts['id'] ) : ALC_Post_Type::find_by_slug( $atts['slug'] );
		if ( ! $post || ALC_Post_Type::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return '';
		}

		$url = ALC_Post_Type::cloaked_url( $post->ID );
		if ( '' !== $atts['subid'] ) {
			$subid = sanitize_title( $atts['subid'] );
			$url   = get_option( 'permalink_structure' )
				? trailingslashit( untrailingslashit( $url ) ) . $subid . '/'
				: add_query_arg( 'alc_subid', $subid, $url );
		}

		if ( $atts['url_only'] ) {
			return esc_url( $url );
		}

		return self::anchor( $url, '' !== trim( $content ) ? do_shortcode( $content ) : esc_html( get_the_title( $post ) ), $atts['class'] );
	}

	public static function anchor( $url, $inner_html, $class = '' ) {
		$settings = ALC_Settings::all();
		$rel      = array();
		if ( $settings['nofollow'] ) {
			$rel[] = 'nofollow';
		}
		if ( $settings['sponsored'] ) {
			$rel[] = 'sponsored';
		}
		$target = '';
		if ( $settings['new_tab'] ) {
			$target = ' target="_blank"';
			$rel[]  = 'noopener';
		}

		return sprintf(
			'<a href="%s" class="%s"%s rel="%s">%s</a>',
			esc_url( $url ),
			esc_attr( trim( 'alc-link ' . $class ) ),
			$target,
			esc_attr( implode( ' ', $rel ) ),
			$inner_html
		);
	}
}
