( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		document.addEventListener( 'click', function ( event ) {
			var add = event.target.closest( '.bt-add' );
			var remove = event.target.closest( '.bt-remove' );
			var editor;

			if ( add ) {
				editor = add.closest( '.bt-editor' );
				var tpl = editor.querySelector( '.bt-row-template' );
				var next = parseInt( tpl.getAttribute( 'data-next' ), 10 ) || 0;
				var tbody = editor.querySelector( 'tbody' );
				tbody.insertAdjacentHTML( 'beforeend', tpl.innerHTML.replace( /__INDEX__/g, String( next ) ) );
				tpl.setAttribute( 'data-next', String( next + 1 ) );
				var qty = tbody.lastElementChild.querySelector( '.bt-qty' );
				if ( qty ) {
					qty.focus();
				}
			}

			if ( remove ) {
				editor = remove.closest( '.bt-editor' );
				// Keep at least one row; the server also guarantees a qty 1 tier.
				if ( editor.querySelectorAll( '.bt-row' ).length > 1 ) {
					remove.closest( '.bt-row' ).remove();
				}
			}
		} );

		// Product tab: show custom tiers only in "custom" mode.
		var mode = document.getElementById( '_bundletiers_mode' );
		var wrap = document.querySelector( '.bt-custom-wrap' );
		if ( mode && wrap ) {
			mode.addEventListener( 'change', function () {
				wrap.style.display = mode.value === 'custom' ? '' : 'none';
			} );
		}
	} );
}() );
