=== Affiliate Link Cloaker ===
Contributors: arbe
Tags: affiliate, links, cloaking, redirect, warriorplus
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.2.1
License: GPLv2 or later

Pull your approved affiliate offers from WarriorPlus and turn them into short, cloaked, trackable links.

== Description ==

* Lists every WarriorPlus offer you're an affiliate for, straight from the WarriorPlus V2 API, with an "Approved only" filter. Tick the offers you want and get a cloaked link for each, e.g. https://yoursite.com/go/ai-video-maker/
* The affiliate URL is the same one WarriorPlus's "Get Link" button shows (the API's promote_url). No scraping, no WarriorPlus login stored.
* Or paste a list of affiliate links from any network, one per line ("Name | URL" or just the URL).
* Twice-daily sync (WP-Cron): picks up changed affiliate URLs and renamed offers, flags links whose offer you've been paused or banned from or that has ended, and optionally creates links for newly approved offers.
* Fallback URL: send clicks on dead offers somewhere useful instead of a page that won't pay you.
* Click counts (total / 30 days / 7 days), sub-IDs (/go/slug/facebook/), bots and HEAD requests ignored, IPs stored only as salted hashes.
* 301 / 302 / 307 redirects, noindex headers, 404 for unknown links.
* Shortcode, and the cloaked URL works anywhere off-site (email, social, ads).
* Built on WP Foundation (activation, upgrades and migrations, uninstall, multisite, deactivation survey).

== Installation ==

1. Install the release zip (Plugins → Add New → Upload), or from source run `composer install` in the plugin folder.
2. Affiliate Links → Settings: paste your WarriorPlus API key (WarriorPlus → My Account → API Key, https://warriorplus.com/account/api). To keep it out of the database, add `define( 'ALC_WARRIORPLUS_API_KEY', '...' );` to wp-config.php instead. Click "Test WarriorPlus connection".
3. Affiliate Links → Import Offers → WarriorPlus: tick offers, click "Create cloaked links for selected offers".
4. Use pretty permalinks (anything but "Plain") for /go/slug/ URLs; with plain permalinks links fall back to ?alc=slug.

== Shortcode ==

    [alc_link slug="ai-video-maker"]Get it now[/alc_link]
    [alc_link id="42" subid="sidebar" class="button"]
    [alc_link slug="ai-video-maker" url_only="1"]

== Hooks ==

* `alc_register_sources( SourceRegistry $sources )`: add more networks (implement `AffLinkCloaker\Sources\Source`).
* `alc_destination_url( $url, $link_id, $subid )`: filter the final destination, e.g. to pass the sub-ID to a network that supports it.
* `alc_before_redirect( $link_id, $url, $subid )`: runs just before the redirect.

== Notes ==

* Sub-IDs are recorded in your click log. They are not passed to WarriorPlus, because its docs don't describe a sub-ID format for promote URLs; use the `alc_destination_url` filter if you know yours.
* The WarriorPlus V2 API is marked beta by WarriorPlus.
* "Cloaking" here means a branded redirect URL. Keep your affiliate disclosures, and check the rules of any ad platform you post these links on.

== Development ==

    composer install          # also prefixes WP Foundation into vendor-prefixed/ with Strauss
    bin/build-zip.sh          # release zip in dist/, without dev tools

To release: bump "Version:" in aff-link-cloaker.php and "Stable tag:" here, optionally add
release-notes/vX.Y.Z.md, commit, then push a tag `vX.Y.Z`. The Release workflow builds the zip
and publishes a GitHub release with it attached.

Strauss rewrites `use Bignall\WPFoundation\...` in src/ to `AffLinkCloaker\Vendor\Bignall\WPFoundation\...`; that's expected and committed.

== Changelog ==

= 0.2.1 =
* Import results link each new cloaked link to its edit screen again.
* Updated WP Foundation to 0.1.3: uninstall now also removes the link categories' hierarchy cache.
* Release builds resolve dependencies for PHP 8.1.

= 0.2.0 =
* WarriorPlus V2 API: list your affiliate offers (approved only or all) and create cloaked links in bulk.
* Paste a list of affiliate links from any network.
* Background sync, dead-offer detection and fallback URL.
* Rebuilt on WP Foundation (PHP 8.1+, WordPress 6.4+). Uninstall only deletes data if you opt in under Settings.
* Removed the "affiliate ID" setting and page scraping; links now store the exact affiliate URL. Existing links are migrated automatically.

= 0.1.0 =
* Initial release.
