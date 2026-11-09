<?php
/**
 * [alc_link] shortcode.
 *
 *   [alc_link slug="my-offer"]Get it now[/alc_link]
 *   [alc_link id="42" subid="sidebar" class="button"]
 *   [alc_link slug="my-offer" url_only="1"]   Prints just the cloaked URL.
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker;

final class Shortcode {

	public static function init(): void {
		add_shortcode( 'alc_link', array( self::class, 'render' ) );
	}

	/**
	 * @param array<string, string>|string $atts    Attributes.
	 * @param string|null                  $content Link text.
	 */
	public static function render( $atts, $content = '' ): string {
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

		$post = $atts['id'] ? get_post( (int) $atts['id'] ) : Links::findBySlug( (string) $atts['slug'] );
		if ( ! $post || Links::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return '';
		}

		$url = Links::cloakedUrl( $post->ID, (string) $atts['subid'] );
		if ( $atts['url_only'] ) {
			return esc_url( $url );
		}

		$inner = '' !== trim( (string) $content ) ? do_shortcode( (string) $content ) : esc_html( get_the_title( $post ) );
		return self::anchor( $url, $inner, (string) $atts['class'] );
	}

	public static function anchor( string $url, string $inner_html, string $class = '' ): string {
		$settings = Settings::all();
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
