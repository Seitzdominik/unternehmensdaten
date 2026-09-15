/**
 * Backend-Skript.
 *
 * Reines Vanilla-JavaScript ohne jQuery, wird ausschliesslich auf den eigenen
 * Seiten geladen. Drei Aufgaben: Registerkarten, Kopieren in die Zwischenablage
 * und das Ein- und Ausblenden abhaengiger Fragen.
 */
( function () {
	'use strict';

	var l10n = window.undtL10n || { copied: 'Kopiert', failed: 'Kopieren fehlgeschlagen' };
	var live = null;

	/**
	 * Meldet eine Statusaenderung an Screenreader.
	 *
	 * @param {string} message Text.
	 */
	function announce( message ) {
		if ( ! live ) {
			live = document.createElement( 'div' );
			live.className = 'screen-reader-text';
			live.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( live );
		}

		live.textContent = message;
	}

	/* --------------------------------------------------------- Registerkarten */

	function initTabs() {
		Array.prototype.slice
			.call( document.querySelectorAll( '.undt-tabs' ) )
			.forEach( initTabGroup );
	}

	function initTabGroup( wrapper ) {
		var tabs = Array.prototype.slice.call( wrapper.querySelectorAll( '[role="tab"]' ) );

		if ( ! tabs.length ) {
			return;
		}

		function activate( tab, focus ) {
			tabs.forEach( function ( item ) {
				var panel    = document.getElementById( item.getAttribute( 'aria-controls' ) );
				var selected = item === tab;

				item.classList.toggle( 'nav-tab-active', selected );
				item.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
				item.setAttribute( 'tabindex', selected ? '0' : '-1' );

				if ( panel ) {
					panel.hidden = ! selected;
				}
			} );

			if ( focus ) {
				tab.focus();
			}

			var slug = tab.id.replace( 'undt-tab-', '' );

			if ( window.history && window.history.replaceState ) {
				window.history.replaceState( null, '', '#' + slug );
			}
		}

		tabs.forEach( function ( tab, index ) {
			tab.addEventListener( 'click', function () {
				activate( tab, false );
			} );

			tab.addEventListener( 'keydown', function ( event ) {
				var next = null;

				if ( 'ArrowRight' === event.key ) {
					next = tabs[ ( index + 1 ) % tabs.length ];
				} else if ( 'ArrowLeft' === event.key ) {
					next = tabs[ ( index - 1 + tabs.length ) % tabs.length ];
				} else if ( 'Home' === event.key ) {
					next = tabs[ 0 ];
				} else if ( 'End' === event.key ) {
					next = tabs[ tabs.length - 1 ];
				}

				if ( next ) {
					event.preventDefault();
					activate( next, true );
				}
			} );
		} );

		// Vertiefter Link, etwa aus der Prüfseite heraus.
		var hash = window.location.hash.replace( '#', '' );

		if ( hash ) {
			var target = document.getElementById( 'undt-tab-' + hash );

			if ( target ) {
				activate( target, false );
			}
		}

		/*
		 * Wird ein ungueltiges Pflichtfeld in einem verborgenen Panel abgewiesen,
		 * kann der Browser es nicht anzeigen. Deshalb vor der Validierung auf das
		 * betroffene Panel wechseln.
		 */
		var form = wrapper.closest( 'form' );

		if ( form ) {
			form.addEventListener(
				'invalid',
				function ( event ) {
					var panel = event.target.closest( '.undt-panel' );

					if ( panel && panel.hidden ) {
						var tab = document.getElementById( panel.getAttribute( 'aria-labelledby' ) );

						if ( tab ) {
							activate( tab, false );
						}
					}
				},
				true
			);
		}
	}

	/* ------------------------------------------------------------- Kopieren */

	function copyFallback( text ) {
		var field = document.createElement( 'textarea' );

		field.value = text;
		field.setAttribute( 'readonly', '' );
		field.style.position = 'fixed';
		field.style.top = '-1000px';
		field.style.opacity = '0';

		document.body.appendChild( field );
		field.select();

		var ok = false;

		try {
			ok = document.execCommand( 'copy' );
		} catch ( error ) {
			ok = false;
		}

		document.body.removeChild( field );

		return ok;
	}

	function flash( button, message, ok ) {
		button.classList.add( ok ? 'undt-copy--ok' : 'undt-copy--fail' );
		announce( message );

		window.setTimeout( function () {
			button.classList.remove( 'undt-copy--ok', 'undt-copy--fail' );
		}, 1200 );
	}

	function initCopy() {
		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest ? event.target.closest( '.undt-copy' ) : null;

			if ( ! button ) {
				return;
			}

			event.preventDefault();

			var text = button.getAttribute( 'data-undt-copy' ) || '';

			// Die Clipboard-API steht nur in sicheren Kontexten bereit. Ein Backend
			// ueber reines HTTP faellt deshalb auf execCommand zurueck.
			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( text ).then(
					function () {
						flash( button, l10n.copied, true );
					},
					function () {
						var ok = copyFallback( text );
						flash( button, ok ? l10n.copied : l10n.failed, ok );
					}
				);

				return;
			}

			var ok = copyFallback( text );
			flash( button, ok ? l10n.copied : l10n.failed, ok );
		} );
	}

	/* --------------------------------------------------- Abhaengige Fragen */

	function fieldValue( name ) {
		var element = document.getElementById( 'undt-' + name );

		if ( ! element ) {
			return null;
		}

		if ( 'checkbox' === element.type ) {
			return element.checked ? 1 : 0;
		}

		return element.value;
	}

	function matches( conditions ) {
		return Object.keys( conditions ).every( function ( key ) {
			var expected = conditions[ key ];
			var actual   = fieldValue( key );

			if ( null === actual ) {
				return false;
			}

			if ( Array.isArray( expected ) ) {
				return expected.map( String ).indexOf( String( actual ) ) !== -1;
			}

			return String( expected ) === String( actual );
		} );
	}

	function initConditions() {
		var rows = Array.prototype.slice.call( document.querySelectorAll( '[data-undt-when]' ) );

		if ( ! rows.length ) {
			return;
		}

		var parsed = rows.map( function ( row ) {
			var conditions = {};

			try {
				conditions = JSON.parse( row.getAttribute( 'data-undt-when' ) ) || {};
			} catch ( error ) {
				conditions = {};
			}

			return { row: row, conditions: conditions };
		} );

		function refresh() {
			parsed.forEach( function ( item ) {
				item.row.hidden = ! matches( item.conditions );
			} );
		}

		document.addEventListener( 'change', function ( event ) {
			if ( event.target.closest( '.undt-form' ) ) {
				refresh();
			}
		} );

		refresh();
	}

	/* ---------------------------------------------------------- Filterfeld */

	function initFilter() {
		var input = document.getElementById( 'undt-filter' );

		if ( ! input ) {
			return;
		}

		var rows   = Array.prototype.slice.call( document.querySelectorAll( '.undt-searchable' ) );
		var empty  = document.querySelector( '.undt-no-results' );
		var panels = Array.prototype.slice.call( document.querySelectorAll( '.undt-panel' ) );
		var nav    = document.querySelector( '.undt-tabs' );
		var wrap   = document.querySelector( '.undt-wrap' );
		var timer  = null;

		function apply() {
			var term    = input.value.trim().toLowerCase();
			var visible = 0;

			/*
			 * Waehrend einer Suche treten die Reiter zurueck und die Treffer stehen
			 * untereinander. Sonst faende man nur, was zufaellig im offenen Reiter
			 * liegt, und die Suche waere eine Falle.
			 */
			var searching = '' !== term;

			if ( wrap ) {
				wrap.classList.toggle( 'is-searching', searching );
			}

			if ( nav ) {
				nav.hidden = searching;
			}

			// Alles aufdecken, damit die Zeilenpruefung unten greifen kann.
			panels.forEach( function ( panel ) {
				panel.hidden = false;
			} );

			rows.forEach( function ( row ) {
				var hit = '' === term || ( row.getAttribute( 'data-undt-text' ) || '' ).indexOf( term ) !== -1;

				row.hidden = ! hit;

				if ( hit ) {
					visible++;
				}
			} );

			// Überschrift und Tabelle einer Gruppe ausblenden, wenn sie leer ist.
			Array.prototype.slice.call( document.querySelectorAll( '.undt-table' ) ).forEach( function ( table ) {
				var any = Array.prototype.slice
					.call( table.querySelectorAll( '.undt-searchable' ) )
					.some( function ( row ) {
						return ! row.hidden;
					} );

				table.hidden = ! any;

				var heading = table.previousElementSibling;

				while ( heading && ( 'P' === heading.tagName ) ) {
					heading.hidden = ! any;
					heading = heading.previousElementSibling;
				}

				if ( heading && /^H[234]$/.test( heading.tagName ) ) {
					heading.hidden = ! any;
				}
			} );

			panels.forEach( function ( panel ) {
				if ( searching ) {
					// Ein Panel ohne Treffer traegt nichts bei und verschwindet.
					panel.hidden = ! Array.prototype.slice
						.call( panel.querySelectorAll( '.undt-searchable' ) )
						.some( function ( row ) {
							return ! row.hidden;
						} );

					return;
				}

				var tab = document.getElementById( panel.getAttribute( 'aria-labelledby' ) );

				panel.hidden = ! tab || 'true' !== tab.getAttribute( 'aria-selected' );
			} );

			if ( empty ) {
				empty.hidden = visible > 0;
			}
		}

		input.addEventListener( 'input', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( apply, 120 );
		} );
	}

	/* -------------------------------------------------- Wiederholungsfelder */

	var INDEX_TOKEN = '__UNDT_INDEX__';

	function repeaterRows( repeater ) {
		return repeater.querySelector( '.undt-repeater__rows' );
	}

	function repeaterSync( repeater ) {
		var rows  = repeaterRows( repeater );
		var empty = repeater.querySelector( '.undt-repeater__empty' );

		if ( empty ) {
			empty.hidden = rows.children.length > 0;
		}

		// Die äußeren Pfeile führen nirgendwo hin und werden deshalb gesperrt.
		Array.prototype.slice.call( rows.children ).forEach( function ( row, index, all ) {
			var up   = row.querySelector( '.undt-repeater__move[data-dir="up"]' );
			var down = row.querySelector( '.undt-repeater__move[data-dir="down"]' );

			if ( up ) {
				up.disabled = 0 === index;
			}

			if ( down ) {
				down.disabled = index === all.length - 1;
			}
		} );
	}

	function repeaterAdd( repeater ) {
		var template = repeater.querySelector( '.undt-repeater__template' );

		if ( ! template ) {
			return;
		}

		var next = parseInt( repeater.getAttribute( 'data-undt-next' ) || '0', 10 );

		// Der Platzhalter steckt in den Formularnamen und am Ende der IDs.
		var markup = template.innerHTML.split( INDEX_TOKEN ).join( String( next ) );

		repeater.setAttribute( 'data-undt-next', String( next + 1 ) );

		var holder = document.createElement( 'div' );
		holder.innerHTML = markup;

		var row = holder.firstElementChild;

		if ( ! row ) {
			return;
		}

		repeaterRows( repeater ).appendChild( row );
		repeaterSync( repeater );
		announce( l10n.rowAdded );

		var first = row.querySelector( 'input:not([type="hidden"]), select, textarea' );

		if ( first ) {
			first.focus();
		}
	}

	function repeaterMove( row, direction ) {
		var sibling = 'up' === direction ? row.previousElementSibling : row.nextElementSibling;

		if ( ! sibling ) {
			return;
		}

		if ( 'up' === direction ) {
			row.parentNode.insertBefore( row, sibling );
		} else {
			row.parentNode.insertBefore( sibling, row );
		}

		announce( l10n.rowMoved );
	}

	function initRepeaters() {
		var repeaters = Array.prototype.slice.call( document.querySelectorAll( '[data-undt-repeater]' ) );

		if ( ! repeaters.length ) {
			return;
		}

		repeaters.forEach( repeaterSync );

		document.addEventListener( 'click', function ( event ) {
			if ( ! event.target.closest ) {
				return;
			}

			var add = event.target.closest( '.undt-repeater__add' );

			if ( add ) {
				event.preventDefault();
				repeaterAdd( add.closest( '[data-undt-repeater]' ) );
				return;
			}

			var move = event.target.closest( '.undt-repeater__move' );

			if ( move ) {
				event.preventDefault();

				var moveRow = move.closest( '.undt-repeater__row' );
				var dir     = move.getAttribute( 'data-dir' );

				repeaterMove( moveRow, dir );
				repeaterSync( move.closest( '[data-undt-repeater]' ) );

				// Nach dem Verschieben wandert der Fokus mit der Zeile.
				var again = moveRow.querySelector( '.undt-repeater__move[data-dir="' + dir + '"]' );

				if ( again && ! again.disabled ) {
					again.focus();
				}

				return;
			}

			var remove = event.target.closest( '.undt-repeater__remove' );

			if ( remove ) {
				event.preventDefault();

				if ( ! window.confirm( l10n.confirmRow ) ) {
					return;
				}

				var repeater  = remove.closest( '[data-undt-repeater]' );
				var removeRow = remove.closest( '.undt-repeater__row' );
				var addButton = repeater.querySelector( '.undt-repeater__add' );

				removeRow.parentNode.removeChild( removeRow );
				repeaterSync( repeater );
				announce( l10n.rowRemoved );

				if ( addButton ) {
					addButton.focus();
				}
			}
		} );
	}

	/* ------------------------------------------------------------- Medien */

	function initMedia() {
		var fields = document.querySelectorAll( '[data-undt-media]' );

		if ( ! fields.length ) {
			return;
		}

		var frame = null;

		document.addEventListener( 'click', function ( event ) {
			if ( ! event.target.closest ) {
				return;
			}

			var clear = event.target.closest( '.undt-media__clear' );

			if ( clear ) {
				event.preventDefault();

				var clearField = clear.closest( '[data-undt-media]' );

				clearField.querySelector( '.undt-media__value' ).value = '0';
				clearField.querySelector( '.undt-media__preview' ).textContent = '';
				clear.hidden = true;

				return;
			}

			var select = event.target.closest( '.undt-media__select' );

			if ( ! select ) {
				return;
			}

			event.preventDefault();

			if ( ! window.wp || ! window.wp.media ) {
				return;
			}

			var field = select.closest( '[data-undt-media]' );

			frame = window.wp.media( {
				title: l10n.mediaTitle,
				button: { text: l10n.mediaButton },
				library: { type: 'image' },
				multiple: false
			} );

			frame.on( 'select', function () {
				var item = frame.state().get( 'selection' ).first().toJSON();
				var url  = item.url;

				if ( item.sizes && item.sizes.thumbnail ) {
					url = item.sizes.thumbnail.url;
				}

				field.querySelector( '.undt-media__value' ).value = item.id;

				/*
				 * Das Vorschaubild entsteht als Element, nicht als HTML-Text. Die
				 * Adresse landet so in einer Eigenschaft und kann kein Attribut
				 * aufbrechen, gleich woher sie stammt.
				 */
				var preview = field.querySelector( '.undt-media__preview' );
				var image   = document.createElement( 'img' );

				image.src = url;
				image.alt = '';

				preview.textContent = '';
				preview.appendChild( image );

				var clearButton = field.querySelector( '.undt-media__clear' );

				if ( clearButton ) {
					clearButton.hidden = false;
				}
			} );

			frame.open();
		} );
	}

	/* ------------------------------------------------------ Öffnungszeiten */

	function initHours() {
		var rows = Array.prototype.slice.call( document.querySelectorAll( '[data-undt-day]' ) );

		if ( ! rows.length ) {
			return;
		}

		function sync( row ) {
			var toggle = row.querySelector( '[data-undt-closed]' );

			// Die Uhrzeiten bleiben bedienbar und behalten ihren Wert. Wer den
			// Schalter versehentlich setzt, verliert die Eingaben nicht.
			row.classList.toggle( 'undt-hours-edit__row--closed', !! ( toggle && toggle.checked ) );
		}

		rows.forEach( sync );

		document.addEventListener( 'change', function ( event ) {
			if ( event.target.matches && event.target.matches( '[data-undt-closed]' ) ) {
				sync( event.target.closest( '[data-undt-day]' ) );
			}
		} );
	}

	/* ---------------------------------------------------------------- Hinweis */

	/*
	 * Die Infobox erscheint per CSS beim Ueberfahren und beim Tastaturfokus. Das
	 * Skript ergaenzt nur das Feststellen per Klick, damit sich der Text
	 * markieren laesst, ohne dass er beim Wegziehen der Maus verschwindet.
	 */
	function initHelp() {
		function closeAll( except ) {
			Array.prototype.slice
				.call( document.querySelectorAll( '[data-undt-help].is-open' ) )
				.forEach( function ( wrap ) {
					if ( wrap !== except ) {
						wrap.classList.remove( 'is-open' );
					}
				} );
		}

		document.addEventListener( 'click', function ( event ) {
			if ( ! event.target.closest ) {
				return;
			}

			var toggle = event.target.closest( '.undt-help-toggle' );

			if ( toggle ) {
				event.preventDefault();

				var wrap = toggle.closest( '[data-undt-help]' );

				closeAll( wrap );
				wrap.classList.toggle( 'is-open' );

				return;
			}

			// Klick daneben schliesst die festgestellte Box wieder.
			if ( ! event.target.closest( '.undt-help' ) ) {
				closeAll( null );
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				closeAll( null );
			}
		} );
	}

	function init() {
		initTabs();
		initCopy();
		initConditions();
		initFilter();
		initRepeaters();
		initMedia();
		initHours();
		initHelp();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
