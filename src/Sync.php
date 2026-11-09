<?php
/**
 * Keeps links in step with the networks: new affiliate URLs, renamed offers, and offers you've
 * been paused or banned from. Runs on WP-Cron (scheduled by WP Foundation) and on demand.
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker;

final class Sync {

	public const LAST_RUN_OPTION = 'alc_last_sync';

	public static function init(): void {
		add_action( Bootstrap::SYNC_HOOK, array( self::class, 'cron' ) );
	}

	public static function cron(): void {
		if ( Settings::get( 'auto_sync' ) ) {
			self::run( (bool) Settings::get( 'auto_create' ) );
		}
	}

	/**
	 * @param bool $create Also create links for newly approved offers.
	 * @return array{time: int, created: int, updated: int, dead: int, errors: list<string>}
	 */
	public static function run( bool $create = false ): array {
		$result = array(
			'time'    => time(),
			'created' => 0,
			'updated' => 0,
			'dead'    => 0,
			'errors'  => array(),
		);

		foreach ( App::instance()->sources->all() as $source ) {
			if ( ! $source->canListOffers() ) {
				continue;
			}
			$offers = $source->listOffers( true );
			if ( is_wp_error( $offers ) ) {
				$result['errors'][] = $source->label() . ': ' . $offers->get_error_message();
				continue;
			}
			foreach ( $offers as $offer ) {
				$r = Importer::save( $offer, array( 'update_only' => ! ( $create && $offer->isPromotable() ) ) );
				if ( is_wp_error( $r ) ) {
					continue; // Offer with no link on this site: nothing to do.
				}
				if ( $r['created'] ) {
					++$result['created'];
				} else {
					++$result['updated'];
				}
				if ( Links::isDead( $r['post_id'] ) ) {
					++$result['dead'];
				}
			}
		}

		update_option( self::LAST_RUN_OPTION, $result, false );
		return $result;
	}
}
