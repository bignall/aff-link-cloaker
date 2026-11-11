const $ = ( id ) => document.getElementById( id );

( async () => {
	const s = await alcGetSettings();
	$( 'siteUrl' ).value = s.siteUrl;
	$( 'username' ).value = s.username;
	$( 'affiliateId' ).value = s.affiliateId;
	if ( s.appPassword ) {
		$( 'appPassword' ).placeholder = 'Saved – leave blank to keep';
	}
} )();

function show( message, ok ) {
	const el = $( 'result' );
	el.textContent = message;
	el.className = ok ? 'ok' : 'error';
}

$( 'settings' ).addEventListener( 'submit', async ( e ) => {
	e.preventDefault();
	let siteUrl = $( 'siteUrl' ).value.trim().replace( /\/+$/, '' );
	try {
		const u = new URL( siteUrl );
		if ( ! /^https?:$/.test( u.protocol ) ) {
			throw new Error();
		}
		siteUrl = u.origin + u.pathname.replace( /\/+$/, '' );
	} catch {
		show( 'Enter the full site address, starting with https://', false );
		return;
	}

	// Ask for access to this one site (the extension only has JVZoo access by default).
	const granted = await chrome.permissions.request( { origins: [ alcOriginPattern( siteUrl ) ] } );
	if ( ! granted ) {
		show( 'The extension needs permission to reach your site to send links to it.', false );
		return;
	}

	const update = {
		siteUrl,
		username: $( 'username' ).value.trim(),
		affiliateId: $( 'affiliateId' ).value.replace( /\D/g, '' ),
	};
	const password = $( 'appPassword' ).value.trim();
	if ( password ) {
		update.appPassword = password;
	}
	await chrome.storage.local.set( update );
	$( 'appPassword' ).value = '';
	$( 'appPassword' ).placeholder = 'Saved – leave blank to keep';

	show( 'Testing…', true );
	try {
		const status = await alcApi( '/status' );
		show( `Connected to “${ status.site }” as ${ status.user } (plugin ${ status.version }).` + ( status.can_publish ? '' : ' Links will be created as drafts: this user can’t publish.' ), true );
	} catch ( err ) {
		show( err.message, false );
	}
} );
