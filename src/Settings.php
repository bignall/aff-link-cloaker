<?php
/**
 * Plugin settings, stored in one option.
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker;

use AffLinkCloaker\Vendor\Bignall\WPFoundation\Plugin;

final class Settings {

	public const OPTION = 'alc_settings';
	public const GROUP  = 'alc-settings';

	/** Define this in wp-config.php to keep the key out of the database. */
	public const API_KEY_CONSTANT = 'ALC_WARRIORPLUS_API_KEY';

	private static ?Plugin $plugin = null;

	/** @return array<string, mixed> */
	public static function defaults(): array {
		return array(
			'prefix'          => 'go',
			'redirect_type'   => 307,
			'nofollow'        => 1,
			'sponsored'       => 1,
			'new_tab'         => 1,
			'track_clicks'    => 1,
			'ignore_bots'     => 1,
			'pass_query'      => 0,
			'inactive_url'    => '',
			'wp_api_key'      => '',
			'auto_sync'       => 1,
			'auto_create'     => 0,
			'remove_data'     => 0,
		);
	}

	/** @return array<string, mixed> */
	public static function all(): array {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	public static function get( string $key ): mixed {
		return self::all()[ $key ] ?? null;
	}

	/** @return array<int, string> */
	public static function redirectTypes(): array {
		return array(
			301 => __( '301 Moved Permanently', 'aff-link-cloaker' ),
			302 => __( '302 Found', 'aff-link-cloaker' ),
			307 => __( '307 Temporary Redirect (recommended)', 'aff-link-cloaker' ),
		);
	}

	public static function warriorPlusApiKey(): string {
		if ( defined( self::API_KEY_CONSTANT ) ) {
			return (string) constant( self::API_KEY_CONSTANT );
		}
		return (string) self::get( 'wp_api_key' );
	}

	public static function init( Plugin $plugin ): void {
		self::$plugin = $plugin;
		add_action( 'admin_init', array( self::class, 'register' ) );
		add_action( 'update_option_' . self::OPTION, array( self::class, 'onUpdate' ), 10, 2 );
	}

	public static function register(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
			)
		);
	}

	/**
	 * @param mixed $input Raw form input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ): array {
		$input   = is_array( $input ) ? $input : array();
		$current = self::all();
		$out     = self::defaults();

		$prefix        = sanitize_title( (string) ( $input['prefix'] ?? '' ) );
		$out['prefix'] = '' !== $prefix ? $prefix : 'go';

		$type                 = absint( $input['redirect_type'] ?? 307 );
		$out['redirect_type'] = array_key_exists( $type, self::redirectTypes() ) ? $type : 307;

		foreach ( array( 'nofollow', 'sponsored', 'new_tab', 'track_clicks', 'ignore_bots', 'pass_query', 'auto_sync', 'auto_create', 'remove_data' ) as $flag ) {
			$out[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
		}

		$inactive            = esc_url_raw( (string) ( $input['inactive_url'] ?? '' ), array( 'http', 'https' ) );
		$out['inactive_url'] = Links::isValidDestination( $inactive ) ? $inactive : '';

		// The key field is left blank to keep the saved key; tick "clear" to remove it.
		$key = trim( sanitize_text_field( (string) ( $input['wp_api_key'] ?? '' ) ) );
		if ( ! empty( $input['wp_api_key_clear'] ) ) {
			$out['wp_api_key'] = '';
		} elseif ( '' !== $key ) {
			$out['wp_api_key'] = $key;
		} else {
			$out['wp_api_key'] = (string) $current['wp_api_key'];
		}

		return $out;
	}

	/**
	 * @param mixed $old Old value.
	 * @param mixed $new New value.
	 */
	public static function onUpdate( $old, $new ): void {
		$old_prefix = is_array( $old ) ? ( $old['prefix'] ?? '' ) : '';
		$new_prefix = is_array( $new ) ? ( $new['prefix'] ?? '' ) : '';
		if ( $old_prefix !== $new_prefix && self::$plugin ) {
			// WP Foundation's RewriteTask flushes on the next `init` when this flag is set.
			update_option( self::$plugin->optionName( 'flush_rewrite_rules' ), 1, false );
		}
		if ( is_array( $old ) && is_array( $new ) && ( $old['wp_api_key'] ?? '' ) !== ( $new['wp_api_key'] ?? '' ) ) {
			Sources\WarriorPlus\WarriorPlusSource::forgetCache();
		}
	}
}
