<?php
/**
 * Click log table (created, updated and dropped by WP Foundation).
 *
 * @package AffLinkCloaker
 */

declare(strict_types=1);

namespace AffLinkCloaker;

use AffLinkCloaker\Vendor\Bignall\WPFoundation\Database\Table;

final class ClicksTable extends Table {

	public function name(): string {
		return 'alc_clicks';
	}

	public function columns(): string {
		return "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
link_id bigint(20) unsigned NOT NULL,
clicked_at datetime NOT NULL,
subid varchar(100) NOT NULL DEFAULT '',
referrer varchar(255) NOT NULL DEFAULT '',
ip_hash char(64) NOT NULL DEFAULT '',
user_agent varchar(255) NOT NULL DEFAULT '',
PRIMARY KEY  (id),
KEY link_id (link_id),
KEY subid (subid),
KEY clicked_at (clicked_at)";
	}
}
