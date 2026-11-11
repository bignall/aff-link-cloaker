/**
 * Runs inside a JVZoo page (injected by the popup) and returns your affiliate links on it.
 *
 * 1. JVZoo's own "Get Link" boxes, which are already in the page:
 *    - Approved Products list: each row has a popup with the product name
 *      (#modalTitle<id>) and your link (<input id="affiliate_link<id>">).
 *    - A product's Affiliate Information page: <input id="affiliate_link"> holds your link
 *      and the page title is "<Product> - Affiliate Information - JVZoo".
 * 2. Only if a page has none of those: any JVZoo affiliate link in the page, and products
 *    linked to their Affiliate Information page, with links built from your affiliate ID.
 *
 * Links under someone else's affiliate ID are ignored. JVZoo's pages include its own links
 * (affiliate ID 1, e.g. the "Product of the day" banner), which aren't yours.
 *
 * Must stay a single self-contained function: chrome.scripting.executeScript serialises it.
 *
 * @param {string} knownAffiliateId Affiliate ID from settings, or ''.
 * @returns {{links: Array<{url: string, name: string, product: string}>, affiliateId: string, stats: object, productsWithoutLink: number}}
 */
function alcScrapeJvzoo( knownAffiliateId ) {
	const LINK_RE = /https?:\/\/(?:www\.)?(?:jvzoo\.com|jvz[1-9]\.com)\/c\/(\d+)\/(\d+)\/?/i;
	const LINK_RE_ALL = new RegExp( LINK_RE.source, 'gi' );
	const INFO_RE = /\/affiliate\/affiliateinfo(?:new)?\/index\/(\d+)\/?$/i; // The product's own page, not its ?tab= pages.
	const TAB_LABEL = /^(details|info|view|more|integrations|bonuses|seller information|affiliate info(rmation)?|get link)$/i;
	const clean = ( s ) => ( s || '' ).replace( /\s+/g, ' ' ).trim();
	const parse = ( url ) => {
		const m = String( url || '' ).match( LINK_RE );
		return m ? { url: m[ 0 ].replace( /\/$/, '' ), affiliate: m[ 1 ], product: m[ 2 ] } : null;
	};

	const stats = { linkBoxes: 0, pageLinks: 0, built: 0, ignored: 0 };
	const byProduct = new Map(); // product id -> { url, name, product }

	// Your affiliate ID: settings, then JVZoo's hidden field on product pages.
	let affiliateId = ( knownAffiliateId || '' ).trim();
	if ( ! affiliateId ) {
		const field = document.querySelector( 'input[name="affiliate_id"]' );
		affiliateId = field && /^\d+$/.test( field.value ) ? field.value : '';
	}

	// 1) JVZoo's "Get Link" boxes.
	const pageTitleName = clean( document.title.replace( /\s+-\s+Affiliate Information\s+-\s+JVZoo\s*$/i, '' ) );
	document.querySelectorAll( 'input[id^="affiliate_link"]' ).forEach( ( input ) => {
		const link = parse( input.value );
		if ( ! link ) {
			return;
		}
		stats.linkBoxes++;
		affiliateId = affiliateId || link.affiliate;
		const id = input.id.slice( 'affiliate_link'.length ); // "<product id>" in the list, "" on a product page.
		const title = id ? document.getElementById( 'modalTitle' + id ) : null;
		const name = title ? clean( title.textContent ) : ( pageTitleName !== clean( document.title ) ? pageTitleName : '' );
		if ( ! byProduct.has( link.product ) ) {
			byProduct.set( link.product, { url: link.url, name, product: link.product } );
		}
	} );

	if ( byProduct.size > 0 ) {
		return { links: [ ...byProduct.values() ], affiliateId, stats, productsWithoutLink: 0 };
	}

	// 2) Fallback for other layouts.

	// Product names from links to each product's own Affiliate Information page (not its tabs).
	const names = new Map(); // product id -> name
	document.querySelectorAll( 'a[href]' ).forEach( ( a ) => {
		const m = a.getAttribute( 'href' ).match( INFO_RE );
		if ( ! m ) {
			return;
		}
		const name = [ clean( a.textContent ), clean( a.getAttribute( 'title' ) ) ].find( ( n ) => n && ! TAB_LABEL.test( n ) ) || '';
		if ( name ) {
			names.set( m[ 1 ], names.get( m[ 1 ] ) || name ); // First real name wins.
		} else if ( ! names.has( m[ 1 ] ) ) {
			names.set( m[ 1 ], '' );
		}
	} );

	// Affiliate links anywhere in the page.
	const found = [];
	const values = [];
	document.querySelectorAll( 'a[href], input[value], textarea, [data-clipboard-text], [data-link], [data-url], [data-href]' ).forEach( ( el ) => {
		for ( const v of [ el.getAttribute( 'href' ), el.value, el.getAttribute( 'data-clipboard-text' ), el.getAttribute( 'data-link' ), el.getAttribute( 'data-url' ), el.getAttribute( 'data-href' ) ] ) {
			if ( v ) {
				values.push( v );
			}
		}
	} );
	values.push( document.body ? document.body.innerText : '' );
	for ( const text of values ) {
		for ( const m of String( text ).matchAll( LINK_RE_ALL ) ) {
			found.push( parse( m[ 0 ] ) );
		}
	}

	// Without a known ID, yours is the most common one, never JVZoo's own (1).
	if ( ! affiliateId ) {
		const counts = new Map();
		found.filter( ( l ) => l.affiliate !== '1' ).forEach( ( l ) => counts.set( l.affiliate, ( counts.get( l.affiliate ) || 0 ) + 1 ) );
		affiliateId = [ ...counts.entries() ].sort( ( a, b ) => b[ 1 ] - a[ 1 ] )[ 0 ]?.[ 0 ] || '';
	}

	for ( const link of found ) {
		if ( link.affiliate !== affiliateId ) {
			stats.ignored++;
			continue;
		}
		if ( ! byProduct.has( link.product ) ) {
			stats.pageLinks++;
			byProduct.set( link.product, { url: link.url, name: names.get( link.product ) || '', product: link.product } );
		}
	}

	// Products known only by ID: build the link from your affiliate ID.
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
