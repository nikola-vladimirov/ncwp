/**
 * Nashenski Commerce: office, locker and address pickers, shared by every checkout.
 *
 * The checkout-specific files (pickers-classic.js for the classic and page builder checkouts,
 * pickers-block.js for the block checkout) read the customer's details and render the Picker
 * component below; a choice is written to the courier cookie the shipping method reads, then the
 * checkout file asks WooCommerce to price the cart again.
 *
 * Also here: courier logos, the delivery type and the "Free shipping over" hint under each option,
 * the free shipping progress bar, and "Complete the fields to calculate" instead of "Free" for
 * options that still wait for a choice.
 *
 * No build step: plain JavaScript with WordPress's React (wp.element).
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.element ) {
		return;
	}

	var cfg = window.nashenskiPickers || {};
	var t = cfg.i18n || {};
	var el = wp.element;
	var h = el.createElement;
	var useState = el.useState;
	var useEffect = el.useEffect;
	var useRef = el.useRef;
	var useId = el.useId;

	/* Couriers: AJAX actions and how a choice goes into the cookie. */
	var COURIERS = {
		econt: {
			cookie: 'nashenski--econt-address',
			lists: { office: [ 'nashenski_econt_load_offices', 'offices' ], automat: [ 'nashenski_econt_load_offices', 'offices' ] },
			streets: [ 'nashenski_econt_load_streets', 'list' ],
			quarters: true,
			map: 'econt',
			publicData: true,
			key: function ( item ) {
				return String( item.code || item.id || '' );
			},
			pick: function ( item ) {
				return { selectedOffice: item ? item.code : null, selectedOfficeIsAPS: item ? !! item.isAPS : null };
			},
		},
		speedy: {
			cookie: 'nashenski--speedy-address',
			lists: { office: [ 'nashenski_speedy_load_offices', 'offices' ], automat: [ 'nashenski_speedy_load_offices', 'offices' ] },
			streets: [ 'nashenski_speedy_search_address', 'search' ],
			quarters: true,
			map: 'speedy',
			pick: function ( item ) {
				return { selectedOffice: item ? item.id : null, selectedOfficeType: item ? item.type || null : null };
			},
		},
		pigeon: {
			cookie: 'nashenski--pigeon-address',
			// Offices and lockers come from a public list without a login; streets do not.
			publicLists: true,
			lists: { office: [ 'nashenski_pigeon_load_offices', 'offices' ], locker: [ 'nashenski_pigeon_load_lockers', 'lockers' ] },
			streets: [ 'nashenski_pigeon_load_streets', 'list' ],
			pick: function ( item, type ) {
				return 'locker' === type ? { selectedLocker: item ? item.id : null } : { selectedOffice: item ? item.id : null };
			},
		},
		boxnow: {
			cookie: 'nashenski--boxnow-apm',
			lists: { apm: [ 'nashenski_boxnow_load_apms', 'apms' ] },
			pick: function ( item ) {
				return { selectedApm: item ? item.id : null };
			},
		},
	};

	function itemKey( courier, item ) {
		var c = COURIERS[ courier ];

		return c && c.key ? c.key( item ) : String( item.id || item.code || '' );
	}

	/* ---------- cookies, storage, AJAX ---------- */

	function readCookie( name ) {
		var match = document.cookie.match( new RegExp( '(?:^|; )' + name.replace( /[-]/g, '\\-' ) + '=([^;]*)' ) );

		return match ? decodeURIComponent( match[ 1 ] ) : '';
	}

	/*
	 * JSON for the cookie. The server sanitizes it as text before decoding it, which would cut
	 * "<...>" and "%41" out of the middle of the JSON, so these three characters are written as
	 * JSON escapes: the value decodes to the very same text.
	 */
	function safeJson( value ) {
		return JSON.stringify( value ).replace( /[<>%]/g, function ( c ) {
			return '\\u00' + c.charCodeAt( 0 ).toString( 16 );
		} );
	}

	function writeCookie( name, value ) {
		var expires = new Date( Date.now() + 24 * 60 * 60 * 1000 ).toUTCString();

		document.cookie = name + '=' + encodeURIComponent( safeJson( value ) ) + ';expires=' + expires + ';path=/;SameSite=Lax';
	}

	/**
	 * Write the cookie and call refresh() once the value really changed. The checkout re-renders
	 * after a refresh and the picker mounts again with the same value, which then changes nothing.
	 */
	var refreshTimer = null;
	var pendingRefresh = null;

	/*
	 * Refresh once the customer leaves a picker text field: refreshing re-renders the checkout and
	 * would take the focus away mid-word. Choices from a list refresh at once.
	 */
	function flush() {
		var active = document.activeElement;

		if ( ! pendingRefresh ) {
			return;
		}

		if ( active && active.closest && active.closest( '.nashenski-picker' ) && /^(INPUT|TEXTAREA)$/.test( active.tagName ) && 'combobox' !== active.getAttribute( 'role' ) ) {
			if ( ! active.nashenskiWaiting ) {
				active.nashenskiWaiting = true;
				active.addEventListener( 'blur', function () {
					active.nashenskiWaiting = false;
					window.setTimeout( flush, 50 );
				}, { once: true } );
			}

			return;
		}

		var refresh = pendingRefresh;

		pendingRefresh = null;
		refresh();
	}

	function commit( name, value, refresh ) {
		var json = safeJson( value );

		if ( readCookie( name ) === json ) {
			return;
		}

		writeCookie( name, value );
		pendingRefresh = refresh;
		window.clearTimeout( refreshTimer );
		refreshTimer = window.setTimeout( flush, 300 );
	}

	function remember( key, value ) {
		try {
			window.localStorage.setItem( 'nashenski-picker-' + key, JSON.stringify( value ) );
		} catch ( e ) {}
	}

	function recall( key ) {
		try {
			return JSON.parse( window.localStorage.getItem( 'nashenski-picker-' + key ) || 'null' );
		} catch ( e ) {
			return null;
		}
	}

	var cache = {};

	function ajax( action, params ) {
		var body = new FormData();
		var id = action + '|' + JSON.stringify( params );

		if ( cache[ id ] ) {
			return cache[ id ];
		}

		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce || '' );
		Object.keys( params ).forEach( function ( key ) {
			body.append( key, params[ key ] == null ? '' : params[ key ] );
		} );

		cache[ id ] = fetch( cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				return json && json.data ? json.data : {};
			} )
			.catch( function ( error ) {
				delete cache[ id ];
				throw error;
			} );

		return cache[ id ];
	}

	/* ---------- text helpers ---------- */

	function addressOf( item ) {
		var a = item.address;

		if ( a && typeof a === 'object' ) {
			return a.fullAddress || a.fullAddressString || a.localAddressString || '';
		}

		if ( typeof a === 'string' ) {
			return a;
		}

		// BOX NOW: street and town.
		return [ item.addressLine1, item.addressLine2 ].map( function ( part ) {
			return String( part || '' ).trim();
		} ).filter( Boolean ).join( ', ' ) || item.fullAddress || '';
	}

	function nameOf( item ) {
		return String( item.name || item.label || item.title || item.id || '' );
	}

	function describe( item ) {
		var name = nameOf( item );
		var address = addressOf( item );

		return address && address.indexOf( name ) === -1 ? name + ', ' + address : name;
	}

	function normalize( text ) {
		return String( text || '' ).toLowerCase().replace( /\s+/g, ' ' ).trim();
	}

	/* Every word of the query appears somewhere in the text. */
	function matches( text, query ) {
		var hay = normalize( text );

		return normalize( query ).split( ' ' ).every( function ( word ) {
			return ! word || hay.indexOf( word ) !== -1;
		} );
	}

	/* The cookie fields every courier shares. */
	function baseCookie( data, type ) {
		var a = data.address || {};
		var c = data.company || {};

		return {
			billing_to_company: c.to_company ? '1' : '',
			billing_company_mol: c.mol || '',
			billing_company: c.company || '',
			billing_vat_number: c.vat || '',
			type: type,
			receiver: ( ( a.first_name || '' ) + ' ' + ( a.last_name || '' ) ).trim(),
			phone: a.phone || '',
			state: a.state || '',
			city: a.city || '',
			postcode: a.postcode || '',
			country: a.country || 'BG',
			payment: data.payment || '',
		};
	}

	/* ---------- combobox: search box with a list under it ---------- */

	function Combobox( props ) {
		var id = useId();
		var _open = useState( false ), open = _open[ 0 ], setOpen = _open[ 1 ];
		var _active = useState( 0 ), active = _active[ 0 ], setActive = _active[ 1 ];
		var options = props.options;
		var listRef = useRef( null );

		useEffect( function () {
			setActive( 0 );
		}, [ props.query ] );

		useEffect( function () {
			if ( open && listRef.current ) {
				var node = listRef.current.children[ active ];

				if ( node && node.scrollIntoView ) {
					node.scrollIntoView( { block: 'nearest' } );
				}
			}
		}, [ active, open ] );

		function choose( option ) {
			setOpen( false );
			props.onChoose( option );
		}

		function onKeyDown( e ) {
			if ( 'ArrowDown' === e.key ) {
				e.preventDefault();
				setOpen( true );
				setActive( Math.min( active + 1, options.length - 1 ) );
			} else if ( 'ArrowUp' === e.key ) {
				e.preventDefault();
				setActive( Math.max( active - 1, 0 ) );
			} else if ( 'Enter' === e.key ) {
				// Never submit the checkout from here.
				e.preventDefault();

				if ( open && options[ active ] ) {
					choose( options[ active ] );
				}
			} else if ( 'Escape' === e.key ) {
				setOpen( false );
			}
		}

		var showList = open && ( options.length || props.empty );

		return h( 'div', { className: 'nashenski-picker__combo' },
			h( 'label', { className: 'nashenski-picker__label', htmlFor: id }, props.label ),
			h( 'input', {
				id: id,
				type: 'text',
				className: 'nashenski-picker__input',
				role: 'combobox',
				autoComplete: 'off',
				spellCheck: false,
				'aria-autocomplete': 'list',
				'aria-expanded': showList ? 'true' : 'false',
				'aria-controls': id + '-list',
				'aria-activedescendant': showList && options[ active ] ? id + '-opt-' + active : undefined,
				placeholder: props.placeholder,
				value: props.query,
				onChange: function ( e ) {
					props.onQuery( e.target.value );
					setOpen( true );
				},
				onFocus: function () {
					setOpen( true );
				},
				onBlur: function () {
					window.setTimeout( function () {
						setOpen( false );
					}, 150 );
				},
				onKeyDown: onKeyDown,
			} ),
			showList ? h( 'ul', { className: 'nashenski-picker__list', role: 'listbox', id: id + '-list', ref: listRef },
				options.length ? options.map( function ( option, index ) {
					return h( 'li', {
						key: option.key,
						id: id + '-opt-' + index,
						role: 'option',
						'aria-selected': index === active ? 'true' : 'false',
						className: 'nashenski-picker__option' + ( index === active ? ' is-active' : '' ),
						onMouseDown: function ( e ) {
							e.preventDefault();
							choose( option );
						},
						onMouseEnter: function () {
							setActive( index );
						},
					},
						h( 'span', { className: 'nashenski-picker__option-name' }, option.title ),
						option.detail ? h( 'span', { className: 'nashenski-picker__option-detail' }, option.detail ) : null
					);
				} ) : h( 'li', { className: 'nashenski-picker__option nashenski-picker__option--empty', 'aria-disabled': 'true' }, props.empty )
			) : null
		);
	}

	/* ---------- the courier's public map (Econt office locator, Speedy office map) ---------- */

	var MAP_ORIGINS = { econt: 'https://officelocator.econt.com', speedy: 'https://services.speedy.bg' };

	function mapUrl( courier, type, data, cityId ) {
		var base = ( cfg.maps || {} )[ courier ];
		var a = data.address || {};

		if ( 'econt' === courier ) {
			return base + '?city=' + encodeURIComponent( a.city || '' ) + '&officeType=' + ( 'automat' === type ? 'aps' : 'office' ) + '&shopUrl=' + encodeURIComponent( window.location.origin );
		}

		return base + '?showOfficesList=0&lang=bg&selectOfficeButtonCaption=' + encodeURIComponent( t.choose || 'OK' ) + ( cityId ? '&siteID=' + encodeURIComponent( cityId ) : '' );
	}

	/* The office the map sent, in the shape of the courier's list items. */
	function itemFromMap( courier, data ) {
		if ( 'econt' === courier ) {
			var office = data && data.office;

			return office && office.code ? { code: office.code, id: office.id || office.code, name: office.name, address: office.address, isAPS: !! office.isAPS } : null;
		}

		if ( data && typeof data === 'object' && data.id ) {
			return { id: data.id, name: data.name || data.nameEn || String( data.id ), type: data.type || '', address: data.address || '' };
		}

		if ( typeof data === 'number' || ( typeof data === 'string' && /^\d+$/.test( data ) ) ) {
			return { id: Number( data ), name: 'Speedy ' + data, type: '' };
		}

		return null;
	}

	function MapDialog( props ) {
		var ref = useRef( null );

		useEffect( function () {
			var dialog = ref.current;

			if ( dialog && dialog.showModal && ! dialog.open ) {
				dialog.showModal();
			}

			function onMessage( event ) {
				if ( event.origin !== MAP_ORIGINS[ props.courier ] ) {
					return;
				}

				var item = itemFromMap( props.courier, event.data );

				if ( item ) {
					props.onPick( item );
				}
			}

			window.addEventListener( 'message', onMessage );

			return function () {
				window.removeEventListener( 'message', onMessage );
			};
		}, [] );

		return h( 'dialog', {
			className: 'nashenski-map',
			ref: ref,
			'aria-label': t.mapTitle,
			onClose: props.onClose,
			onCancel: props.onClose,
		},
			h( 'div', { className: 'nashenski-map__bar' },
				h( 'strong', null, t.mapTitle ),
				props.note ? h( 'span', { className: 'nashenski-map__note', role: 'alert' }, props.note ) : null,
				h( 'button', { type: 'button', className: 'nashenski-map__close', onClick: props.onClose }, t.close )
			),
			h( 'iframe', { className: 'nashenski-map__frame', src: props.url, title: t.mapTitle, allow: 'geolocation' } )
		);
	}

	/* ---------- office, locker or BOX NOW locker ---------- */

	function ListPicker( props ) {
		var courier = COURIERS[ props.courier ];
		var list = courier.lists[ props.type ];
		var data = props.data;
		var address = data.address || {};
		var connected = !! ( cfg.connected || {} )[ props.courier ];
		var mapOnly = ! connected && ! courier.publicData && ! courier.publicLists;
		var storeKey = props.courier + ':' + props.type + ':' + normalize( address.city );

		var _items = useState( [] ), items = _items[ 0 ], setItems = _items[ 1 ];
		var _status = useState( 'idle' ), status = _status[ 0 ], setStatus = _status[ 1 ];
		var _message = useState( '' ), message = _message[ 0 ], setMessage = _message[ 1 ];
		var _cityId = useState( '' ), cityId = _cityId[ 0 ], setCityId = _cityId[ 1 ];
		var _query = useState( '' ), query = _query[ 0 ], setQuery = _query[ 1 ];
		var _selected = useState( recall( storeKey ) ), selected = _selected[ 0 ], setSelected = _selected[ 1 ];
		var _editing = useState( false ), editing = _editing[ 0 ], setEditing = _editing[ 1 ];
		var _map = useState( false ), map = _map[ 0 ], setMap = _map[ 1 ];
		var _attempt = useState( 0 ), attempt = _attempt[ 0 ], setAttempt = _attempt[ 1 ];
		var _mapNote = useState( '' ), mapNote = _mapNote[ 0 ], setMapNote = _mapNote[ 1 ];

		useEffect( function () {
			setSelected( recall( storeKey ) );
		}, [ storeKey ] );

		useEffect( function () {
			if ( mapOnly || ( 'apm' !== props.type && ! address.city ) ) {
				setStatus( 'idle' );
				setItems( [] );
				return;
			}

			setStatus( 'loading' );
			ajax( list[ 0 ], { delivery_type: props.type, city: address.city || '', state: address.state || '', country: address.country || 'BG' } )
				.then( function ( response ) {
					var found = response[ list[ 1 ] ] || [];

					setItems( Array.isArray( found ) ? found : Object.values( found ) );
					setCityId( response.cityId || '' );
					setMessage( response.error || ( found.length ? '' : t.none ) );
					setStatus( 'ready' );
				} )
				.catch( function () {
					setStatus( 'error' );
				} );
		}, [ props.courier, props.type, address.city, address.state, address.country, attempt ] );

		// Keep the cookie in step with the choice, the name, phone, company and payment method.
		useEffect( function () {
			if ( ! selected ) {
				// Nothing chosen in this town (yet): forget an office chosen for another town or type.
				if ( readCookie( courier.cookie ) ) {
					commit( courier.cookie, Object.assign( 'boxnow' === props.courier ? { payment: data.payment || '' } : baseCookie( data, props.type ), courier.pick( null, props.type ) ), props.refresh );
				}

				return;
			}

			var value = 'boxnow' === props.courier
				? Object.assign( { payment: data.payment || '' }, courier.pick( selected, props.type ) )
				: Object.assign( baseCookie( data, props.type ), courier.pick( selected, props.type ) );


			value.selectedLabel = describe( selected );
			props.onChoice( describe( selected ) );
			commit( courier.cookie, value, props.refresh );
		}, [ selected, JSON.stringify( data ) ] );

		function choose( item ) {
			var keep = { id: item.id, code: item.code || '', name: nameOf( item ), address: addressOf( item ), type: item.type || '', isAPS: !! item.isAPS };

			remember( storeKey, keep );
			setSelected( keep );
			setEditing( false );
			setQuery( '' );
			setMap( false );
		}

		var mapButton = courier.map ? h( 'button', {
			type: 'button',
			className: 'nashenski-picker__map-button',
			onClick: function () {
				setMap( true );
			},
		}, t.map ) : null;

		var mapDialog = map ? h( MapDialog, {
			courier: props.courier,
			url: mapUrl( props.courier, props.type, data, cityId ),
			note: mapNote,
			onPick: function ( item ) {
				// Speedy marks parcel lockers APT: a locker option needs a locker, an office option an office.
				if ( 'speedy' === props.courier && item.type ) {
					var isLocker = 'APT' === String( item.type ).toUpperCase();

					if ( 'automat' === props.type && ! isLocker ) {
						setMapNote( t.notLocker );
						return;
					}

					if ( 'office' === props.type && isLocker ) {
						setMapNote( t.notOffice );
						return;
					}
				}

				setMapNote( '' );

				// Prefer the full item from the list when the map sent only its number.
				var full = null;

				items.forEach( function ( i ) {
					if ( itemKey( props.courier, i ) === itemKey( props.courier, item ) ) {
						full = i;
					}
				} );
				choose( full || item );
			},
			onClose: function () {
				setMapNote( '' );
				setMap( false );
			},
		} ) : null;

		if ( selected && ! editing ) {
			return h( 'div', { className: 'nashenski-picker' },
				h( 'div', { className: 'nashenski-picker__chosen' },
					h( 'span', { className: 'nashenski-picker__chosen-label' }, t[ props.type ] || t.office ),
					h( 'span', { className: 'nashenski-picker__chosen-name' }, selected.name ),
					selected.address ? h( 'span', { className: 'nashenski-picker__chosen-detail' }, selected.address ) : null,
					h( 'button', {
						type: 'button',
						className: 'nashenski-picker__change',
						onClick: function () {
							setEditing( true );
						},
					}, t.change )
				),
				mapDialog
			);
		}

		if ( mapOnly ) {
			return h( 'div', { className: 'nashenski-picker' },
				h( 'p', { className: 'nashenski-picker__note' }, t.mapOnly ),
				mapButton,
				mapDialog
			);
		}

		if ( 'apm' !== props.type && ! address.city ) {
			return h( 'div', { className: 'nashenski-picker' }, h( 'p', { className: 'nashenski-picker__note' }, t.enterCity ) );
		}

		if ( 'loading' === status ) {
			return h( 'div', { className: 'nashenski-picker', 'aria-busy': 'true' }, h( 'p', { className: 'nashenski-picker__note nashenski-picker__note--loading' }, t.loading ) );
		}

		if ( 'error' === status ) {
			return h( 'div', { className: 'nashenski-picker' },
				h( 'p', { className: 'nashenski-picker__note' }, t.error ),
				h( 'button', {
					type: 'button',
					className: 'nashenski-picker__change',
					onClick: function () {
						setAttempt( attempt + 1 );
					},
				}, t.retry )
			);
		}

		var options = items.filter( function ( item ) {
			return matches( describe( item ), query );
		} ).slice( 0, 60 ).map( function ( item ) {
			return { key: itemKey( props.courier, item ), title: nameOf( item ), detail: addressOf( item ), item: item };
		} );

		return h( 'div', { className: 'nashenski-picker' },
			message && ! items.length ? h( 'p', { className: 'nashenski-picker__note' }, message ) : null,
			items.length ? h( Combobox, {
				label: t[ props.type ] || t.office,
				placeholder: t.searchOffice,
				query: query,
				options: options,
				empty: t.noMatch,
				onQuery: setQuery,
				onChoose: function ( option ) {
					choose( option.item );
				},
			} ) : null,
			mapButton,
			mapDialog
		);
	}

	/* ---------- delivery to an address ---------- */

	function AddressPicker( props ) {
		var courier = COURIERS[ props.courier ];
		var data = props.data;
		var address = data.address || {};
		var connected = !! ( cfg.connected || {} )[ props.courier ];
		var freeText = ! connected && ! courier.publicData;
		var storeKey = props.courier + ':address:' + normalize( address.city );
		var saved = recall( storeKey ) || {};

		var _streets = useState( [] ), streets = _streets[ 0 ], setStreets = _streets[ 1 ];
		var _status = useState( 'idle' ), status = _status[ 0 ], setStatus = _status[ 1 ];
		var _query = useState( saved.selectedAddress ? saved.selectedAddress.label : '' ), query = _query[ 0 ], setQuery = _query[ 1 ];
		var _form = useState( saved ), form = _form[ 0 ], setForm = _form[ 1 ];
		var mode = courier.streets ? courier.streets[ 1 ] : 'list';

		useEffect( function () {
			var again = recall( storeKey ) || {};

			setForm( again );
			setQuery( again.selectedAddress ? again.selectedAddress.label : '' );
		}, [ storeKey ] );

		// Streets of the town: the whole list at once, or a search as the customer types.
		useEffect( function () {
			if ( freeText || ! address.city || ! courier.streets ) {
				return;
			}

			if ( 'search' === mode ) {
				if ( normalize( query ).length < 2 || ( form.selectedAddress && form.selectedAddress.label === query ) ) {
					return;
				}

				var timer = window.setTimeout( function () {
					setStatus( 'loading' );
					ajax( courier.streets[ 0 ], { query: query, city: address.city, state: address.state || '', country: address.country || 'BG' } )
						.then( function ( response ) {
							setStreets( response.streets || [] );
							setStatus( response.cities && ! response.streets ? 'invalid' : 'ready' );
						} )
						.catch( function () {
							setStatus( 'error' );
						} );
				}, 300 );

				return function () {
					window.clearTimeout( timer );
				};
			}

			setStatus( 'loading' );
			ajax( courier.streets[ 0 ], { city: address.city, state: address.state || '', country: address.country || 'BG' } )
				.then( function ( response ) {
					setStreets( response.streets || [] );
					setStatus( 'invalid-city' === response.status ? 'invalid' : 'ready' );
				} )
				.catch( function () {
					setStatus( 'error' );
				} );
		}, [ props.courier, address.city, address.state, address.country, 'search' === mode ? query : '' ] );

		function update( changes ) {
			var next = Object.assign( {}, form, changes );

			remember( storeKey, next );
			setForm( next );
		}

		var isQuarter = form.selectedAddress && 'quarters' === form.selectedAddress.type;

		useEffect( function () {
			if ( ! form.selectedAddress ) {
				if ( readCookie( courier.cookie ) ) {
					commit( courier.cookie, Object.assign( baseCookie( data, 'address' ), { selectedAddress: null } ), props.refresh );
				}

				return;
			}

			var value = Object.assign( baseCookie( data, 'address' ), {
				selectedAddress: form.selectedAddress,
				streetNumber: form.streetNumber || '',
				other: form.other || '',
				otherField: address.address_2 || '',
			} );

			if ( courier.quarters ) {
				value.blockNumber = form.blockNumber || '';
				value.entranceNumber = form.entranceNumber || '';
				value.floorNumber = form.floorNumber || '';
				value.apartmentNumber = form.apartmentNumber || '';
			}

			var text = form.selectedAddress.label + ( form.streetNumber ? ' ' + form.streetNumber : '' );

			props.onChoice( isQuarter && form.blockNumber ? form.selectedAddress.label + ', ' + form.blockNumber : text );
			commit( courier.cookie, value, props.refresh );
		}, [ JSON.stringify( form ), JSON.stringify( data ) ] );

		if ( ! address.city ) {
			return h( 'div', { className: 'nashenski-picker' }, h( 'p', { className: 'nashenski-picker__note' }, t.enterCity ) );
		}

		function field( name, label, wide ) {
			var id = 'nashenski-' + props.courier + '-' + name;

			return h( 'p', { className: 'nashenski-picker__field' + ( wide ? ' nashenski-picker__field--wide' : '' ), key: name },
				h( 'label', { className: 'nashenski-picker__label', htmlFor: id }, label ),
				h( 'input', {
					id: id,
					type: 'text',
					className: 'nashenski-picker__input',
					value: form[ name ] || '',
					onChange: function ( e ) {
						var changes = {};
						changes[ name ] = e.target.value;
						update( changes );
					},
				} )
			);
		}

		// No street list for this courier without a login: street and number typed in.
		if ( freeText || 'invalid' === status || 'error' === status ) {
			var typed = form.selectedAddress ? form.selectedAddress.label : '';

			return h( 'div', { className: 'nashenski-picker nashenski-picker--address' },
				h( 'p', { className: 'nashenski-picker__field nashenski-picker__field--wide' },
					h( 'label', { className: 'nashenski-picker__label', htmlFor: 'nashenski-' + props.courier + '-street' }, t.street ),
					h( 'input', {
						id: 'nashenski-' + props.courier + '-street',
						type: 'text',
						className: 'nashenski-picker__input',
						value: typed,
						onChange: function ( e ) {
							var label = e.target.value;

							update( { selectedAddress: label.trim() ? { id: label, label: label, orig_key: '', type: 'streets' } : null } );
						},
					} )
				),
				h( 'div', { className: 'nashenski-picker__row' },
					field( 'streetNumber', t.streetNumber ),
					courier.quarters ? [ field( 'blockNumber', t.block ), field( 'entranceNumber', t.entrance ), field( 'floorNumber', t.floor ), field( 'apartmentNumber', t.apartment ) ] : null
				),
				field( 'other', t.other, true )
			);
		}

		var options = [];

		if ( query && ( ! form.selectedAddress || form.selectedAddress.label !== query ) ) {
			options = ( 'search' === mode ? streets : streets.filter( function ( s ) {
				return matches( s.label, query );
			} ) ).slice( 0, 40 ).map( function ( s, index ) {
				return { key: ( s.orig_key || s.id || s.label ) + ':' + index, title: s.label, detail: '', street: s };
			} );
		}

		return h( 'div', { className: 'nashenski-picker nashenski-picker--address' },
			h( Combobox, {
				label: t.street,
				placeholder: t.streetSearch,
				query: query,
				options: options,
				empty: 'loading' === status ? t.loading : ( normalize( query ).length > 1 ? t.noMatch : '' ),
				onQuery: setQuery,
				onChoose: function ( option ) {
					setQuery( option.street.label );
					update( { selectedAddress: option.street } );
				},
			} ),
			form.selectedAddress ? h( 'div', { className: 'nashenski-picker__row' },
				isQuarter && courier.quarters
					? [ field( 'blockNumber', t.block ), field( 'entranceNumber', t.entrance ), field( 'floorNumber', t.floor ), field( 'apartmentNumber', t.apartment ) ]
					: field( 'streetNumber', t.streetNumber )
			) : null,
			form.selectedAddress ? field( 'other', t.other, true ) : null
		);
	}

	/* ---------- the town, when the checkout hides the town or region field ---------- */

	/*
	 * The address with the town chosen in the picker, while it is still the town of the checkout:
	 * the town field is hidden or empty, or says the same (also typed in Latin, "Varna").
	 */
	function withTown( address ) {
		var town = cfg.townPicker ? recall( 'town' ) : null;
		var typed = normalize( address.city );

		if ( ! town || ( typed && typed !== normalize( town.name ) && typed !== normalize( town.typed ) ) ) {
			return address;
		}

		return Object.assign( {}, address, { city: town.name, state: town.state, postcode: address.postcode || town.postcode } );
	}

	function regionOf( town ) {
		return town.region ? String( t.region || '%s' ).replace( '%s', town.region ) : '';
	}

	/* props: address (from the checkout), onTown( town ). */
	function TownPicker( props ) {
		var address = props.address;
		var _query = useState( address.city || '' ), query = _query[ 0 ], setQuery = _query[ 1 ];
		var _towns = useState( [] ), towns = _towns[ 0 ], setTowns = _towns[ 1 ];
		var _status = useState( 'idle' ), status = _status[ 0 ], setStatus = _status[ 1 ];
		var _editing = useState( false ), editing = _editing[ 0 ], setEditing = _editing[ 1 ];
		var current = withTown( address );
		var known = current.city && current.state;

		function choose( town ) {
			remember( 'town', Object.assign( {}, town, { typed: address.city || '' } ) );
			setEditing( false );
			props.onTown( town );
		}

		// A town remembered from an earlier visit goes back into an empty checkout address.
		useEffect( function () {
			var town = recall( 'town' );

			if ( town && ! address.city && current.city ) {
				props.onTown( town );
			}
		}, [ address.city ] );

		useEffect( function () {
			if ( ( known && ! editing ) || normalize( query ).length < 2 ) {
				return;
			}

			var timer = window.setTimeout( function () {
				setStatus( 'loading' );
				ajax( 'nashenski_search_towns', { query: query } )
					.then( function ( response ) {
						var found = response.towns || [];
						var exact = found.filter( function ( town ) {
							return 0 === town.rank;
						} );

						setTowns( found );
						setStatus( 'ready' );

						// The checkout's town field says which town: take it when only one place has that name.
						if ( ! editing && address.city && 1 === exact.length && normalize( query ) === normalize( address.city ) ) {
							choose( exact[ 0 ] );
						}
					} )
					.catch( function () {
						setStatus( 'error' );
					} );
			}, 250 );

			return function () {
				window.clearTimeout( timer );
			};
		}, [ query, known, editing ] );

		if ( known && ! editing ) {
			return h( 'div', { className: 'nashenski-picker nashenski-picker--town' },
				h( 'div', { className: 'nashenski-picker__chosen' },
					h( 'span', { className: 'nashenski-picker__chosen-label' }, t.town ),
					h( 'span', { className: 'nashenski-picker__chosen-name' }, current.city ),
					h( 'span', { className: 'nashenski-picker__chosen-detail' }, regionOf( recall( 'town' ) || {} ) ),
					h( 'button', {
						type: 'button',
						className: 'nashenski-picker__change',
						onClick: function () {
							setQuery( '' );
							setEditing( true );
						},
					}, t.change )
				)
			);
		}

		return h( 'div', { className: 'nashenski-picker nashenski-picker--town' },
			h( Combobox, {
				label: t.town,
				placeholder: t.townSearch,
				query: query,
				options: towns.map( function ( town ) {
					return { key: town.name + town.state + town.postcode, title: town.name, detail: regionOf( town ) + ', ' + town.postcode, town: town };
				} ),
				empty: 'loading' === status ? t.loading : ( 'error' === status ? t.error : ( normalize( query ).length > 1 ? t.noMatch : '' ) ),
				onQuery: setQuery,
				onChoose: function ( option ) {
					choose( option.town );
				},
			} )
		);
	}

	/**
	 * The picker for one shipping option. props: courier, type, data, refresh(), onChoice( text ),
	 * and onTown( town ) for checkouts that keep the picker's town in their own address.
	 */
	function Picker( props ) {
		var courier = COURIERS[ props.courier ];
		var _version = useState( 0 ), setVersion = _version[ 1 ];

		if ( ! courier ) {
			return null;
		}

		// Every courier needs the town and region; ask for them when the checkout does not.
		if ( cfg.townPicker && 'apm' !== props.type && ! props.hasTown ) {
			var address = props.data.address || {};
			var current = withTown( address );

			return h( 'div', { className: 'nashenski-picker-group' },
				h( TownPicker, {
					address: address,
					onTown: function ( town ) {
						setVersion( function ( v ) {
							return v + 1;
						} );

						if ( props.onTown ) {
							props.onTown( town );
						}
					},
				} ),
				current.city && current.state ? h( Picker, Object.assign( {}, props, {
					hasTown: true,
					data: Object.assign( {}, props.data, { address: current } ),
				} ) ) : null
			);
		}

		if ( 'address' === props.type ) {
			return courier.streets || ! ( cfg.connected || {} )[ props.courier ] ? h( AddressPicker, props ) : null;
		}

		return courier.lists[ props.type ] ? h( ListPicker, props ) : null;
	}

	/* ---------- shipping option labels: logo, delivery type, "complete the fields" ---------- */

	var RATE = /^nashenski_([a-z]+):\d+$/;

	function labelFor( input ) {
		if ( input.id ) {
			var byFor = document.querySelector( 'label[for="' + ( window.CSS && CSS.escape ? CSS.escape( input.id ) : input.id ) + '"]' );

			if ( byFor ) {
				return byFor;
			}
		}

		return input.closest( 'label' ) || ( input.closest( 'li' ) && input.closest( 'li' ).querySelector( 'label' ) );
	}

	// The element that holds the method name: builder and block checkouts wrap it, classic does not.
	function nameElement( label ) {
		return label.querySelector( '.wc-block-components-radio-control__label, .brx-shipping-options__label' ) || label;
	}

	// The builder or block element that shows the price ("Free" while nothing is chosen).
	function priceElement( label ) {
		return label.querySelector( '.brx-shipping-options__secondary-label' );
	}

	/* An amount in the store's money format, like wc_price() without the markup; symbol: another currency's. */
	function money( amount, symbol ) {
		var c = cfg.currency || {};
		var parts = Number( amount ).toFixed( c.decimals || 0 ).split( '.' );

		parts[ 0 ] = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, c.thousand || '' );

		return ( c.format || '%1$s%2$s' ).replace( '%1$s', ( undefined === symbol ? c.symbol : symbol ) || '' ).replace( '%2$s', parts.join( c.decimal || '.' ) );
	}

	/* The offer closest to free shipping (least left, then the lowest total), or null. */
	function lowestOffer( offers ) {
		return offers.reduce( function ( best, offer ) {
			return offer.over > 0 && ( ! best || offer.left < best.left || ( offer.left === best.left && offer.over < best.over ) ) ? offer : best;
		}, null );
	}

	/* Text of an element, set only when it changed: the checkouts watch the page for changes. */
	function setText( node, text ) {
		if ( node.textContent !== text ) {
			node.textContent = text;
		}
	}

	var progress = null;

	/* "Add 23,40 € more for free shipping." with a bar, above the shipping options. */
	function showProgress( list, offer ) {
		if ( ! cfg.freeBar || ! list || ! offer ) {
			if ( progress && progress.parentNode ) {
				progress.remove();
			}

			return;
		}

		if ( ! progress ) {
			progress = document.createElement( 'div' );
			progress.className = 'nashenski-free-progress';
			progress.appendChild( document.createElement( 'p' ) ).id = 'nashenski-free-progress-text';
			progress.appendChild( document.createElement( 'progress' ) ).setAttribute( 'aria-labelledby', 'nashenski-free-progress-text' );
		}

		var bar = progress.lastChild;

		setText( progress.firstChild, offer.left > 0 ? t.freeLeft.replace( '%s', money( offer.left ) ) : t.freeDone );
		bar.max = offer.over;
		bar.value = Math.max( 0, offer.over - offer.left );

		if ( list.previousElementSibling !== progress ) {
			list.before( progress );
		}
	}

	/* Free shipping total and what is left for it, from the classic holder's data. */
	function offerOf( holder ) {
		return holder ? { over: parseFloat( holder.getAttribute( 'data-free-over' ) ) || 0, left: parseFloat( holder.getAttribute( 'data-free-left' ) ) || 0 } : null;
	}

	/**
	 * Decorate every Nashenski Commerce shipping option on the page. pending: rate IDs still waiting
	 * for a choice (the block checkout passes them; elsewhere they come from data-pending). offers:
	 * rate ID => { over, left } of the free shipping totals (block checkout; elsewhere from the holders).
	 */
	function decorateRates( pending, offers ) {
		var inputs = document.querySelectorAll( 'input[name^="shipping_method"], input.wc-block-components-radio-control__input' );
		var logos = cfg.logos || {};
		var rates = cfg.rates || {};
		var shown = [];
		var list = null;
		var chosen = null;

		Array.prototype.forEach.call( inputs, function ( input ) {
			var match = RATE.exec( input.value || '' );

			if ( ! match ) {
				return;
			}

			var label = labelFor( input );

			if ( ! label ) {
				return;
			}

			var holder = document.querySelector( '.nashenski-additional-fields[data-rate="' + input.value + '"]' );
			var type = ( holder && holder.getAttribute( 'data-type' ) ) || ( rates[ input.value ] || {} ).type;
			var name = nameElement( label );

			if ( logos[ match[ 1 ] ] && ! label.querySelector( '.nashenski-courier-logo' ) ) {
				var img = document.createElement( 'img' );
				img.className = 'nashenski-courier-logo nashenski-courier-logo--' + match[ 1 ];
				img.src = logos[ match[ 1 ] ];
				img.alt = '';
				img.setAttribute( 'aria-hidden', 'true' );
				img.decoding = 'async';
				name.insertBefore( img, name.firstChild );
			}

			if ( cfg.showType && type && ( cfg.types || {} )[ type ] && ! label.querySelector( '.nashenski-rate-type' ) ) {
				var sub = document.createElement( 'span' );
				sub.className = 'nashenski-rate-type';
				sub.textContent = cfg.types[ type ];
				// The delivery type goes before the free shipping hint.
				name.insertBefore( sub, name.querySelector( '.nashenski-rate-free-over' ) );
			}

			var offer = offers ? offers[ input.value ] : offerOf( holder );
			var hint = name.querySelector( '.nashenski-rate-free-over' );

			if ( cfg.freeOver && offer && offer.over > 0 && offer.left > 0 ) {
				if ( ! hint ) {
					hint = name.appendChild( document.createElement( 'span' ) );
					hint.className = 'nashenski-rate-free-over';
				}

				setText( hint, t.freeOver.replace( '%s', money( offer.over ) ) );
			} else if ( hint ) {
				hint.remove();
			}

			if ( offer ) {
				shown.push( offer );
				list = list || input.closest( '#shipping_method, .brx-shipping-options__control, .wc-block-components-shipping-rates-control' );
			}

			label.classList.add( 'nashenski-rate' );

			var isPending = pending ? pending.indexOf( input.value ) !== -1 : !! ( holder && '1' === holder.getAttribute( 'data-pending' ) );
			var price = priceElement( label );

			if ( price ) {
				if ( isPending && ! price.classList.contains( 'nashenski-rate-pending' ) ) {
					price.textContent = cfg.pending;
					price.classList.add( 'nashenski-rate-pending' );
				}
			}

			if ( input.checked || 'hidden' === input.type ) {
				chosen = { list: input.closest( '#shipping_method, .brx-shipping-options__control' ), label: label };
			}
		} );

		showProgress( list, lowestOffer( shown ) );
		showNote( chosen );
	}

	var note = null;

	/*
	 * The long text of the chosen option (what it costs at the courier, or what to choose first),
	 * once under the options instead of in every card. The block checkout says it in its summary.
	 * One live region right after the list (not an option of it): created once, put back when the
	 * checkout redraws the list, and only its text changes, so screen readers announce it.
	 */
	function showNote( chosen ) {
		var source = chosen && chosen.list ? chosen.label.querySelector( '[data-nashenski-note]' ) : null;

		if ( ! note ) {
			note = document.createElement( 'p' );
			note.className = 'nashenski-rates-note';
			note.setAttribute( 'aria-live', 'polite' );
		}

		if ( chosen && chosen.list && chosen.list.nextElementSibling !== note ) {
			chosen.list.after( note );
		}

		setText( note, source ? source.getAttribute( 'data-nashenski-note' ) : '' );
	}

	window.nashenskiPickers = Object.assign( cfg, {
		Picker: Picker,
		COURIERS: COURIERS,
		decorateRates: decorateRates,
		money: money,
		lowestOffer: lowestOffer,
		courierOf: function ( rateId ) {
			var match = RATE.exec( rateId || '' );

			return match ? match[ 1 ] : '';
		},
	} );
} )();
