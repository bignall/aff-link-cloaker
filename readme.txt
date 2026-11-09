=== Affiliate Link Cloaker ===
Contributors: arbe
Tags: affiliate, links, cloaking, redirect, warriorplus
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later

Import offers from affiliate networks (starting with WarriorPlus) and turn them into short, cloaked, trackable links.

== Description ==

* Import WarriorPlus offers from an affiliate link, a WarriorPlus offer URL or a bare offer code — one at a time or up to 50 per batch.
* Reads the offer's title, description and image from the offer/sales page (Open Graph tags, <title>, meta description).
* Each offer becomes an "Affiliate Link" with a cloaked URL: https://yoursite.com/go/offer-slug/
* Destinations are built from your WarriorPlus affiliate ID on every click, so changing your ID in Settings updates every link at once.
* Sub-ID tracking: https://yoursite.com/go/offer-slug/facebook/ → https://warriorplus.com/o2/a/OFFER/YOURID/facebook
* Click counts (total / 30 days / 7 days), bot and HEAD-request filtering, hashed IPs only.
* 301 / 302 / 307 redirects (site default plus per-link override), noindex headers.
* Shortcode for use in posts; the cloaked URL also works anywhere off-site (email, social, ads).
* Manual links: any URL can be cloaked, with or without a source.
* Extensible: add other networks by implementing ALC_Source and hooking `alc_register_sources`.

== Installation ==

1. Upload the `aff-link-cloaker` folder to `/wp-content/plugins/` (or upload the zip via Plugins → Add New → Upload).
2. Activate the plugin.
3. Go to Affiliate Links → Settings and enter your WarriorPlus affiliate ID (the last part of any of your affiliate links: https://warriorplus.com/o2/a/OFFER/YOURID).
4. Use pretty permalinks (Settings → Permalinks, anything except "Plain") for /go/slug/ URLs. With plain permalinks links fall back to ?alc=slug.
5. Affiliate Links → Import Offers, paste offers one per line:

    https://warriorplus.com/o2/a/abc123/456789
    abc123
    abc123 | https://vendor-sales-page.example.com/

== Shortcode ==

    [alc_link slug="offer-slug"]Get it now[/alc_link]
    [alc_link id="42" subid="sidebar" class="button"]
    [alc_link slug="offer-slug" url_only="1"]

== Hooks ==

* `alc_register_sources( ALC_Source_Registry $registry )` — register more networks.
* `alc_destination_url( $url, $link_id, $subid )` — filter the final destination.
* `alc_before_redirect( $link_id, $url, $subid )` — runs just before the redirect.

== Notes ==

* WarriorPlus does not publish a public offers API, so offer details are read from the public offer page. Many vendors' sales pages live on their own domains; give the sales page URL ("code | url") for the best title/image.
* When only an offer code is given, details are read by following your affiliate link to the sales page. That request may show as a hop in your WarriorPlus stats — supply the sales page URL to avoid it, or untick "Read offer details".
* "Cloaking" here means a branded redirect URL. It does not hide that the link is an affiliate link; keep your FTC/ASA affiliate disclosures in place, and check the rules of any ad platform you post cloaked links on.

== Changelog ==

= 0.1.0 =
* Initial release: WarriorPlus source, cloaked redirects, click tracking, bulk import, shortcode.
