<?php
/**
 * Public API for other plugins and themes.
 *
 * These functions are the supported way to use the cloaker from outside: the classes under
 * AffLinkCloaker\ are internal and may change. Check `function_exists( 'alc_api_version' )`
 * before calling any of them.
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

use AffLinkCloaker\Clicks;
use AffLinkCloaker\Links;

defined( 'ABSPATH' ) || exit;

/**
 * API version. Bumped when a function is added (minor) or changed incompatibly (major).
 */
function alc_api_version(): string {
	return '1.0';
}

/**
 * Post type that stores links.
 */
function alc_link_post_type(): string {
	return Links::POST_TYPE;
}

/**
 * A published link's ID, from its ID or slug. 0 if there's no such published link.
 *
 * @param int|string $link Link ID or slug.
 */
function alc_get_link_id( int|string $link ): int {
	if ( is_int( $link ) || ctype_digit( (string) $link ) ) {
		$id = (int) $link;
		return Links::POST_TYPE === get_post_type( $id ) && 'publish' === get_post_status( $id ) ? $id : 0;
	}
	$post = Links::findBySlug( (string) $link );
	return $post ? $post->ID : 0;
}

/**
 * The public cloaked URL for a published link, optionally with a sub-ID that is recorded with
 * each click (and passed to networks that support one). '' if the link doesn't exist or isn't
 * published.
 *
 * @param int|string $link  Link ID or slug.
 * @param string     $subid Sub-ID, e.g. "sidebar" or "adn-42". Sanitized like a slug.
 */
function alc_cloaked_url( int|string $link, string $subid = '' ): string {
	$id = alc_get_link_id( $link );
	return $id ? Links::cloakedUrl( $id, $subid ) : '';
}

/**
 * Whether the affiliate network says the link no longer earns (paused, banned, offer inactive...).
 *
 * @param int $link_id Link ID.
 */
function alc_link_is_dead( int $link_id ): bool {
	return Links::POST_TYPE === get_post_type( $link_id ) && Links::isDead( $link_id );
}

/**
 * Published links, by title.
 *
 * @param array<string, mixed> $args Extra get_posts() arguments, e.g. a tax_query.
 * @return array<int, string> Link ID => title.
 */
function alc_get_links( array $args = array() ): array {
	$posts = get_posts(
		array_merge(
			array(
				'numberposts'      => 500,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => true,
			),
			$args,
			array(
				'post_type'   => Links::POST_TYPE,
				'post_status' => 'publish',
			)
		)
	);
	$out = array();
	foreach ( $posts as $post ) {
		$out[ $post->ID ] = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
	}
	return $out;
}

/**
 * Click counts, grouped.
 *
 *     // Clicks per sub-ID starting with "adn-" in October, per day.
 *     alc_click_counts( [ 'subid_prefix' => 'adn-', 'since' => '2026-10-01', 'until' => '2026-10-31', 'group_by' => [ 'subid', 'day' ] ] );
 *
 * @param array{link_id?: int, subid?: string, subid_prefix?: string, since?: string, until?: string, group_by?: string|list<string>} $args
 *        link_id: one link (default all). subid / subid_prefix: exact sub-ID, or sub-IDs starting
 *        with this. since / until: UTC dates (Y-m-d) or datetimes, inclusive. group_by: any of
 *        "link", "subid", "day" (default none: one total row).
 * @return list<array{link_id?: int, subid?: string, day?: string, clicks: int}>
 */
function alc_click_counts( array $args = array() ): array {
	return Clicks::grouped( $args );
}
