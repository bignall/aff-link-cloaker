/** Shared settings and WordPress REST calls for the popup and the options page. */

const ALC_SETTINGS_KEYS = [ 'siteUrl', 'username', 'appPassword', 'affiliateId' ];

async function alcGetSettings() {
	const s = await chrome.storage.local.get( ALC_SETTINGS_KEYS );
	return {
		siteUrl: ( s.siteUrl || '' ).replace( /\/+$/, '' ),
		username: s.username || '',
		appPassword: s.appPassword || '',
		affiliateId: s.affiliateId || '',
	};
}

/** Origin pattern for the WordPress site, for chrome.permissions. */
function alcOriginPattern( siteUrl ) {
	const u = new URL( siteUrl );
	return `${ u.protocol }//${ u.host }/*`;
}

/**
 * Call the plugin's REST API on the configured site.
 *
 * @returns {Promise<object>} Parsed JSON; throws Error with a readable message on failure.
 */
async function alcApi( path, { method = 'GET', body } = {}, settings ) {
	const s = settings || ( await alcGetSettings() );
	if ( ! s.siteUrl || ! s.username || ! s.appPassword ) {
		throw new Error( 'Set your WordPress site, user name and Application Password in the extension options first.' );
	}
	const request = ( url ) => fetch( url, {
		method,
		credentials: 'omit',
		headers: {
			Authorization: 'Basic ' + btoa( unescape( encodeURIComponent( `${ s.username }:${ s.appPassword.replace( /\s+/g, '' ) }` ) ) ),
			'Content-Type': 'application/json',
			Accept: 'application/json',
		},
		body: body ? JSON.stringify( body ) : undefined,
	} );
	let res;
	try {
		res = await request( `${ s.siteUrl }/wp-json/alc/v1${ path }` );
		if ( res.status === 404 ) {
			// Sites with plain permalinks only answer at ?rest_route=.
			res = await request( `${ s.siteUrl }/?rest_route=${ encodeURIComponent( `/alc/v1${ path }` ) }` );
		}
	} catch ( e ) {
		throw new Error( `Couldn't reach ${ s.siteUrl }. Check the address, and that the extension was allowed to access it.` );
	}
	let data = null;
	try {
		data = await res.json();
	} catch ( e ) {
		// Not JSON: handled below.
	}
	if ( ! res.ok ) {
		if ( res.status === 404 ) {
			throw new Error( 'The Affiliate Link Cloaker plugin (0.3.0 or newer) wasn\'t found on that site. Is it active, and are permalinks enabled?' );
		}
		throw new Error( ( data && data.message ) || `The site answered with HTTP ${ res.status }.` );
	}
	if ( ! data ) {
		throw new Error( 'The site didn\'t answer with JSON. Check the site address.' );
	}
	return data;
}
