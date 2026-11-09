( function () {
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.alc-copy-btn' );
		if ( ! btn ) {
			return;
		}
		e.preventDefault();
		var url = btn.getAttribute( 'data-url' );
		var done = function () {
			var old = btn.textContent;
			btn.textContent = ( window.alcAdmin && window.alcAdmin.copied ) || 'Copied!';
			setTimeout( function () { btn.textContent = old; }, 1500 );
		};
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( url ).then( done );
		} else {
			btn.parentNode.querySelector( 'input' ).select();
			document.execCommand( 'copy' );
			done();
		}
	} );

	document.addEventListener( 'change', function ( e ) {
		if ( ! e.target.classList.contains( 'alc-check-all' ) ) {
			return;
		}
		var table = e.target.closest( 'table' );
		table.querySelectorAll( 'tbody input[type=checkbox]' ).forEach( function ( box ) {
			box.checked = e.target.checked;
		} );
	} );
} )();
