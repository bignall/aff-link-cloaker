<?php
/**
 * REST API used by the browser extension to send affiliate links to this site.
 *
 *   GET  /wp-json/alc/v1/status   Check the connection and the user's permissions.
 *   POST /wp-json/alc/v1/links    Import links: {"links":[{"url":"…","name":"…"}],"draft":false}
 *
 * Authentication is WordPress's own: an Application Password (Users → Profile) sent as HTTP
 * Basic auth, which WordPress only accepts over HTTPS (or on local development sites).
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class Rest {

	public const NAMESPACE = 'alc/v1';
	public const MAX_LINKS = 200;

	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	public static function routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'status' ),
				'permission_callback' => array( self::class, 'canImport' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/links',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'importLinks' ),
				'permission_callback' => array( self::class, 'canImport' ),
				'args'                => array(
					'links'    => array(
						'type'     => 'array',
						'required' => true,
						'minItems' => 1,
						'maxItems' => self::MAX_LINKS,
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'url'  => array( 'type' => 'string', 'required' => true ),
								'name' => array( 'type' => 'string' ),
							),
						),
					),
					'draft'    => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'category' => array(
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					),
				),
			)
		);
	}

	/** @return true|WP_Error */
	public static function canImport() {
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}
		return new WP_Error(
			'alc_forbidden',
			is_user_logged_in()
				? __( 'Your WordPress user is not allowed to create affiliate links.', 'aff-link-cloaker' )
				: __( 'Not signed in. Check the site address, user name and Application Password.', 'aff-link-cloaker' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	public static function status(): WP_REST_Response {
		$user = wp_get_current_user();
		return new WP_REST_Response(
			array(
				'plugin'      => 'aff-link-cloaker',
				'version'     => App::version(),
				'site'        => get_bloginfo( 'name' ),
				'user'        => $user->display_name,
				'can_publish' => current_user_can( 'publish_posts' ),
				'max_links'   => self::MAX_LINKS,
			)
		);
	}

	public static function importLinks( WP_REST_Request $request ): WP_REST_Response {
		$rows = array();
		foreach ( (array) $request['links'] as $link ) {
			$rows[] = array(
				'url'  => sanitize_text_field( (string) ( $link['url'] ?? '' ) ),
				'name' => sanitize_text_field( (string) ( $link['name'] ?? '' ) ),
			);
		}

		$options = array(
			'status'   => $request['draft'] || ! current_user_can( 'publish_posts' ) ? 'draft' : 'publish',
			'category' => (int) $request['category'],
		);

		$out = array();
		foreach ( Importer::importLinks( $rows, $options ) as $i => $r ) {
			$item = array(
				'url'    => $rows[ $i ]['url'],
				'name'   => $r['offer']->name,
				'status' => $r['status'],
			);
			if ( $r['post_id'] ) {
				$item['post_id']     = $r['post_id'];
				$item['title']       = html_entity_decode( get_the_title( $r['post_id'] ), ENT_QUOTES, 'UTF-8' );
				$item['cloaked_url'] = 'publish' === get_post_status( $r['post_id'] ) ? Links::cloakedUrl( $r['post_id'] ) : '';
				$item['edit_url']    = (string) get_edit_post_link( $r['post_id'], 'raw' );
			}
			if ( $r['error'] ) {
				$item['message'] = $r['error']->get_error_message();
			}
			$out[] = $item;
		}

		$counts = array_count_values( array_column( $out, 'status' ) );
		return new WP_REST_Response(
			array(
				'created' => $counts['created'] ?? 0,
				'exists'  => $counts['exists'] ?? 0,
				'errors'  => $counts['error'] ?? 0,
				'results' => $out,
			)
		);
	}
}
