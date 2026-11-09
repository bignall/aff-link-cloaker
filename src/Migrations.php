<?php
/**
 * Data migrations, run by WP Foundation on upgrade.
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker;

use AffLinkCloaker\Vendor\Bignall\WPFoundation\Plugin;

final class Migrations {

	/**
	 * 0.2.0: destinations are no longer built from a stored affiliate ID at click time; every
	 * link stores its real affiliate URL (as WarriorPlus's API returns it). Bake the URLs
	 * 0.1.0 would have built into each link, then drop the old setting and options.
	 */
	public static function storeBuiltDestinations( Plugin $plugin ): void {
		$settings = get_option( Settings::OPTION );
		$aff_id   = is_array( $settings ) ? (string) ( $settings['wp_affiliate_id'] ?? '' ) : '';

		if ( '' !== $aff_id ) {
			$ids = get_posts(
				array(
					'post_type'   => Links::POST_TYPE,
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery
						array( 'key' => Links::META['source'], 'value' => 'warriorplus' ),
					),
				)
			);
			foreach ( $ids as $id ) {
				$code = Links::meta( (int) $id, 'offer_id' );
				if ( preg_match( '/^[a-z0-9]+$/i', $code ) ) {
					update_post_meta( (int) $id, Links::META['target_url'], 'https://warriorplus.com/o2/a/' . $code . '/' . rawurlencode( $aff_id ) );
				}
			}
		}

		if ( is_array( $settings ) ) {
			unset( $settings['wp_affiliate_id'] );
			update_option( Settings::OPTION, $settings );
		}
		delete_option( 'alc_db_version' );
		delete_option( 'alc_flush_rewrites' );
	}
}
