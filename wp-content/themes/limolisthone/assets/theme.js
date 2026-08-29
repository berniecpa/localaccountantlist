/* Mobile navigation toggle. */
( function () {
	'use strict';

	var toggle = document.querySelector( '.llh-nav-toggle' );
	var nav = document.getElementById( 'llh-nav' );

	if ( toggle && nav ) {
		toggle.addEventListener( 'click', function () {
			var open = nav.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
	}
} )();
