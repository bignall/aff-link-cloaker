/**
 * Runs inside a JVZoo page (injected by the popup) and returns the affiliate links it can find.
 *
 * It doesn't depend on JVZoo's exact layout:
 *  1. Any JVZoo affiliate link in the page: https://www.jvzoo.com/c/<affiliate>/<product>
 *     (or jvz1.com … jvz9.com), in links, text boxes, data attributes or text.
 *  2. Products linked to their Affiliate Information page (…/affiliateinfo[new]/index/<id>),
 *     for when links only appear in a "Get Link" popup: the link is built from the product ID
 *     and your affiliate ID (taken from any link on the page, or from the extension's settings).
 *
 * Must stay a single self-contained function: chrome.scripting.executeScript serialises it.
 *
 * @param {string} knownAffiliateId Affiliate ID from settings, or ''.
 * @returns {{links: Array<{url: string, name: string, product: string}>, affiliateId: string, stats: object}}
 */
function alcScrapeJvzoo( knownAffiliateId ) {
	const LINK_RE = /https?:\/\/(?:www\.)?(?:jvzoo\.com|jvz[1-9]\.com)\/c\/(\d+)\/(\d+)\/?/gi;
	const INFO_RE = /\/affiliate\/affiliateinfo(?:new)?\/index\/(\d+)/i;
	const clean = ( s ) => ( s || '' ).replace( /\s+/g, ' ' ).trim();

	const byProduct = new Map(); // product id -> { url, name, product }
	const names = new Map(); // product id -> name
	let affiliateId = ( knownAffiliateId || '' ).trim();
	const stats = { pageLinks: 0, infoLinks: 0, built: 0 };

	// The row/card an element belongs to, for finding the product's name next to its link.
	const container = ( el ) => el.closest( 'tr, li, .row, .card, .panel, [class*="product"], [class*="item"]' ) || el.parentElement;

	// 1) Product names (and IDs) from links to the product's Affiliate Information page.
	document.querySelectorAll( 'a[href]' ).forEach( ( a ) => {
		const m = a.getAttribute( 'href' ).match( INFO_RE );
		if ( ! m ) {
			return;
		}
		stats.infoLinks++;
		// Link text, unless it's a generic label ("Details", "Info"); then the link's title.
		const generic = /^(details|info|view|more|affiliate info(rmation)?)$/i;
		const name = [ clean( a.textContent ), clean( a.getAttribute( 'title' ) ) ].find( ( n ) => n && ! generic.test( n ) ) || '';
		if ( name || ! names.has( m[ 1 ] ) ) {
			names.set( m[ 1 ], name || names.get( m[ 1 ] ) || '' );
		}
	} );

	// 2) Affiliate links anywhere in the page.
	const sources = [];
	document.querySelectorAll( 'a[href], input[value], textarea, [data-clipboard-text], [data-link], [data-url], [data-href]' ).forEach( ( el ) => {
		for ( const v of [ el.getAttribute( 'href' ), el.value, el.getAttribute( 'data-clipboard-text' ), el.getAttribute( 'data-link' ), el.getAttribute( 'data-url' ), el.getAttribute( 'data-href' ) ] ) {
			if ( v ) {
				sources.push( [ v, el ] );
			}
		}
	} );
	sources.push( [ document.body ? document.body.innerText : '', null ] );

	for ( const [ text, el ] of sources ) {
		for ( const m of String( text ).matchAll( LINK_RE ) ) {
			const [ url, aff, product ] = m;
			affiliateId = affiliateId || aff;
			if ( byProduct.has( product ) ) {
				continue;
			}
			stats.pageLinks++;
			let name = names.get( product ) || '';
			if ( ! name && el ) {
				const row = container( el );
				const info = row && row.querySelector( 'a[href*="affiliateinfo"]' );
				name = clean( info ? info.textContent : '' );
			}
			byProduct.set( product, { url: url.replace( /\/$/, '' ), name, product } );
		}
	}

	// 3) Products we know only by ID: build the link from your affiliate ID.
	if ( affiliateId ) {
		for ( const [ product, name ] of names ) {
			if ( ! byProduct.has( product ) ) {
				stats.built++;
				byProduct.set( product, { url: `https://www.jvzoo.com/c/${ affiliateId }/${ product }`, name, product } );
			}
		}
	}

	return { links: [ ...byProduct.values() ], affiliateId, stats, productsWithoutLink: affiliateId ? 0 : names.size };
}
