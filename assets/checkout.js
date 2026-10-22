/**
 * Live check of the German ID card numbers at checkout.
 *
 * Runs entirely in the browser and sends nothing anywhere. The server repeats every check
 * when the order is placed.
 */
( function () {
	'use strict';

	var config = window.gidavCheckout;
	if ( ! config ) {
		return;
	}

	var formats = {
		doc: /^[A-Z0-9]{9}\d$/,
		birth: /^\d{7}$/,
		expiry: /^\d{7}$/,
		check: /^\d$/,
	};

	function checkDigit( value ) {
		var weights = [ 7, 3, 1 ];
		var sum = 0;
		for ( var i = 0; i < value.length; i++ ) {
			var c = value.charAt( i );
			var n = 0;
			if ( c >= '0' && c <= '9' ) {
				n = c.charCodeAt( 0 ) - 48;
			} else if ( c >= 'A' && c <= 'Z' ) {
				n = c.charCodeAt( 0 ) - 55;
			}
			sum += n * weights[ i % 3 ];
		}
		return sum % 10;
	}

	function normalize( key, value ) {
		value = String( value || '' ).replace( /\s+/g, '' ).toUpperCase();
		if ( 'doc' === key && 11 === value.length && 'D' === value.slice( -1 ) ) {
			value = value.slice( 0, 10 );
		}
		if ( 'expiry' === key && 8 === value.length && 'D' === value.slice( -1 ) ) {
			value = value.slice( 0, 7 );
		}
		return value;
	}

	function parseDate( yymmdd, kind, todayYear ) {
		var year = 2000 + parseInt( yymmdd.slice( 0, 2 ), 10 );
		var month = parseInt( yymmdd.slice( 2, 4 ), 10 );
		var day = parseInt( yymmdd.slice( 4, 6 ), 10 );
		if ( 'birth' === kind && year > todayYear ) {
			year -= 100;
		}
		var date = new Date( Date.UTC( year, month - 1, day ) );
		if ( date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day ) {
			return null;
		}
		return { y: year, m: month, d: day };
	}

	function compare( a, b ) {
		return ( a.y - b.y ) || ( a.m - b.m ) || ( a.d - b.d );
	}

	/** Error code for one field on its own, or "". */
	function fieldError( key, value ) {
		if ( '' === value ) {
			return '';
		}
		if ( ! formats[ key ].test( value ) ) {
			return 'format';
		}
		if ( 'check' !== key && String( checkDigit( value.slice( 0, -1 ) ) ) !== value.slice( -1 ) ) {
			return 'checksum';
		}
		return '';
	}

	function inputs( section ) {
		var map = {};
		section.querySelectorAll( '[data-gidav]' ).forEach( function ( input ) {
			map[ input.getAttribute( 'data-gidav' ) ] = input;
		} );
		return map;
	}

	function setError( input, message ) {
		var id = input.id + '-gidav-error';
		var row = input.closest( '.form-row' );
		var error = document.getElementById( id );
		var described = ( input.getAttribute( 'aria-describedby' ) || '' ).split( ' ' ).filter( function ( part ) {
			return part && part !== id;
		} );

		if ( message ) {
			if ( ! error ) {
				error = document.createElement( 'span' );
				error.id = id;
				error.className = 'gidav__error';
				input.parentNode.appendChild( error );
			}
			error.textContent = message;
			described.push( id );
			input.setAttribute( 'aria-invalid', 'true' );
			if ( row ) {
				row.classList.add( 'woocommerce-invalid' );
				row.classList.remove( 'woocommerce-validated' );
			}
		} else {
			if ( error ) {
				error.parentNode.removeChild( error );
			}
			input.removeAttribute( 'aria-invalid' );
			if ( row ) {
				row.classList.remove( 'woocommerce-invalid' );
			}
		}

		if ( described.length ) {
			input.setAttribute( 'aria-describedby', described.join( ' ' ) );
		} else {
			input.removeAttribute( 'aria-describedby' );
		}
	}

	function message( key, code ) {
		return ( config.messages[ key ] && config.messages[ key ][ code ] ) || '';
	}

	function check( section, showAll ) {
		var map = inputs( section );
		var values = {};
		var complete = true;
		var errors = {};

		Object.keys( formats ).forEach( function ( key ) {
			if ( ! map[ key ] ) {
				complete = false;
				return;
			}
			values[ key ] = normalize( key, map[ key ].value );
			var code = fieldError( key, values[ key ] );
			if ( code ) {
				errors[ key ] = code;
			}
			if ( '' === values[ key ] || code ) {
				complete = false;
			}
		} );

		if ( complete ) {
			var today = config.today.split( '-' ).map( Number );
			var now = { y: today[ 0 ], m: today[ 1 ], d: today[ 2 ] };
			if ( String( checkDigit( values.doc + values.birth + values.expiry ) ) !== values.check ) {
				errors.check = 'checksum';
			} else {
				var birth = parseDate( values.birth, 'birth', now.y );
				var expiry = parseDate( values.expiry, 'expiry', now.y );
				if ( ! birth ) {
					errors.birth = 'date';
				}
				if ( ! expiry ) {
					errors.expiry = 'date';
				}
				if ( birth && expiry ) {
					if ( compare( expiry, now ) < 0 ) {
						errors.expiry = 'expired';
					}
					var age = now.y - birth.y - ( ( now.m < birth.m || ( now.m === birth.m && now.d < birth.d ) ) ? 1 : 0 );
					if ( age < parseInt( config.minAge, 10 ) ) {
						errors.birth = 'underage';
					}
				}
			}
		}

		Object.keys( map ).forEach( function ( key ) {
			var show = showAll || map[ key ].getAttribute( 'data-gidav-touched' );
			setError( map[ key ], show && errors[ key ] ? message( key, errors[ key ] ) : '' );
		} );

		var status = section.querySelector( '.gidav__status' );
		if ( status ) {
			status.textContent = complete && ! Object.keys( errors ).length ? config.valid : '';
		}
	}

	document.addEventListener( 'focusout', function ( event ) {
		var input = event.target;
		if ( ! input.matches || ! input.matches( '[data-gidav]' ) ) {
			return;
		}
		if ( '' !== input.value ) {
			input.setAttribute( 'data-gidav-touched', '1' );
		}
		check( input.closest( '.gidav' ), false );
	} );

	document.addEventListener( 'input', function ( event ) {
		var input = event.target;
		if ( ! input.matches || ! input.matches( '[data-gidav]' ) ) {
			return;
		}
		var section = input.closest( '.gidav' );
		// Re-check while typing only for fields that already show an error, or once everything is filled in.
		check( section, false );
	} );
} )();
