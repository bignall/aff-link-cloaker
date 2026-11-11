=== Affiliate Link Cloaker ===
Contributors: arbe
Tags: affiliate, links, cloaking, redirect, warriorplus, jvzoo
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.3.0
License: GPLv2 or later

Pull your affiliate offers from WarriorPlus and JVZoo and turn them into short, cloaked, trackable links.

== Description ==

Website and guide: https://bignall.github.io/aff-link-cloaker/

* Lists every WarriorPlus offer you're an affiliate for, straight from the WarriorPlus V2 API, with an "Approved only" filter. Tick the offers you want and get a cloaked link for each, e.g. https://yoursite.com/go/ai-video-maker/
* The affiliate URL is the same one WarriorPlus's "Get Link" button shows (the API's promote_url). No scraping, no WarriorPlus login stored.
* JVZoo: paste your JVZoo affiliate links (https://www.jvzoo.com/c/<affiliate>/<product>, or the jvz1.com–jvz9.com short links). Product names are filled in from JVZoo, and sub-IDs are passed to JVZoo as its tracking ID (?tid=), so they show up in your JVZoo stats. JVZoo's API only covers vendors' own products, so JVZoo offers can't be listed automatically.
* Browser extension for JVZoo: while you're signed in to JVZoo, it reads your Approved Products page and creates the cloaked links on your site in one click. See browser-extension/README.md.
* Or paste a list of affiliate links from any network, one per line ("Name | URL" or just the URL). A network page pasted by mistake (a sales or offer page instead of your affiliate link) is rejected with an explanation.
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

== REST API ==

Used by the browser extension; authenticate with an Application Password (Users → Profile).

    GET  /wp-json/alc/v1/status
    POST /wp-json/alc/v1/links   {"links":[{"url":"https://www.jvzoo.com/c/1/2","name":"Optional"}],"draft":false}

Links get the same checks as the Paste tab, and existing links are reported, not duplicated. Requires a user who can edit posts; users who can't publish get drafts.

== Hooks ==

* `alc_register_sources( SourceRegistry $sources )`: add more networks (implement `AffLinkCloaker\Sources\Source`).
* `alc_destination_url( $url, $link_id, $subid )`: filter the final destination, e.g. to pass the sub-ID to a network that supports it.
* `alc_before_redirect( $link_id, $url, $subid )`: runs just before the redirect.

== Notes ==

* Sub-IDs are always recorded in your click log. JVZoo links also pass them to JVZoo as `?tid=`. They are not passed to WarriorPlus, because its docs don't describe a sub-ID format for promote URLs; use the `alc_destination_url` filter if you know yours.
* The WarriorPlus V2 API is marked beta by WarriorPlus.
* "Cloaking" here means a branded redirect URL. Keep your affiliate disclosures, and check the rules of any ad platform you post these links on.

== Development ==

    composer install          # also prefixes WP Foundation into vendor-prefixed/ with Strauss
    bin/build-zip.sh          # release zip in dist/, without dev tools

To release: bump "Version:" in aff-link-cloaker.php and "Stable tag:" here, optionally add
release-notes/vX.Y.Z.md, commit, then push a tag `vX.Y.Z`. The Release workflow builds the zip
and publishes a GitHub release with it attached. Start the notes file with a heading such as
`# vX.Y.Z: Short summary` to use it as the release title (otherwise the title is the tag).
Without a notes file, GitHub generates notes from the commits.

Strauss rewrites `use Bignall\WPFoundation\...` in src/ to `AffLinkCloaker\Vendor\Bignall\WPFoundation\...`; that's expected and committed.

== Changelog ==

= 0.3.0 =
* JVZoo support: paste JVZoo affiliate links; product names are looked up from JVZoo, sub-IDs are passed as JVZoo's tid tracking ID, and the same product isn't added twice.
* Pasting a network's page instead of your affiliate link (e.g. a WarriorPlus offer page) is now rejected with an explanation instead of becoming a link that earns nothing.
* Browser extension for JVZoo's Approved Products page, and a REST API (alc/v1) it uses to create links.
* Edit screen: the shortcode is a proper row with a Copy button, and WordPress's Excerpt box is now a "Short description" box for your own notes.
* Links list: new Shortcode column with a Copy button.
* Developers: the `Source` interface gained `hasOfferApi()` and `applySubId()`, and `offerFromUrl()` may return a WP_Error. Custom sources registered with `alc_register_sources` need updating.

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
