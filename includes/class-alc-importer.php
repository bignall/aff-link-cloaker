<?php
/**
 * Turns source offers into Affiliate Link posts.
 *
 * @package AffLinkCloaker
 */

defined( 'ABSPATH' ) || exit;

class ALC_Importer {

	/**
	 * Import (or refresh) one offer.
	 *
	 * @param string $source_slug
	 * @param string $input       Offer URL, affiliate link or offer code.
	 * @param array  $args {
	 *     @type string $sales_url Optional sales page to read details from.
	 *     @type string $slug      Optional cloaked slug.
	 *     @type string $status    Post status, default 'publish'.
	 *     @type bool   $fetch     Read offer details from the source site (default true).
	 *     @type int    $category  Optional link category term ID.
	 * }
	 * @return array|WP_Error { post_id, created, warning }
	 */
	public static function import( $source_slug, $input, array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'sales_url' => '',
				'slug'      => '',
				'status'    => 'publish',
				'fetch'     => true,
				'category'  => 0,
			)
		);

		$source = ALC_Plugin::instance()->sources->get( $source_slug );
		if ( ! $source ) {
			return new WP_Error( 'alc_unknown_source', __( 'Unknown affiliate source.', 'aff-link-cloaker' ) );
		}

		$parsed = $source->parse_input( $input );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		if ( ! $source->is_configured() && '' === $parsed['affiliate_url'] ) {
			return new WP_Error(
				'alc_not_configured',
				/* translators: %s: source name */
				sprintf( __( 'Set your %s affiliate ID in Settings, or paste a full affiliate link.', 'aff-link-cloaker' ), $source->get_label() )
			);
		}

		$sales_url = $args['sales_url'] ? esc_url_raw( $args['sales_url'] ) : '';
		$details   = array();
		$warning   = '';
		if ( $args['fetch'] ) {
			$details = $source->fetch_offer( $parsed, $sales_url );
			if ( is_wp_error( $details ) ) {
				$warning = $details->get_error_message();
				$details = array();
			}
		}

		$title = ! empty( $details['title'] ) ? $details['title'] : sprintf( '%s offer %s', $source->get_label(), $parsed['offer_id'] );

		$existing = ALC_Post_Type::find_by_offer( $source_slug, $parsed['offer_id'] );
		$postarr  = array(
			'post_type'   => ALC_Post_Type::POST_TYPE,
			'post_title'  => wp_strip_all_tags( $title ),
			'post_status' => $args['status'],
		);
		if ( ! empty( $details['description'] ) ) {
			$postarr['post_excerpt'] = sanitize_textarea_field( $details['description'] );
		}
		if ( $args['slug'] ) {
			$postarr['post_name'] = sanitize_title( $args['slug'] );
		}

		if ( $existing ) {
			$postarr['ID'] = $existing;
			unset( $postarr['post_status'] ); // Don't flip status on refresh.
			if ( empty( $details['title'] ) ) {
				unset( $postarr['post_title'] ); // Keep the existing title if nothing new was read.
			}
			if ( ! $args['slug'] ) {
				unset( $postarr['post_name'] ); // Keep the existing cloaked URL stable.
			}
			$post_id = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$post_id = wp_insert_post( wp_slash( $postarr ), true );
		}

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		ALC_Post_Type::set_meta( $post_id, 'source', $source_slug );
		ALC_Post_Type::set_meta( $post_id, 'offer_id', $parsed['offer_id'] );
		if ( $parsed['affiliate_url'] ) {
			// Fallback destination if the account ID is ever cleared.
			ALC_Post_Type::set_meta( $post_id, 'target_url', $parsed['affiliate_url'] );
		}
		$offer_url = ! empty( $details['offer_url'] ) ? $details['offer_url'] : $sales_url;
		if ( $offer_url ) {
			ALC_Post_Type::set_meta( $post_id, 'offer_url', esc_url_raw( $offer_url ) );
		}
		if ( ! empty( $details['image'] ) ) {
			ALC_Post_Type::set_meta( $post_id, 'image', esc_url_raw( $details['image'] ) );
		}
		if ( ! empty( $details['vendor'] ) ) {
			ALC_Post_Type::set_meta( $post_id, 'vendor', sanitize_text_field( $details['vendor'] ) );
		}
		if ( $args['category'] ) {
			wp_set_object_terms( $post_id, (int) $args['category'], ALC_Post_Type::TAXONOMY, true );
		}

		return array(
			'post_id' => (int) $post_id,
			'created' => ! $existing,
			'warning' => $warning,
		);
	}

	/**
	 * Parse a bulk textarea. One offer per line:
	 *   {offer url or code}
	 *   {offer url or code} | {sales page url}
	 *
	 * @return array<int,array{input:string,sales_url:string}>
	 */
	public static function parse_bulk( $text ) {
		$rows = array();
		foreach ( preg_split( '/\R/', (string) $text ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			$bits   = array_map( 'trim', explode( '|', $line, 2 ) );
			$rows[] = array(
				'input'     => $bits[0],
				'sales_url' => isset( $bits[1] ) ? $bits[1] : '',
			);
		}
		return $rows;
	}
}
