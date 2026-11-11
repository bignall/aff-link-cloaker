const $ = ( id ) => document.getElementById( id );
const JVZOO_HOST = /^(?:www\.)?jvzoo\.com$/;

let items = []; // [{ url, name, product, selected }]
let tab = null;

async function saveItems() {
	await chrome.storage.session.set( { alcItems: items } );
}

function render() {
	$( 'listWrap' ).hidden = items.length === 0;
	$( 'clear' ).hidden = items.length === 0;
	const selected = items.filter( ( i ) => i.selected ).length;
	$( 'count' ).textContent = `${ selected } of ${ items.length } selected`;
	$( 'checkAll' ).checked = selected === items.length;
	$( 'send' ).textContent = `Create ${ selected } cloaked link${ selected === 1 ? '' : 's' }`;
	$( 'send' ).disabled = selected === 0;

	const list = $( 'list' );
	list.replaceChildren();
	items.forEach( ( item, i ) => {
		const li = document.createElement( 'li' );
		const box = Object.assign( document.createElement( 'input' ), { type: 'checkbox', checked: item.selected } );
		box.addEventListener( 'change', () => {
			item.selected = box.checked;
			saveItems();
			render();
		} );
		const name = Object.assign( document.createElement( 'input' ), { type: 'text', value: item.name, placeholder: `Product ${ item.product } (name looked up by your site)` } );
		name.addEventListener( 'change', () => {
			item.name = name.value.trim();
			saveItems();
		} );
		const url = Object.assign( document.createElement( 'small' ), { textContent: item.url } );
		const body = document.createElement( 'div' );
		body.append( name, url );
		li.append( box, body );
		list.append( li );
	} );
}

function setStatus( el, message, kind ) {
	$( el ).textContent = message;
	$( el ).className = kind || '';
}

async function scan() {
	setStatus( 'scanStatus', 'Scanning…' );
	const { affiliateId } = await alcGetSettings();
	let result;
	try {
		[ { result } ] = await chrome.scripting.executeScript( { target: { tabId: tab.id }, func: alcScrapeJvzoo, args: [ affiliateId ] } );
	} catch ( e ) {
		setStatus( 'scanStatus', `Couldn't read this page: ${ e.message }`, 'error' );
		return;
	}

	if ( result.affiliateId && ! affiliateId ) {
		await chrome.storage.local.set( { affiliateId: result.affiliateId } ); // Remember it for pages without links.
	}

	let added = 0;
	for ( const link of result.links ) {
		const existing = items.find( ( i ) => i.product === link.product );
		if ( existing ) {
			existing.name = existing.name || link.name;
			continue;
		}
		items.push( { ...link, selected: true } );
		added++;
	}
	await saveItems();
	render();

	if ( result.links.length === 0 ) {
		setStatus( 'scanStatus',
			result.productsWithoutLink
				? `Found ${ result.productsWithoutLink } products but none of your links. Add your JVZoo affiliate ID in Settings and scan again.`
				: 'No JVZoo products or affiliate links found on this page. Open Affiliates → Approved Products on JVZoo and scan again.',
			'error' );
	} else {
		setStatus( 'scanStatus', `Found ${ result.links.length } product${ result.links.length === 1 ? '' : 's' } (${ added } new). Scan other pages to add more.`, 'ok' );
	}
}

async function send() {
	const chosen = items.filter( ( i ) => i.selected );
	$( 'send' ).disabled = true;
	setStatus( 'summary', `Sending ${ chosen.length }…` );
	$( 'resultsWrap' ).hidden = false;
	$( 'results' ).replaceChildren();

	let data;
	try {
		data = await alcApi( '/links', { method: 'POST', body: { links: chosen.map( ( i ) => ( { url: i.url, name: i.name } ) ), draft: $( 'draft' ).checked } } );
	} catch ( e ) {
		setStatus( 'summary', e.message, 'error' );
		$( 'send' ).disabled = false;
		return;
	}

	const renamed = data.renamed ? `, ${ data.renamed } renamed` : '';
	setStatus( 'summary', `${ data.created } created${ renamed }, ${ data.exists } already on your site, ${ data.errors } not imported.`, data.errors ? 'error' : 'ok' );
	for ( const r of data.results ) {
		const li = document.createElement( 'li' );
		li.className = r.status;
		const title = Object.assign( document.createElement( 'strong' ), { textContent: r.title || r.name || r.url } );
		li.append( title, ' ' );
		const tag = Object.assign( document.createElement( 'span' ), { className: 'tag', textContent: { created: 'created', renamed: 'renamed', exists: 'already there', error: 'not imported' }[ r.status ] } );
		li.append( tag );
		if ( r.cloaked_url ) {
			const row = document.createElement( 'div' );
			row.className = 'copy';
			const field = Object.assign( document.createElement( 'input' ), { type: 'text', readOnly: true, value: r.cloaked_url } );
			const btn = Object.assign( document.createElement( 'button' ), { textContent: 'Copy' } );
			btn.addEventListener( 'click', async () => {
				await navigator.clipboard.writeText( r.cloaked_url );
				btn.textContent = 'Copied!';
				setTimeout( () => ( btn.textContent = 'Copy' ), 1500 );
			} );
			row.append( field, btn );
			li.append( row );
		}
		if ( r.message ) {
			li.append( Object.assign( document.createElement( 'small' ), { textContent: r.message } ) );
		}
		$( 'results' ).append( li );
	}

	// Keep only what wasn't imported, so a retry doesn't resend the rest.
	const done = new Set( data.results.filter( ( r ) => r.status !== 'error' ).map( ( r ) => r.url ) );
	items = items.filter( ( i ) => ! done.has( i.url ) );
	await saveItems();
	render();
}

( async () => {
	$( 'openOptions' ).addEventListener( 'click', ( e ) => {
		e.preventDefault();
		chrome.runtime.openOptionsPage();
	} );
	$( 'setup' ).addEventListener( 'click', () => chrome.runtime.openOptionsPage() );

	const s = await alcGetSettings();
	if ( ! s.siteUrl || ! s.username || ! s.appPassword ) {
		$( 'notConfigured' ).hidden = false;
		return;
	}
	$( 'main' ).hidden = false;

	[ tab ] = await chrome.tabs.query( { active: true, currentWindow: true } );
	const onJvzoo = tab && tab.url && JVZOO_HOST.test( new URL( tab.url ).host );
	$( 'scan' ).disabled = ! onJvzoo;
	$( 'pageHint' ).textContent = onJvzoo
		? 'On JVZoo, open Affiliates → Approved Products, then scan each page of the list.'
		: 'Open your Approved Products page on jvzoo.com (signed in), then click the extension again.';

	( { alcItems: items = [] } = await chrome.storage.session.get( 'alcItems' ) );
	render();

	$( 'scan' ).addEventListener( 'click', scan );
	$( 'send' ).addEventListener( 'click', send );
	$( 'clear' ).addEventListener( 'click', async () => {
		items = [];
		await saveItems();
		render();
		setStatus( 'scanStatus', '' );
	} );
	$( 'checkAll' ).addEventListener( 'change', async () => {
		items.forEach( ( i ) => ( i.selected = $( 'checkAll' ).checked ) );
		await saveItems();
		render();
	} );
} )();
