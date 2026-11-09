<?php
/**
 * Plugin settings (stored in a single option).
 *
 * @package AffLinkCloaker
 */

defined( 'ABSPATH' ) || exit;

class ALC_Settings {

	const OPTION = 'alc_settings';
	const PAGE   = 'alc-settings';

	public static function defaults() {
		return array(
			'prefix'          => 'go',
			'redirect_type'   => 307,
			'nofollow'        => 1,
			'sponsored'       => 1,
			'new_tab'         => 1,
			'track_clicks'    => 1,
			'ignore_bots'     => 1,
			'pass_query'      => 0,
			'wp_affiliate_id' => '',
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function redirect_types() {
		return array(
			301 => __( '301 Moved Permanently', 'aff-link-cloaker' ),
			302 => __( '302 Found', 'aff-link-cloaker' ),
			307 => __( '307 Temporary Redirect (recommended)', 'aff-link-cloaker' ),
		);
	}

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'on_update' ), 10, 2 );
	}

	public static function register() {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = self::defaults();

		$prefix        = isset( $input['prefix'] ) ? sanitize_title( $input['prefix'] ) : '';
		$out['prefix'] = '' !== $prefix ? $prefix : 'go';

		$type                 = isset( $input['redirect_type'] ) ? absint( $input['redirect_type'] ) : 307;
		$out['redirect_type'] = array_key_exists( $type, self::redirect_types() ) ? $type : 307;

		foreach ( array( 'nofollow', 'sponsored', 'new_tab', 'track_clicks', 'ignore_bots', 'pass_query' ) as $flag ) {
			$out[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
		}

		$out['wp_affiliate_id'] = isset( $input['wp_affiliate_id'] )
			? preg_replace( '/[^A-Za-z0-9_-]/', '', $input['wp_affiliate_id'] )
			: '';

		return $out;
	}

	/** Re-flush rewrites when the link prefix changes. */
	public static function on_update( $old, $new ) {
		$old_prefix = is_array( $old ) && isset( $old['prefix'] ) ? $old['prefix'] : '';
		$new_prefix = is_array( $new ) && isset( $new['prefix'] ) ? $new['prefix'] : '';
		if ( $old_prefix !== $new_prefix ) {
			update_option( 'alc_flush_rewrites', 1 );
		}
	}
}
