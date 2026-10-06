( function ( $ ) {
	'use strict';

	var cfg = window.bundletiersConfig || {};

	/* ---------- Pricing maths (port of includes/class-pricing.php) ---------- */

	function divRound( a, b ) {
		return Math.floor( ( 2 * a + b ) / ( 2 * b ) );
	}

	function toMinor( amount, decimals ) {
		return Math.round( parseFloat( amount ) * Math.pow( 10, decimals ) );
	}

	function applyTier( base, qty, type, value, decimals ) {
		var full, total, unit, bp, off;
		base = Math.max( 0, Math.round( base ) );
		qty = Math.max( 1, Math.round( qty ) );
		full = base * qty;

		if ( type === 'percent' ) {
			bp = Math.round( Math.min( 100, Math.max( 0, parseFloat( value ) ) ) * 100 );
			unit = divRound( base * ( 10000 - bp ), 10000 );
			total = unit * qty;
		} else if ( type === 'fixed_item' ) {
			off = Math.max( 0, toMinor( value, decimals ) );
			total = Math.max( 0, base - off ) * qty;
		} else if ( type === 'fixed_pack' ) {
			total = Math.max( 0, toMinor( value, decimals ) );
		} else {
			total = full;
		}
		total = Math.min( total, full );

		return {
			total: total,
			full: full,
			saved: full - total,
			unit: divRound( total, qty )
		};
	}

	/* ---------- Money formatting (matches wc_price text) ---------- */

	function formatMoney( minor ) {
		var d = cfg.decimals;
		var neg = minor < 0;
		var abs = Math.abs( minor );
		var str = String( abs );
		var intPart, fracPart, out;

		while ( str.length <= d ) {
			str = '0' + str;
		}
		intPart = d ? str.slice( 0, str.length - d ) : str;
		fracPart = d ? str.slice( str.length - d ) : '';
		intPart = intPart.replace( /\B(?=(\d{3})+(?!\d))/g, cfg.thousandSep );
		out = fracPart ? intPart + cfg.decimalSep + fracPart : intPart;
		if ( neg ) {
			out = '-' + out;
		}
		return cfg.priceFormat.replace( '%1$s', cfg.symbol ).replace( '%2$s', out );
	}

	/* ---------- Selector ---------- */

	function Selector( el ) {
		this.el = el;
		this.state = JSON.parse( el.getAttribute( 'data-bundletiers' ) );
		this.initial = { base: this.state.base, min: this.state.min, max: this.state.max };
		this.form = el.closest( 'form' );
		this.qtyInput = this.form ? this.form.querySelector( 'input[name="quantity"]' ) : null;
		this.options = Array.prototype.slice.call( el.querySelectorAll( '.bt-option' ) );
		this.live = el.querySelector( '.bt-live' );
		this.bind();
		this.refresh( true );
	}

	Selector.prototype.bind = function () {
		var self = this;

		if ( this.form ) {
			this.form.classList.add( 'bt-active' );
		}

		this.el.addEventListener( 'change', function ( e ) {
			if ( e.target.classList.contains( 'bt-radio' ) ) {
				self.setQuantity( parseInt( e.target.value, 10 ) );
				self.announce();
				self.emit();
			}
		} );

		// Quantity changed by something else: follow it.
		if ( this.qtyInput ) {
			this.qtyInput.addEventListener( 'change', function () {
				self.syncFromQuantity();
			} );
		}

		if ( this.state.variable && this.form ) {
			$( this.form )
				.on( 'found_variation.bundletiers', function ( e, variation ) {
					self.state.base = toMinor( variation.display_price, cfg.decimals );
					self.state.min = Math.max( 1, parseInt( variation.min_qty, 10 ) || 1 );
					self.state.max = variation.max_qty === '' || variation.max_qty === undefined ? -1 : parseInt( variation.max_qty, 10 );
					self.refresh( false );
				} )
				// WooCommerce touches the quantity field while showing a variation; set ours afterwards.
				.on( 'show_variation.bundletiers', function () {
					self.setQuantity( self.selectedQty() );
				} )
				.on( 'reset_data.bundletiers hide_variation.bundletiers', function () {
					self.state.base = self.initial.base;
					self.state.min = self.initial.min;
					self.state.max = self.initial.max;
					self.refresh( false );
				} );
		}
	};

	Selector.prototype.selectedQty = function () {
		var checked = this.el.querySelector( '.bt-radio:checked' );
		return checked ? parseInt( checked.value, 10 ) : 1;
	};

	Selector.prototype.setQuantity = function ( qty ) {
		if ( ! this.qtyInput ) {
			return;
		}
		if ( parseInt( this.qtyInput.value, 10 ) !== qty ) {
			this.qtyInput.value = qty;
			this.qtyInput.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
	};

	Selector.prototype.syncFromQuantity = function () {
		var qty = parseInt( this.qtyInput.value, 10 );
		var match = this.el.querySelector( '.bt-radio[value="' + qty + '"]:not(:disabled)' );
		if ( match && ! match.checked ) {
			match.checked = true;
			this.announce();
			this.emit();
		}
	};

	/** Recomputes every card, fixes the selection if it became unavailable. */
	Selector.prototype.refresh = function ( first ) {
		var self = this;
		var state = this.state;
		var selected = this.selectedQty();
		var firstEnabled = null;
		var defaultRadio = null;

		this.options.forEach( function ( option, i ) {
			var tier = state.tiers[ i ];
			var radio = option.querySelector( '.bt-radio' );
			var r = applyTier( state.base, tier.qty, tier.type, tier.value, cfg.decimals );
			var disabled = ( state.max >= 0 && tier.qty > state.max ) || tier.qty < state.min;

			option.querySelector( '.bt-total' ).textContent = formatMoney( r.total );
			option.querySelector( '.bt-full del' ).textContent = formatMoney( r.full );
			option.querySelector( '.bt-full' ).hidden = r.saved <= 0;
			option.querySelector( '.bt-unit' ).textContent = formatMoney( r.unit ) + ' ' + cfg.textPerItem;
			option.querySelector( '.bt-unit' ).hidden = tier.qty <= 1;
			option.querySelector( '.bt-save' ).textContent = cfg.textSave + ' ' + formatMoney( r.saved );
			option.querySelector( '.bt-save' ).hidden = r.saved <= 0;

			radio.disabled = disabled;
			option.classList.toggle( 'is-disabled', disabled );

			if ( ! disabled && firstEnabled === null ) {
				firstEnabled = radio;
			}
			if ( ! disabled && radio.defaultChecked ) {
				defaultRadio = radio;
			}
		} );

		// Keep the selection if still possible, else fall back to the default, then the first available.
		var current = this.el.querySelector( '.bt-radio[value="' + selected + '"]:not(:disabled)' );
		var target = current || defaultRadio || firstEnabled;
		if ( target ) {
			target.checked = true;
			this.setQuantity( parseInt( target.value, 10 ) );
		}
		this.emit();
		if ( ! first ) {
			this.announce();
		}
	};

	Selector.prototype.currentResult = function () {
		var qty = this.selectedQty();
		var tier = null;
		this.state.tiers.forEach( function ( t ) {
			if ( t.qty === qty ) {
				tier = t;
			}
		} );
		if ( ! tier ) {
			return null;
		}
		return { qty: qty, tier: tier, result: applyTier( this.state.base, qty, tier.type, tier.value, cfg.decimals ), base: this.state.base };
	};

	Selector.prototype.announce = function () {
		var cur = this.currentResult();
		if ( cur && this.live ) {
			this.live.textContent = cur.qty + ' — ' + formatMoney( cur.result.total );
		}
	};

	/** Lets a theme react (e.g. show a second currency). */
	Selector.prototype.emit = function () {
		var cur = this.currentResult();
		if ( cur ) {
			this.el.dispatchEvent( new CustomEvent( 'bundletiers:updated', { bubbles: true, detail: cur } ) );
		}
	};

	window.BundleTiers = { applyTier: applyTier, formatMoney: formatMoney, toMinor: toMinor };

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.bt-selector[data-bundletiers]' ), function ( el ) {
			el.bundletiers = new Selector( el );
		} );
	} );
}( window.jQuery ) );
