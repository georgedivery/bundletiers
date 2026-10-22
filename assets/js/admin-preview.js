( function ( $ ) {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		var root = document.querySelector( '.bt-preview' );
		var selector = root ? root.querySelector( '.bt-selector' ) : null;
		var keys = [ 'style', 'style_bg', 'style_border_color', 'style_accent', 'style_text', 'style_border_width', 'style_radius', 'text_save', 'text_per_item' ];
		var hex = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;
		var presets = {};
		var prefix;
		var units;
		var saves;
		var last = '';

		if ( ! root || ! selector ) {
			return;
		}
		prefix = root.getAttribute( 'data-name' );
		try {
			presets = JSON.parse( root.getAttribute( 'data-presets' ) || '{}' );
		} catch ( e ) {
			presets = {};
		}

		function field( key ) {
			return document.querySelector( '[name="' + prefix + '[' + key + ']"]' );
		}

		function value( key ) {
			var f = field( key );
			return f ? String( f.value ).trim() : '';
		}

		function whole( raw, min, max, fallback ) {
			var n = parseFloat( raw );
			return isNaN( n ) ? fallback : Math.min( max, Math.max( min, Math.round( n ) ) );
		}

		// The amounts printed in the cards stay; only the words around them change.
		units = [].slice.call( selector.querySelectorAll( '.bt-unit' ) );
		saves = [].slice.call( selector.querySelectorAll( '.bt-save' ) );
		units.forEach( function ( el ) {
			var text = el.textContent.trim();
			var word = value( 'text_per_item' );
			el.setAttribute( 'data-money', word && text.slice( -word.length ) === word ? text.slice( 0, text.length - word.length ).trim() : text );
		} );
		saves.forEach( function ( el ) {
			var text = el.textContent.trim();
			var word = value( 'text_save' );
			el.setAttribute( 'data-money', word && 0 === text.indexOf( word ) ? text.slice( word.length ).trim() : text );
		} );

		function matches( preset ) {
			return Object.keys( preset ).every( function ( key ) {
				return value( key ).toLowerCase() === String( preset[ key ] ).toLowerCase();
			} );
		}

		function update() {
			var layout = [ 'cards', 'list', 'minimal' ].indexOf( value( 'style' ) ) >= 0 ? value( 'style' ) : 'cards';

			[ [ 'style_bg', '--bt-bg' ], [ 'style_border_color', '--bt-border' ], [ 'style_accent', '--bt-accent' ] ].forEach( function ( pair ) {
				if ( hex.test( value( pair[ 0 ] ) ) ) {
					selector.style.setProperty( pair[ 1 ], value( pair[ 0 ] ) );
				}
			} );
			if ( hex.test( value( 'style_text' ) ) ) {
				selector.style.setProperty( '--bt-text', value( 'style_text' ) );
				selector.style.setProperty( '--bt-muted', 'color-mix(in srgb, ' + value( 'style_text' ) + ' 62%, ' + value( 'style_bg' ) + ')' );
			} else {
				selector.style.removeProperty( '--bt-text' );
				selector.style.removeProperty( '--bt-muted' );
			}
			selector.style.setProperty( '--bt-border-width', whole( value( 'style_border_width' ), 0, 10, 1 ) + 'px' );
			selector.style.setProperty( '--bt-radius', whole( value( 'style_radius' ), 0, 40, 4 ) + 'px' );
			[ 'cards', 'list', 'minimal' ].forEach( function ( name ) {
				selector.classList.toggle( 'bt-style-' + name, name === layout );
			} );

			units.forEach( function ( el ) {
				el.textContent = el.getAttribute( 'data-money' ) + ' ' + value( 'text_per_item' );
			} );
			saves.forEach( function ( el ) {
				el.textContent = value( 'text_save' ) + ' ' + el.getAttribute( 'data-money' );
			} );

			[].forEach.call( root.querySelectorAll( '.bt-preset' ), function ( button ) {
				var preset = presets[ button.getAttribute( 'data-preset' ) ];
				button.classList.toggle( 'is-active', !! preset && matches( preset ) );
			} );
		}

		// WooCommerce's color picker changes the value without an event we can rely on, so look at the values too.
		function tick() {
			var now = keys.map( value ).join( '|' );
			if ( now !== last ) {
				last = now;
				update();
			}
		}

		keys.forEach( function ( key ) {
			var f = field( key );
			if ( f ) {
				[ 'input', 'change', 'keyup' ].forEach( function ( type ) {
					f.addEventListener( type, tick );
				} );
			}
		} );
		window.setInterval( tick, 250 );

		root.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '.bt-preset' );
			var preset = button ? presets[ button.getAttribute( 'data-preset' ) ] : null;
			if ( ! preset ) {
				return;
			}
			Object.keys( preset ).forEach( function ( key ) {
				var f = field( key );
				if ( ! f ) {
					return;
				}
				f.value = String( preset[ key ] );
				if ( $ && $.fn && 'function' === typeof $.fn.iris && f.classList.contains( 'colorpick' ) && '' !== String( preset[ key ] ) ) {
					$( f ).iris( 'color', String( preset[ key ] ) );
				}
				f.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );
			tick();
		} );

		tick();
	} );
}( window.jQuery ) );
