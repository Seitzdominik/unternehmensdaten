/**
 * Backend-Skript.
 *
 * Reines Vanilla-JavaScript ohne jQuery, wird ausschliesslich auf den eigenen
 * Seiten geladen. Dazu gehoeren Registerkarten, Kopieren in die Zwischenablage,
 * abhaengige Fragen, Wiederholungsfelder, das automatische Speichern, die
 * Schnelleinstellungen und das Menue unter 783 px.
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

				item.classList.toggle( 'is-active', selected );
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

		if ( ok ) {
			showCopied( button );
		}

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

	/*
	 * Haengt eine Zeile aus der Vorlage an. quiet: ohne Ansage und ohne den
	 * Fokus zu verschieben, etwa wenn mehrere Zeilen auf einmal entstehen.
	 * Gibt die neue Zeile zurueck.
	 */
	function repeaterAdd( repeater, quiet ) {
		var template = repeater.querySelector( '.undt-repeater__template' );

		if ( ! template ) {
			return null;
		}

		var next = parseInt( repeater.getAttribute( 'data-undt-next' ) || '0', 10 );

		// Der Platzhalter steckt in den Formularnamen und am Ende der IDs.
		var markup = template.innerHTML.split( INDEX_TOKEN ).join( String( next ) );

		repeater.setAttribute( 'data-undt-next', String( next + 1 ) );

		var holder = document.createElement( 'div' );
		holder.innerHTML = markup;

		var row = holder.firstElementChild;

		if ( ! row ) {
			return null;
		}

		repeaterRows( repeater ).appendChild( row );
		repeaterSync( repeater );

		if ( quiet ) {
			return row;
		}

		announce( l10n.rowAdded );

		var first = row.querySelector( 'input:not([type="hidden"]), select, textarea' );

		if ( first ) {
			first.focus();
		}

		return row;
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
				changed( moveRow );

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
				changed( repeater );
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
				changed( clearField );

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

				changed( field );
			} );

			frame.open();
		} );
	}

	/* ------------------------------------------------------ Öffnungszeiten */

	/*
	 * Das Wochenraster des allgemeinen Formulars (Feldtyp hours, etwa in einem
	 * Bereich, den ein Filter ergaenzt). Die Seite Oeffnungszeiten hat seit 0.6.0
	 * ihr eigenes Raster, siehe initHoursPage().
	 */
	function initHours() {
		var rows = Array.prototype.slice.call( document.querySelectorAll( '.undt-hours-edit [data-undt-day]' ) );

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

		/*
		 * Zeiten des ersten Tages auf alle uebrigen uebertragen. Uebernommen
		 * werden Uhrzeiten und der Schalter „geschlossen“, damit die Zeile danach
		 * wirklich gleich aussieht.
		 */
		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest ? event.target.closest( '[data-undt-hours-copy]' ) : null;

			if ( ! button ) {
				return;
			}

			var table = document.getElementById( button.getAttribute( 'data-undt-hours-copy' ) );
			var all   = table ? Array.prototype.slice.call( table.querySelectorAll( '[data-undt-day]' ) ) : [];

			if ( all.length < 2 ) {
				return;
			}

			var quelle = all.shift();
			var zeiten = Array.prototype.slice.call( quelle.querySelectorAll( 'input[type="time"]' ) );
			var zu     = quelle.querySelector( '[data-undt-closed]' );

			all.forEach( function ( row ) {
				Array.prototype.slice.call( row.querySelectorAll( 'input[type="time"]' ) ).forEach( function ( feld, i ) {
					feld.value = zeiten[ i ] ? zeiten[ i ].value : '';
				} );

				var schalter = row.querySelector( '[data-undt-closed]' );

				if ( schalter && zu ) {
					schalter.checked = zu.checked;
				}

				sync( row );
			} );

			changed( table );
			announce( l10n.hoursCopied || l10n.copied );
		} );
	}

	/* ------------------------------------------------ Automatisch speichern */

	/*
	 * Jede Formularseite speichert von selbst: kurz nach der letzten Eingabe,
	 * etwas schneller nach einer Auswahl. Geschickt wird das ganze Formular
	 * einer Option an UNDT_Autosave. Schlaegt das fehl, steht oben rechts eine
	 * Bernstein-Zeile, und nach zehn Sekunden folgt ein neuer Versuch.
	 *
	 * Aenderungen, die kein input- oder change-Ereignis ausloesen (Zeilen
	 * entfernen oder verschieben, Bild waehlen, Zeiten uebertragen), melden
	 * sich ueber das eigene Ereignis undt-change.
	 */
	var save    = l10n.save || null;
	var savers  = [];
	var DELAY   = { input: 800, change: 300 };
	var RETRY   = 10000;

	function changed( element ) {
		var event;

		try {
			event = new CustomEvent( 'undt-change', { bubbles: true } );
		} catch ( error ) {
			event = document.createEvent( 'CustomEvent' );
			event.initCustomEvent( 'undt-change', true, false, null );
		}

		element.dispatchEvent( event );
	}

	function savedEvent( data ) {
		var event;

		try {
			event = new CustomEvent( 'undt-saved', { detail: data } );
		} catch ( error ) {
			event = document.createEvent( 'CustomEvent' );
			event.initCustomEvent( 'undt-saved', false, false, data );
		}

		return event;
	}

	function saveState( state ) {
		Array.prototype.slice.call( document.querySelectorAll( '[data-undt-save]' ) ).forEach( function ( status ) {
			status.setAttribute( 'data-state', state );
		} );
	}

	function payload( target, data ) {
		data = data || new FormData();

		// Das Formular bringt das Feld action von options.php mit, es wird ersetzt.
		data.set( 'action', save.action );
		data.set( 'nonce', save.nonce );
		data.set( 'target', target );

		return data;
	}

	function request( data ) {
		return window.fetch( save.url, {
			method: 'POST',
			credentials: 'same-origin',
			body: data
		} ).then( function ( response ) {
			return response.json().then(
				function ( json ) {
					return { ok: response.ok && json && json.success, data: json && json.data };
				},
				function () {
					return { ok: false, data: null };
				}
			);
		} );
	}

	function badgeElement( count ) {
		var badge = document.createElement( 'span' );
		var sr    = document.createElement( 'span' );

		badge.className = 'undt-badge';
		badge.setAttribute( 'data-undt-badge', '' );
		badge.textContent = String( count );
		sr.className = 'screen-reader-text';
		sr.textContent = ' ' + ( l10n.openItems || '' );
		badge.appendChild( sr );

		return badge;
	}

	/*
	 * Fuehrt die Zaehler der Navigation nach, damit eine eben eingetragene
	 * Pflichtangabe sofort aus dem Zaehler verschwindet.
	 */
	function applyBadges( data ) {
		if ( ! data || ! data.badges ) {
			return;
		}

		Object.keys( data.badges ).forEach( function ( key ) {
			Array.prototype.slice.call( document.querySelectorAll( '[data-undt-badge-for="' + key + '"]' ) ).forEach( function ( tile ) {
				var old = tile.querySelector( '.undt-badge' );

				if ( old ) {
					tile.removeChild( old );
				}

				if ( data.badges[ key ] > 0 ) {
					tile.appendChild( badgeElement( data.badges[ key ] ) );
				}
			} );
		} );

		Array.prototype.slice.call( document.querySelectorAll( '[data-undt-badge-total]' ) ).forEach( function ( holder ) {
			holder.textContent = '';

			if ( data.total > 0 ) {
				holder.appendChild( badgeElement( data.total ) );
			}
		} );
	}

	/*
	 * Teile einer Seite, die vom gespeicherten Stand abhaengen: Vorschau,
	 * Statuszeile, JSON-LD. Das HTML kommt vom Server, bereinigt in
	 * UNDT_Module_View::parts().
	 */
	function applyParts( data ) {
		if ( ! data || ! data.parts ) {
			return;
		}

		Object.keys( data.parts ).forEach( function ( key ) {
			Array.prototype.slice.call( document.querySelectorAll( '[data-undt-part="' + key + '"]' ) ).forEach( function ( holder ) {
				holder.innerHTML = data.parts[ key ];
			} );
		} );

		bannerPreviewReset();
	}

	function Saver( form ) {
		var self    = this;
		var target  = form.getAttribute( 'data-undt-autosave' );
		var timer   = null;
		var retry   = null;
		var running = false;
		var pending = false;

		this.form = form;

		this.schedule = function ( delay ) {
			pending = true;
			window.clearTimeout( timer );
			window.clearTimeout( retry );
			timer = window.setTimeout( self.run, delay );
		};

		this.run = function () {
			window.clearTimeout( timer );
			window.clearTimeout( retry );

			if ( running ) {
				// Laeuft gerade eine Anfrage, folgt die naechste direkt danach.
				pending = true;
				return;
			}

			pending = false;
			running = true;
			saveState( 'saving' );

			request( payload( target, new FormData( form ) ) ).then(
				function ( result ) {
					running = false;

					if ( ! result.ok ) {
						self.fail();
						return;
					}

					save.nonce = result.data.nonce || save.nonce;
					applyBadges( result.data );
					applyParts( result.data );
					document.dispatchEvent( savedEvent( result.data ) );

					if ( result.data.reload ) {
						window.location.reload();
						return;
					}

					if ( pending ) {
						self.run();
						return;
					}

					saveState( 'saved' );
				},
				function () {
					running = false;
					self.fail();
				}
			);
		};

		this.fail = function () {
			pending = true;
			saveState( 'error' );
			announce( l10n.saveFailed );
			retry = window.setTimeout( self.run, RETRY );
		};

		/*
		 * Beim Verlassen der Seite geht, was noch wartet, als Beacon hinterher.
		 * Der Browser schickt ihn auch dann noch ab, wenn die Seite schon weg ist.
		 */
		this.flush = function () {
			if ( ! pending || ! navigator.sendBeacon ) {
				return;
			}

			pending = false;
			window.clearTimeout( timer );
			navigator.sendBeacon( save.url, payload( target, new FormData( form ) ) );
		};

		form.addEventListener( 'input', function ( event ) {
			if ( 'file' !== event.target.type ) {
				self.schedule( DELAY.input );
			}
		} );

		form.addEventListener( 'change', function ( event ) {
			if ( 'file' !== event.target.type ) {
				self.schedule( DELAY.change );
			}
		} );

		form.addEventListener( 'undt-change', function () {
			self.schedule( DELAY.change );
		} );

		// Die Eingabetaste speichert sofort, statt die Seite neu zu laden.
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			self.run();
		} );
	}

	function initAutosave() {
		if ( ! save || ! window.fetch || ! window.FormData ) {
			return;
		}

		savers = Array.prototype.slice
			.call( document.querySelectorAll( 'form[data-undt-autosave]' ) )
			.map( function ( form ) {
				return new Saver( form );
			} );

		document.addEventListener( 'click', function ( event ) {
			if ( event.target.closest && event.target.closest( '[data-undt-save-retry]' ) ) {
				savers.forEach( function ( saver ) {
					saver.run();
				} );
			}
		} );

		window.addEventListener( 'pagehide', function () {
			savers.forEach( function ( saver ) {
				saver.flush();
			} );
		} );
	}

	/* --------------------------------------------- Schnelleinstellungen */

	function quickSave( key, value, done ) {
		if ( ! save || ! window.fetch ) {
			done( false );
			return;
		}

		var data = new FormData();

		data.set( 'value', value );

		request( payload( 'quick:' + key, data ) ).then(
			function ( result ) {
				if ( result.ok ) {
					save.nonce = result.data.nonce || save.nonce;
					applyBadges( result.data );
					applyParts( result.data );
				}

				done( result.ok, result.data );
			},
			function () {
				done( false );
			}
		);
	}

	function bannerSwitches() {
		return Array.prototype.slice.call( document.querySelectorAll( '[data-undt-quick="banner"]' ) );
	}

	function setBanner( on ) {
		bannerSwitches().forEach( function ( item ) {
			item.setAttribute( 'aria-checked', on ? 'true' : 'false' );
		} );

		// Steht die Seite des Banners offen, zieht ihr Schalter mit.
		var field = document.querySelector( 'input[type="checkbox"][name="undt_banner[enabled]"]' );

		if ( field ) {
			field.checked = on;
			bannerTile( on );
		}
	}

	function initQuick() {
		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest ? event.target.closest( '[data-undt-quick="banner"]' ) : null;

			if ( ! button || 'true' === button.getAttribute( 'aria-busy' ) ) {
				return;
			}

			var on = 'true' !== button.getAttribute( 'aria-checked' );

			setBanner( on );
			button.setAttribute( 'aria-busy', 'true' );

			quickSave( 'banner', on ? '1' : '0', function ( ok ) {
				button.removeAttribute( 'aria-busy' );

				if ( ! ok ) {
					setBanner( ! on );
					announce( l10n.saveFailed );
				}
			} );
		} );

		document.addEventListener( 'change', function ( event ) {
			var target = event.target;

			if ( target.matches && target.matches( '[data-undt-quick="copy_format"], input[name="undt_format_choice"]' ) ) {
				applyFormat( target.value );

				quickSave( 'copy_format', target.value, function ( ok ) {
					if ( ! ok ) {
						announce( l10n.saveFailed );
					}
				} );
			}

			if ( target.matches && target.matches( 'input[type="checkbox"][name="undt_banner[enabled]"]' ) ) {
				bannerSwitches().forEach( function ( item ) {
					item.setAttribute( 'aria-checked', target.checked ? 'true' : 'false' );
				} );
			}
		} );
	}

	/* ------------------------------------------------------- Uebersicht */

	/*
	 * Knoepfe, die eine Einstellung setzen und danach die Seite neu laden:
	 * einen Bereich einschalten oder die Einrichtung ueberspringen.
	 */
	function initActions() {
		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest ? event.target.closest( '[data-undt-action]' ) : null;

			if ( ! button || button.disabled ) {
				return;
			}

			button.disabled = true;
			button.setAttribute( 'aria-busy', 'true' );

			quickSave( button.getAttribute( 'data-undt-action' ), button.getAttribute( 'data-undt-value' ) || '', function ( ok ) {
				if ( ok ) {
					window.location.reload();
					return;
				}

				button.disabled = false;
				button.removeAttribute( 'aria-busy' );
				announce( l10n.saveFailed );
			} );
		} );
	}

	// Ab mehr als vier Punkten zeigt „Alle anzeigen“ den Rest.
	function initTodoMore() {
		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest ? event.target.closest( '[data-undt-todo-more]' ) : null;

			if ( ! button ) {
				return;
			}

			var list  = button.closest( '[data-undt-todo]' );
			var first = null;

			Array.prototype.slice.call( list.querySelectorAll( '.undt-todo__row[hidden]' ) ).forEach( function ( row ) {
				row.hidden = false;
				first      = first || row;
			} );

			button.parentNode.removeChild( button );

			// Der Fokus landet auf dem ersten neu sichtbaren Punkt.
			var link = first ? first.querySelector( 'a' ) : null;

			if ( link ) {
				link.focus();
			}
		} );
	}

	/*
	 * Sprung aus der Uebersicht direkt in ein Feld: #undt-<feld> oeffnet die
	 * Registerkarte, in der es steht, scrollt hin und setzt den Fokus.
	 */
	function initJump() {
		var hash = window.location.hash.replace( '#', '' );

		if ( 0 !== hash.indexOf( 'undt-' ) ) {
			return;
		}

		var field = document.getElementById( hash );

		if ( ! field ) {
			// Felder aus mehreren Teilen, etwa eine Seitenauswahl, tragen die ID am ersten Teil.
			field = document.querySelector( '[name^="undt_company[' + hash.replace( 'undt-', '' ) + ']"]' );
		}

		if ( ! field ) {
			return;
		}

		var panel = field.closest( '.undt-panel' );
		var tab   = panel ? document.getElementById( panel.getAttribute( 'aria-labelledby' ) ) : null;

		if ( tab && 'true' !== tab.getAttribute( 'aria-selected' ) ) {
			tab.click();
		}

		field.scrollIntoView( { block: 'center' } );
		field.focus( { preventScroll: true } );
	}

	/* ------------------------------------------------------------ Firma */

	/*
	 * Die Karte „Rechtsform“: „Ändern“ klappt die Fragen auf, „Fertig“ wieder
	 * zu. Ein Link mit #rechtsform, etwa aus dem Erststart, oeffnet sie gleich.
	 */
	function initLegalForm() {
		var toggle = document.querySelector( '[data-undt-legal-toggle]' );

		if ( ! toggle ) {
			return;
		}

		var body = document.getElementById( toggle.getAttribute( 'aria-controls' ) );

		function set( open ) {
			body.hidden = ! open;
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			toggle.textContent = toggle.getAttribute( open ? 'data-label-close' : 'data-label-open' );
		}

		toggle.addEventListener( 'click', function () {
			set( body.hidden );
		} );

		if ( '#rechtsform' === window.location.hash ) {
			set( true );

			var first = body.querySelector( 'select, input:not([type="hidden"])' );

			if ( first ) {
				first.focus();
			}
		}
	}

	// Ob ein Feld leer ist. Seiten- und Medienfelder speichern 0 fuer nichts.
	function isEmpty( wrapper ) {
		var controls = Array.prototype.slice.call( wrapper.querySelectorAll( 'input:not([type="hidden"]), select, textarea' ) );

		return ! controls.some( function ( control ) {
			if ( 'checkbox' === control.type ) {
				return control.checked;
			}

			var value = String( control.value || '' ).trim();

			return '' !== value && '0' !== value;
		} );
	}

	/*
	 * Bernstein-Rand und Hinweis an leeren Pflichtfeldern, dazu die Zaehler der
	 * Registerkarten. Laeuft beim Tippen, nicht erst nach dem Speichern.
	 */
	function refreshMissing( form ) {
		Array.prototype.slice.call( form.querySelectorAll( '[data-undt-row]' ) ).forEach( function ( row ) {
			var missing = Array.prototype.slice.call( row.querySelectorAll( '[data-undt-field][data-undt-required]' ) ).some( function ( field ) {
				return ! field.hidden && isEmpty( field );
			} );

			row.classList.toggle( 'is-missing', missing );

			var hint = row.querySelector( '.undt-frow__missing' );

			if ( hint ) {
				hint.hidden = ! missing;
			}

			// Formatpruefung je Feld, etwa der W-IdNr.
			Array.prototype.slice.call( row.querySelectorAll( '[data-undt-pattern]' ) ).forEach( function ( pattern ) {
				var bad = row.querySelector( '[data-undt-pattern-for="' + pattern.getAttribute( 'data-undt-field' ) + '"]' );

				if ( ! bad ) {
					return;
				}

				var input = pattern.querySelector( 'input' );
				var value = input ? input.value.replace( /\s/g, '' ) : '';

				bad.hidden = pattern.hidden || '' === value || new RegExp( pattern.getAttribute( 'data-undt-pattern' ), 'i' ).test( value );
			} );
		} );

		Array.prototype.slice.call( document.querySelectorAll( '[data-undt-tab]' ) ).forEach( function ( tab ) {
			var panel = document.getElementById( tab.getAttribute( 'aria-controls' ) );
			var count = panel ? panel.querySelectorAll( '[data-undt-row].is-missing:not([hidden])' ).length : 0;
			var badge = tab.querySelector( '[data-undt-tab-count]' );

			if ( badge ) {
				badge.hidden = 0 === count;
				badge.firstChild.nodeValue = String( count );
			}
		} );
	}

	/*
	 * Nach dem Speichern der Rechtsform: welche Felder, Abschnitte und Karten
	 * jetzt gelten. Eingetragenes bleibt in den verborgenen Feldern stehen.
	 */
	function applyCompanyState( state ) {
		var form = document.querySelector( 'form[data-undt-autosave="company"]' );

		if ( ! form || ! state || ! state.applies ) {
			return;
		}

		Array.prototype.slice.call( form.querySelectorAll( '[data-undt-field]' ) ).forEach( function ( field ) {
			field.hidden = ! state.applies[ field.getAttribute( 'data-undt-field' ) ];
		} );

		Array.prototype.slice.call( form.querySelectorAll( '[data-undt-row]' ) ).forEach( function ( row ) {
			row.hidden = ! row.querySelector( '[data-undt-field]:not([hidden])' );
		} );

		Array.prototype.slice.call( form.querySelectorAll( '[data-undt-more]' ) ).forEach( function ( more ) {
			more.hidden = ! more.querySelector( '[data-undt-row]:not([hidden])' );
		} );

		Array.prototype.slice.call( form.querySelectorAll( '[data-undt-section]' ) ).forEach( function ( section ) {
			section.hidden = ! section.querySelector( '[data-undt-row]:not([hidden])' );
		} );

		var current = null;

		Object.keys( state.tabs || {} ).forEach( function ( key ) {
			var tab = document.getElementById( 'undt-tab-' + key );

			if ( ! tab ) {
				return;
			}

			tab.hidden = ! state.tabs[ key ].visible;
			tab.querySelector( '[data-undt-tab-label]' ).textContent = state.tabs[ key ].label;

			if ( 'true' === tab.getAttribute( 'aria-selected' ) ) {
				current = tab;
			}
		} );

		// Verschwindet die offene Karte, springt die Ansicht auf die erste sichtbare.
		if ( current && current.hidden ) {
			var first = document.querySelector( '[data-undt-tab]:not([hidden])' );

			if ( first ) {
				first.click();
			}
		}

		var title   = document.querySelector( '[data-undt-legal-title]' );
		var summary = document.querySelector( '[data-undt-legal-summary]' );

		if ( title ) {
			title.textContent = state.title;
		}

		if ( summary ) {
			summary.textContent = state.summary;
		}

		refreshMissing( form );
	}

	// Vorschlag uebernehmen: der Server setzt den Wert, das Feld zeigt ihn an.
	function initSuggest() {
		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest ? event.target.closest( '[data-undt-suggest]' ) : null;

			if ( ! button ) {
				return;
			}

			var key = button.getAttribute( 'data-undt-suggest' );

			button.disabled = true;

			quickSave( 'suggest', key, function ( ok, data ) {
				if ( ! ok ) {
					button.disabled = false;
					announce( l10n.saveFailed );
					return;
				}

				var field = document.getElementById( 'undt-' + key );

				if ( field && data && 'string' === typeof data.value ) {
					field.value = data.value;
				}

				button.parentNode.removeChild( button );
				announce( l10n.saved );

				var form = document.querySelector( 'form[data-undt-autosave="company"]' );

				if ( form ) {
					refreshMissing( form );
				}

				if ( field ) {
					field.focus();
				}
			} );
		} );
	}

	function initCompany() {
		var form = document.querySelector( 'form[data-undt-autosave="company"]' );

		if ( form ) {
			form.addEventListener( 'input', function () {
				refreshMissing( form );
			} );

			form.addEventListener( 'change', function () {
				refreshMissing( form );
			} );
		}

		document.addEventListener( 'undt-saved', function ( event ) {
			if ( event.detail && event.detail.company ) {
				applyCompanyState( event.detail.company );
			}
		} );
	}

	/* ------------------------------------------------------ Kopierformat */

	/*
	 * Das Format aus „Kopieren als“ gilt fuer alle Kopierknoepfe der Seite:
	 * am Feld, in den Bausteinen und in der Liste der Angaben. Jeder Knopf traegt
	 * alle Formate und nimmt beim Wechsel das passende.
	 */
	var copyFormat = l10n.format || 'shortcode';

	function applyFormat( format ) {
		copyFormat = format;

		Array.prototype.slice.call( document.querySelectorAll( '[data-undt-codes]' ) ).forEach( function ( button ) {
			var codes = {};
			var shown = {};

			try {
				codes = JSON.parse( button.getAttribute( 'data-undt-codes' ) ) || {};
				shown = JSON.parse( button.getAttribute( 'data-undt-shown' ) || '{}' ) || {};
			} catch ( error ) {
				return;
			}

			if ( ! codes[ format ] ) {
				return;
			}

			button.setAttribute( 'data-undt-copy', codes[ format ] );

			var label = button.querySelector( '[data-undt-code-label]' );

			if ( label ) {
				label.textContent = shown[ format ] || codes[ format ];
			}
		} );

		// Beide Bedienelemente zeigen dasselbe Format.
		Array.prototype.slice.call( document.querySelectorAll( '[data-undt-quick="copy_format"]' ) ).forEach( function ( select ) {
			select.value = format;
		} );

		Array.prototype.slice.call( document.querySelectorAll( 'input[name="undt_format_choice"]' ) ).forEach( function ( radio ) {
			radio.checked = radio.value === format;
		} );

		Array.prototype.slice.call( document.querySelectorAll( '[data-undt-format-help]' ) ).forEach( function ( help ) {
			help.hidden = help.getAttribute( 'data-undt-format-help' ) !== format;
		} );
	}

	// Nach dem Kopieren am Feld steht darunter, was kopiert wurde.
	function showCopied( button ) {
		if ( ! button.classList.contains( 'undt-fcopy' ) ) {
			return;
		}

		var status = button.closest( '[data-undt-field]' );
		status     = status ? status.querySelector( '.undt-frow__copied' ) : null;

		if ( ! status ) {
			return;
		}

		var shown = {};

		try {
			shown = JSON.parse( button.getAttribute( 'data-undt-shown' ) || '{}' ) || {};
		} catch ( error ) {
			shown = {};
		}

		status.textContent = ( l10n.copiedPrefix || 'Kopiert:' ) + ' ' + ( shown[ copyFormat ] || button.getAttribute( 'data-undt-copy' ) );

		window.clearTimeout( status.undtTimer );
		status.undtTimer = window.setTimeout( function () {
			status.textContent = '';
		}, 2400 );
	}

	/* --------------------------------------------------------- Einbinden */

	/*
	 * Der Code eines Bausteins aus seinen Haekchen. Spiegelt UNDT_Embed::code():
	 * ein Haekchen setzt sein Attribut, eine Liste wie show beim Footer erscheint
	 * nur, wenn nicht alles gewaehlt ist.
	 */
	function blockCode( block ) {
		var attrs = [];
		var lists = {};
		var order = [];

		Array.prototype.slice.call( block.querySelectorAll( '[data-undt-option]' ) ).forEach( function ( box ) {
			var list = box.getAttribute( 'data-list' );

			if ( list ) {
				if ( ! lists[ list ] ) {
					lists[ list ] = { all: true, items: [] };
					order.push( list );
				}

				if ( box.checked ) {
					lists[ list ].items.push( box.getAttribute( 'data-item' ) );
				} else {
					lists[ list ].all = false;
				}

				return;
			}

			var attr = box.checked ? box.getAttribute( 'data-on' ) : box.getAttribute( 'data-off' );

			if ( attr ) {
				attrs.push( attr );
			}
		} );

		order.forEach( function ( list ) {
			if ( ! lists[ list ].all ) {
				attrs.push( list + '="' + lists[ list ].items.join( ',' ) + '"' );
			}
		} );

		return '[' + block.getAttribute( 'data-undt-tag' ) + ( attrs.length ? ' ' + attrs.join( ' ' ) : '' ) + ']';
	}

	function initBlocks() {
		document.addEventListener( 'click', function ( event ) {
			var toggle = event.target.closest ? event.target.closest( '[data-undt-preview-toggle]' ) : null;

			if ( ! toggle ) {
				return;
			}

			var more = document.getElementById( toggle.getAttribute( 'aria-controls' ) );
			var open = more.hidden;

			more.hidden = ! open;
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			toggle.textContent = toggle.getAttribute( open ? 'data-label-close' : 'data-label-open' );
		} );

		document.addEventListener( 'change', function ( event ) {
			if ( ! event.target.matches || ! event.target.matches( '[data-undt-option]' ) ) {
				return;
			}

			var block   = event.target.closest( '[data-undt-block]' );
			var code    = blockCode( block );
			var chip    = block.querySelector( '[data-undt-block-code]' );
			var label   = chip.querySelector( '[data-undt-code-label]' );
			var preview = block.querySelector( '[data-undt-preview]' );

			chip.setAttribute( 'data-undt-copy', code );
			label.textContent = code;

			// Die Vorschau rendert der Server mit den gespeicherten Angaben.
			preview.setAttribute( 'aria-busy', 'true' );

			quickSave( 'preview', code, function ( ok, data ) {
				preview.removeAttribute( 'aria-busy' );

				if ( ok && data && 'string' === typeof data.html ) {
					preview.innerHTML = data.html;
				}
			} );
		} );
	}

	// Suche und Filter fuer Bausteine und einzelne Angaben.
	function initEmbedSearch() {
		var input  = document.getElementById( 'undt-embed-search' );
		var values = document.querySelector( '[data-undt-values]' );

		if ( ! input || ! values ) {
			return;
		}

		var filter   = '';
		var showAll  = false;
		var timer    = null;

		function apply() {
			var term = input.value.trim().toLowerCase();

			// Waehrend einer Suche treten Rechtsseiten und Entwickler zurueck.
			Array.prototype.slice.call( document.querySelectorAll( '[data-undt-searchless]' ) ).forEach( function ( part ) {
				part.hidden = '' !== term;
			} );

			var anyBlock = false;

			Array.prototype.slice.call( document.querySelectorAll( '[data-undt-blockgroup]' ) ).forEach( function ( group ) {
				var anyInGroup = false;

				Array.prototype.slice.call( group.querySelectorAll( '[data-undt-block]' ) ).forEach( function ( block ) {
					var hit = '' === term || -1 !== block.getAttribute( 'data-undt-search' ).indexOf( term );

					block.hidden = ! hit;
					anyInGroup   = anyInGroup || hit;
				} );

				group.hidden = ! anyInGroup;
				anyBlock     = anyBlock || anyInGroup;
			} );

			document.querySelector( '[data-undt-blocks-empty]' ).hidden = anyBlock;

			var anyValue = false;

			Array.prototype.slice.call( values.querySelectorAll( '[data-undt-value]' ) ).forEach( function ( row ) {
				var hit = ( '' === filter || row.getAttribute( 'data-undt-group' ) === filter )
					&& ( '' === term || -1 !== row.getAttribute( 'data-undt-search' ).indexOf( term ) );

				// Ohne Suche und Filter stehen zuerst nur acht da.
				var beyond = row.hasAttribute( 'data-undt-beyond' ) && ! showAll && '' === term && '' === filter;

				row.hidden = ! hit || beyond;
				anyValue   = anyValue || hit;
			} );

			values.querySelector( '[data-undt-values-empty]' ).hidden = anyValue;

			var more = values.querySelector( '[data-undt-values-more]' );

			if ( more ) {
				more.parentNode.hidden = showAll || '' !== term || '' !== filter;
			}
		}

		input.addEventListener( 'input', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( apply, 120 );
		} );

		values.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-undt-filter]' );

			if ( button ) {
				filter = button.getAttribute( 'data-undt-filter' );

				Array.prototype.slice.call( values.querySelectorAll( '[data-undt-filter]' ) ).forEach( function ( item ) {
					item.setAttribute( 'aria-pressed', item === button ? 'true' : 'false' );
				} );

				apply();
				return;
			}

			if ( event.target.closest( '[data-undt-values-more]' ) ) {
				showAll = true;
				apply();
			}
		} );
	}

	// Nach dem Speichern einer Rechtsseite: ihr neuer Stand.
	function initPages() {
		document.addEventListener( 'undt-saved', function ( event ) {
			var pages = event.detail && event.detail.pages;

			if ( ! pages ) {
				return;
			}

			Object.keys( pages ).forEach( function ( key ) {
				var row    = document.querySelector( '[data-undt-page="' + key + '"]' );
				var status = row ? row.querySelector( '[data-undt-page-status]' ) : null;

				if ( ! status ) {
					return;
				}

				status.className = 'undt-pagerow__status undt-pagerow__status--' + pages[ key ].tone;
				status.textContent = '';

				if ( 'ok' !== pages[ key ].tone ) {
					var dot = document.createElement( 'span' );

					dot.className = 'undt-dot-s';
					dot.setAttribute( 'aria-hidden', 'true' );
					status.appendChild( dot );
				}

				var text = document.createElement( 'span' );

				text.textContent = pages[ key ].status;
				status.appendChild( text );
			} );
		} );
	}

	/* ------------------------------------------------- Menue unter 783 px */

	function initMenu() {
		var dialog = document.getElementById( 'undt-mmenu' );
		var opener = document.querySelector( '[data-undt-menu-open]' );

		if ( ! dialog || ! opener ) {
			return;
		}

		function focusables() {
			return Array.prototype.slice.call( dialog.querySelectorAll( 'a[href], button:not([disabled]), select' ) );
		}

		function open() {
			dialog.hidden = false;
			opener.setAttribute( 'aria-expanded', 'true' );
			document.body.classList.add( 'undt-menu-open' );

			var close = dialog.querySelector( '[data-undt-menu-close]' );

			if ( close ) {
				close.focus();
			}
		}

		function close( restore ) {
			if ( dialog.hidden ) {
				return;
			}

			dialog.hidden = true;
			opener.setAttribute( 'aria-expanded', 'false' );
			document.body.classList.remove( 'undt-menu-open' );

			if ( restore ) {
				opener.focus();
			}
		}

		opener.addEventListener( 'click', open );

		dialog.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-undt-menu-close]' ) ) {
				close( true );
			}
		} );

		// Der Fokus bleibt im Menue, solange es offen ist.
		dialog.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				close( true );
				return;
			}

			if ( 'Tab' !== event.key ) {
				return;
			}

			var items = focusables();
			var first = items[ 0 ];
			var last  = items[ items.length - 1 ];

			if ( event.shiftKey && document.activeElement === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		} );

		// Wird das Fenster breit genug fuer die Seitenleiste, entfaellt das Menue.
		window.addEventListener( 'resize', function () {
			if ( window.innerWidth > 782 ) {
				close( false );
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

	/* ------------------------------------------------------ Seitenfelder */

	/*
	 * Das Adressfeld erscheint nur, wenn „Eigene Adresse“ gewählt ist, und
	 * bekommt dann gleich den Fokus. Sein Inhalt bleibt beim Umschalten stehen;
	 * gespeichert wird ohnehin nur, was die Auswahl vorgibt.
	 */
	function initLinks() {
		Array.prototype.slice
			.call( document.querySelectorAll( '[data-undt-link]' ) )
			.forEach( function ( select ) {
				var input = select.parentNode ? select.parentNode.querySelector( '[data-undt-link-url]' ) : null;

				if ( ! input ) {
					return;
				}

				function sync() {
					input.hidden = 'url' !== select.value;
				}

				select.addEventListener( 'change', function () {
					sync();

					if ( ! input.hidden ) {
						input.focus();
					}
				} );

				sync();
			} );
	}

	/* ------------------------------------------------ Plattform-Symbole */

	/*
	 * Neben der Plattform-Auswahl steht ihr Symbol. Beim Umschalten kommt das
	 * neue aus der Symbolsammlung des Wiederholungsfeldes. Das gilt auch fuer
	 * Zeilen, die erst nachtraeglich hinzugefuegt werden.
	 */
	function initIconSelects() {
		document.addEventListener( 'change', function ( event ) {
			var select = event.target;

			if ( ! select.matches || ! select.matches( '[data-undt-icon-select]' ) ) {
				return;
			}

			var wrap     = select.closest( '.undt-select-icon' );
			var repeater = select.closest( '[data-undt-repeater]' );
			var preview  = wrap ? wrap.querySelector( '.undt-select-icon__preview' ) : null;
			var library  = repeater ? repeater.querySelector( '.undt-icon-library' ) : null;

			if ( ! preview || ! library ) {
				return;
			}

			var icon = null;

			Array.prototype.slice.call( library.children ).forEach( function ( item ) {
				if ( item.getAttribute( 'data-undt-icon' ) === select.value ) {
					icon = item;
				}
			} );

			while ( preview.firstChild ) {
				preview.removeChild( preview.firstChild );
			}

			if ( icon && icon.firstElementChild ) {
				preview.appendChild( icon.firstElementChild.cloneNode( true ) );
			}
		} );
	}

	/* ------------------------------------------ Seiten der Inhaltsbereiche */

	function all( selector, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( selector ) );
	}

	/*
	 * Oeffnungszeiten: Schalter je Tag, zweites Zeitfenster („Pause“), Montag
	 * auf die Werktage uebertragen, Termine mit Art und Feiertage nach Land.
	 */
	function daySync( day ) {
		var toggle = day.querySelector( '[data-undt-day-open]' );
		var open   = !! ( toggle && toggle.checked );
		var second = day.querySelector( '[data-undt-slot2]' );
		var state  = day.querySelector( '[data-undt-day-state]' );
		var add    = day.querySelector( '[data-undt-add-slot]' );

		// Ob die Pause gilt, steht am Fenster selbst; ein geschlossener Tag behaelt sie.
		var has2 = !! ( second && '1' === second.getAttribute( 'data-active' ) );

		day.classList.toggle( 'is-open', open );

		all( '.undt-day__times', day ).forEach( function ( times ) {
			times.hidden = ! open;
		} );

		if ( second ) {
			second.hidden = ! open || ! has2;
		}

		if ( add ) {
			add.hidden = ! open || has2;
		}

		if ( state ) {
			state.textContent = state.getAttribute( open ? 'data-open' : 'data-closed' );
		}
	}

	function initHoursPage() {
		var days = all( '.undt-day[data-undt-day]' );

		if ( ! days.length ) {
			return;
		}

		document.addEventListener( 'change', function ( event ) {
			var target = event.target;

			if ( ! target.matches ) {
				return;
			}

			if ( target.matches( '[data-undt-day-open]' ) ) {
				var day   = target.closest( '.undt-day' );
				var first = day.querySelectorAll( '.undt-day__col:not([data-undt-slot2]) input[type="time"]' );

				// Wer einen Tag oeffnet, bekommt Zeiten zum Anpassen statt leerer Felder.
				if ( target.checked && first.length === 2 && ! first[0].value && ! first[1].value ) {
					first[0].value = '08:00';
					first[1].value = '17:00';
				}

				daySync( day );
			}

			if ( target.matches( '[data-undt-special-kind]' ) ) {
				var times = target.closest( '[data-undt-special-row]' ).querySelector( '[data-undt-special-times]' );

				if ( times ) {
					times.hidden = '1' === target.value;
				}
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! event.target.closest ) {
				return;
			}

			var add = event.target.closest( '[data-undt-add-slot]' );

			if ( add ) {
				var addDay = add.closest( '.undt-day' );
				var second = addDay.querySelector( '[data-undt-slot2]' );

				second.setAttribute( 'data-active', '1' );
				daySync( addDay );

				var field = second.querySelector( 'input[type="time"]' );

				if ( field ) {
					field.focus();
				}

				return;
			}

			var remove = event.target.closest( '[data-undt-remove-slot]' );

			if ( remove ) {
				var removeDay = remove.closest( '.undt-day' );
				var slot      = remove.closest( '[data-undt-slot2]' );

				all( 'input[type="time"]', slot ).forEach( function ( field ) {
					field.value = '';
				} );

				slot.setAttribute( 'data-active', '0' );
				daySync( removeDay );
				changed( removeDay );

				var again = removeDay.querySelector( '[data-undt-add-slot]' );

				if ( again ) {
					again.focus();
				}

				return;
			}

			if ( event.target.closest( '[data-undt-hours-weekdays]' ) ) {
				var source = document.querySelector( '.undt-day[data-undt-day="mon"]' );

				if ( ! source ) {
					return;
				}

				var times  = all( 'input[type="time"]', source );
				var open   = source.querySelector( '[data-undt-day-open]' ).checked;
				var slot2  = source.querySelector( '[data-undt-slot2]' ).getAttribute( 'data-active' );

				[ 'tue', 'wed', 'thu', 'fri' ].forEach( function ( key ) {
					var target = document.querySelector( '.undt-day[data-undt-day="' + key + '"]' );

					if ( ! target ) {
						return;
					}

					target.querySelector( '[data-undt-day-open]' ).checked = open;
					all( 'input[type="time"]', target ).forEach( function ( field, i ) {
						field.value = times[ i ] ? times[ i ].value : '';
					} );
					target.querySelector( '[data-undt-slot2]' ).setAttribute( 'data-active', slot2 || '0' );
					daySync( target );
				} );

				changed( source );
				announce( l10n.hoursCopied || l10n.copied );
				return;
			}

			var holidays = event.target.closest( '[data-undt-holidays]' );

			if ( holidays ) {
				addHolidays( holidays );
			}
		} );
	}

	// Traegt die Feiertage ein, die noch nicht in der Liste stehen.
	function addHolidays( button ) {
		var list     = [];
		var repeater = document.querySelector( '.undt-special[data-undt-repeater]' );

		try {
			list = JSON.parse( button.getAttribute( 'data-undt-holidays' ) ) || [];
		} catch ( error ) {
			list = [];
		}

		if ( ! repeater ) {
			return;
		}

		var known = all( '.undt-srow__date', repeater ).map( function ( field ) {
			return field.value;
		} );
		var count = 0;

		list.forEach( function ( holiday ) {
			if ( known.indexOf( holiday.date ) !== -1 ) {
				return;
			}

			var row = repeaterAdd( repeater, true );

			if ( ! row ) {
				return;
			}

			row.querySelector( '.undt-srow__date' ).value  = holiday.date;
			row.querySelector( '.undt-srow__label' ).value = holiday.label;
			row.querySelector( '[data-undt-special-kind]' ).value = '1';
			count++;
		} );

		if ( count ) {
			changed( repeater );
			announce( ( l10n.holidaysAdded || '%d' ).replace( '%d', String( count ) ) );
		} else {
			announce( button.getAttribute( 'data-done' ) || '' );
		}
	}

	/*
	 * Social Media: Adresse einfuegen, die Plattform wird erkannt, die neue
	 * Zeile entsteht aus der Vorlage. Dieselbe Erkennung wie
	 * UNDT_Modules::detect_platform().
	 */
	function detectPlatform( url, hosts ) {
		var host = '';
		var path = '';

		url = String( url || '' ).trim();

		if ( ! url ) {
			return '';
		}

		if ( ! /^[a-z][a-z0-9+.-]*:\/\//i.test( url ) ) {
			url = 'https://' + url;
		}

		try {
			var parsed = new URL( url );

			host = parsed.hostname.toLowerCase().replace( /^(www|m|mobile)\./, '' );
			path = parsed.pathname;
		} catch ( error ) {
			return '';
		}

		var found = '';

		Object.keys( hosts ).some( function ( platform ) {
			return hosts[ platform ].some( function ( known ) {
				if ( host === known || host.slice( -( known.length + 1 ) ) === '.' + known ) {
					found = platform;
					return true;
				}

				return false;
			} );
		} );

		if ( found ) {
			return found;
		}

		return host && /^\/@[A-Za-z0-9_.]+\/?$/.test( path ) ? 'mastodon' : 'other';
	}

	function initSocialPage() {
		var input    = document.querySelector( '[data-undt-social-new]' );
		var repeater = document.querySelector( '.undt-profiles[data-undt-repeater]' );

		if ( ! input || ! repeater ) {
			return;
		}

		var button    = document.querySelector( '[data-undt-social-add]' );
		var hint      = document.querySelector( '[data-undt-social-hint]' );
		var names     = {};
		var hosts     = {};

		try {
			names = JSON.parse( repeater.getAttribute( 'data-undt-platforms' ) ) || {};
			hosts = JSON.parse( repeater.getAttribute( 'data-undt-hosts' ) ) || {};
		} catch ( error ) {
			return;
		}

		function current() {
			return detectPlatform( input.value, hosts );
		}

		function refresh() {
			var platform = current();

			button.disabled = ! platform;

			// Erkannt wird nur, was eine Plattform hat; „Andere“ ist kein Fund.
			if ( platform && 'other' !== platform ) {
				hint.textContent = '';
				var strong = document.createElement( 'strong' );
				strong.textContent = names[ platform ] || platform;
				hint.appendChild( strong );
				hint.appendChild( document.createTextNode( ' ' + hint.getAttribute( 'data-found' ).replace( '%s', '' ).trim() ) );
			} else {
				hint.textContent = hint.getAttribute( 'data-idle' );
			}
		}

		function add() {
			var platform = current();
			var url      = input.value.trim();

			if ( ! platform ) {
				return;
			}

			if ( ! /^[a-z][a-z0-9+.-]*:\/\//i.test( url ) ) {
				url = 'https://' + url;
			}

			var row = repeaterAdd( repeater, true );

			if ( ! row ) {
				return;
			}

			var name    = names[ platform ] || platform;
			var library = repeater.querySelector( '.undt-icon-library' );
			var icon    = library ? library.querySelector( '[data-undt-icon="' + platform + '"]' ) : null;
			var holder  = row.querySelector( '[data-undt-platform-icon]' );
			var label   = row.querySelector( '[data-undt-label]' );

			row.querySelector( '[data-undt-platform]' ).value = platform;
			row.querySelector( '[data-undt-url]' ).value      = url;
			row.querySelector( '[data-undt-platform-name]' ).textContent = name;
			row.querySelector( '[data-undt-url-text]' ).textContent      = url.replace( /^https?:\/\/(www\.)?/, '' );
			label.placeholder = name;
			label.setAttribute( 'aria-label', ( l10n.labelFor || '%s' ).replace( '%s', name ) );

			if ( icon && icon.firstElementChild && holder ) {
				holder.appendChild( icon.firstElementChild.cloneNode( true ) );
			}

			input.value = '';
			refresh();
			changed( row );
			announce( l10n.rowAdded );
			input.focus();
		}

		input.addEventListener( 'input', refresh );
		input.addEventListener( 'keydown', function ( event ) {
			// Die Eingabetaste fuegt hinzu, statt das Formular zu speichern.
			if ( 'Enter' === event.key ) {
				event.preventDefault();
				add();
			}
		} );
		button.addEventListener( 'click', add );

		// Der Pfeil fuer neue Tabs ergibt nur mit neuen Tabs Sinn.
		var newTab = document.querySelector( '[data-undt-newtab]' );
		var arrow  = document.querySelector( '[data-undt-newtab-arrow]' );

		function syncArrow() {
			if ( newTab && arrow ) {
				arrow.disabled = ! newTab.checked;
			}
		}

		if ( newTab ) {
			newTab.addEventListener( 'change', syncArrow );
			syncArrow();
		}

		refresh();
	}

	/*
	 * Infobanner: Zeichen zaehlen, die Vorschau wegklicken und wieder
	 * einblenden, die Kachel im Kopf und der Shortcode beim Selbst-Platzieren.
	 */
	function bannerPreviewReset() {
		var holder = document.querySelector( '[data-undt-banner-preview]' );
		var gone   = document.querySelector( '[data-undt-banner-gone]' );

		if ( holder && gone ) {
			holder.hidden = false;
			gone.hidden   = true;
		}
	}

	function bannerTile( on ) {
		all( '.undt-bhead .undt-tile' ).forEach( function ( tile ) {
			tile.classList.toggle( 'undt-tile--brand', on );
			tile.classList.toggle( 'undt-tile--muted', ! on );
		} );
	}

	function countChars( field ) {
		var wrap = field.closest( '.undt-counted' );
		var max  = parseInt( field.getAttribute( 'data-undt-count' ), 10 ) || 140;
		var num  = wrap.querySelector( '[data-undt-count-num]' );
		var warn = wrap.querySelector( '[data-undt-count-warn]' );
		var len  = Array.from ? Array.from( field.value ).length : field.value.length;

		num.textContent = num.getAttribute( 'data-template' ).replace( '%d', String( len ) );
		warn.hidden     = len <= max;
	}

	function initBannerPage() {
		all( '[data-undt-count]' ).forEach( function ( field ) {
			countChars( field );
			field.addEventListener( 'input', function () {
				countChars( field );
			} );
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! event.target.closest ) {
				return;
			}

			var close = event.target.closest( '[data-undt-banner-preview] .undt-banner__close' );

			if ( close ) {
				var holder = close.closest( '[data-undt-banner-preview]' );
				var gone   = document.querySelector( '[data-undt-banner-gone]' );

				holder.hidden = true;

				if ( gone ) {
					gone.hidden = false;
					gone.querySelector( 'button' ).focus();
				}

				return;
			}

			if ( event.target.closest( '[data-undt-banner-back]' ) ) {
				bannerPreviewReset();

				// Der Fokus kehrt zum Schliessen-Knopf zurueck, sonst faellt er an den Seitenanfang.
				var again = document.querySelector( '[data-undt-banner-preview] .undt-banner__close' );

				if ( again ) {
					again.focus();
				}
			}
		} );

		document.addEventListener( 'change', function ( event ) {
			var target = event.target;

			if ( ! target.matches ) {
				return;
			}

			if ( target.matches( '[data-undt-banner-q]' ) ) {
				bannerTile( target.checked );
			}

			if ( target.matches( 'input[name="undt_banner[auto_output]"]' ) ) {
				var manual = document.querySelector( '[data-undt-banner-manual]' );

				if ( manual ) {
					manual.hidden = '0' !== target.value;
				}

				// Die Notiz im zugeklappten Kopf nennt die gewaehlte Platzierung.
				var details = target.closest( 'details' );
				var note    = details ? details.querySelector( '.undt-details__note' ) : null;
				var label   = target.closest( '.undt-choice' ).querySelector( '.undt-choice__label' );

				if ( note && label ) {
					note.textContent = label.textContent;
				}
			}
		} );
	}

	/*
	 * Preise: Gruppen gibt es nur auf der Seite. Jede Leistung traegt den
	 * Namen ihrer Gruppe in einem versteckten Feld, die Reihenfolge im
	 * Formular ist die Reihenfolge der Ausgabe.
	 */
	function pricesNext( root ) {
		var next = parseInt( root.getAttribute( 'data-undt-next' ) || '0', 10 );

		root.setAttribute( 'data-undt-next', String( next + 1 ) );

		return next;
	}

	function fromTemplate( template, index ) {
		var holder = document.createElement( 'div' );

		holder.innerHTML = template.innerHTML.split( INDEX_TOKEN ).join( String( index ) );

		return holder.firstElementChild;
	}

	function priceGroupsSync( root ) {
		all( '[data-undt-pgroup]', root ).forEach( function ( group, index, groups ) {
			var up   = group.querySelector( '.undt-pgroup__move[data-dir="up"]' );
			var down = group.querySelector( '.undt-pgroup__move[data-dir="down"]' );

			if ( up ) {
				up.disabled = 0 === index;
			}

			if ( down ) {
				down.disabled = index === groups.length - 1;
			}
		} );
	}

	function initPricesPage() {
		var root = document.querySelector( '[data-undt-pgroups]' );

		if ( ! root ) {
			return;
		}

		var groupTemplate = document.querySelector( '[data-undt-pgroup-template]' );
		var itemTemplate  = document.querySelector( '[data-undt-pitem-template]' );

		priceGroupsSync( root );

		root.addEventListener( 'input', function ( event ) {
			if ( event.target.matches && event.target.matches( '[data-undt-pgroup-name]' ) ) {
				var group = event.target.closest( '[data-undt-pgroup]' );

				all( '[data-undt-pitem-group]', group ).forEach( function ( hidden ) {
					hidden.value = event.target.value;
				} );
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! event.target.closest ) {
				return;
			}

			var target = event.target;
			var group  = target.closest( '[data-undt-pgroup]' );

			if ( target.closest( '[data-undt-pitem-add]' ) && group ) {
				var item = fromTemplate( itemTemplate, pricesNext( root ) );

				item.querySelector( '[data-undt-pitem-group]' ).value = group.querySelector( '[data-undt-pgroup-name]' ).value;
				group.querySelector( '[data-undt-pgroup-items]' ).appendChild( item );
				item.querySelector( 'input[type="text"]' ).focus();
				announce( l10n.rowAdded );
				return;
			}

			var removeItem = target.closest( '.undt-pitem__remove' );

			if ( removeItem ) {
				var row  = removeItem.closest( '[data-undt-pitem]' );
				var list = row.parentNode;

				list.removeChild( row );
				changed( root );
				announce( l10n.rowRemoved );

				var next = list.querySelector( 'input[type="text"]' ) || group.querySelector( '[data-undt-pitem-add]' );

				if ( next ) {
					next.focus();
				}

				return;
			}

			if ( target.closest( '[data-undt-pgroup-add]' ) ) {
				var fresh = fromTemplate( groupTemplate, pricesNext( root ) );

				root.appendChild( fresh );
				priceGroupsSync( root );
				fresh.querySelector( '[data-undt-pgroup-name]' ).focus();
				announce( l10n.rowAdded );
				return;
			}

			var move = target.closest( '.undt-pgroup__move' );

			if ( move && group ) {
				var dir     = move.getAttribute( 'data-dir' );
				var sibling = 'up' === dir ? group.previousElementSibling : group.nextElementSibling;

				if ( sibling ) {
					root.insertBefore( 'up' === dir ? group : sibling, 'up' === dir ? sibling : group );
					priceGroupsSync( root );
					changed( root );
					announce( l10n.rowMoved );

					if ( ! move.disabled ) {
						move.focus();
					}
				}

				return;
			}

			if ( target.closest( '.undt-pgroup__remove' ) && group ) {
				if ( ! window.confirm( l10n.confirmGroup || l10n.confirmRow ) ) {
					return;
				}

				root.removeChild( group );
				priceGroupsSync( root );
				changed( root );
				announce( l10n.rowRemoved );

				var add = document.querySelector( '[data-undt-pgroup-add]' );

				if ( add ) {
					add.focus();
				}
			}
		} );
	}

	// FAQ: Fragen auf- und zuklappen, der Titel folgt der Frage.
	function initFaqPage() {
		document.addEventListener( 'click', function ( event ) {
			var toggle = event.target.closest ? event.target.closest( '[data-undt-faq-toggle]' ) : null;

			if ( ! toggle ) {
				return;
			}

			var body = document.getElementById( toggle.getAttribute( 'aria-controls' ) );
			var open = 'true' !== toggle.getAttribute( 'aria-expanded' );

			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

			if ( body ) {
				body.hidden = ! open;
			}
		} );

		document.addEventListener( 'input', function ( event ) {
			if ( ! event.target.matches || ! event.target.matches( '[data-undt-faq-question]' ) ) {
				return;
			}

			var title = event.target.closest( '[data-undt-faq]' ).querySelector( '[data-undt-faq-title]' );

			title.textContent = event.target.value.trim() || title.getAttribute( 'data-empty' );
		} );
	}

	/*
	 * Suchmaschinen: das Koordinaten-Feld sagt sofort, was es verstanden hat.
	 * Dieselbe Regel wie UNDT_Content::parse_geo().
	 */
	function parseGeo( value ) {
		var m = String( value || '' ).trim().match( /^(-?\d{1,2}(?:[.,]\d+)?)\s*[,;]?\s+(-?\d{1,3}(?:[.,]\d+)?)$|^(-?\d{1,2}(?:\.\d+)?)\s*[,;]\s*(-?\d{1,3}(?:\.\d+)?)$/ );

		if ( ! m ) {
			return null;
		}

		var lat = ( m[1] !== undefined ? m[1] : m[3] ).replace( ',', '.' );
		var lng = ( m[1] !== undefined ? m[2] : m[4] ).replace( ',', '.' );

		if ( Math.abs( parseFloat( lat ) ) > 90 || Math.abs( parseFloat( lng ) ) > 180 ) {
			return null;
		}

		return [ lat, lng ];
	}

	function initSeoPage() {
		var field = document.querySelector( '[data-undt-geo]' );

		if ( ! field ) {
			return;
		}

		var ok  = document.querySelector( '[data-undt-geo-ok]' );
		var bad = document.querySelector( '[data-undt-geo-bad]' );

		function sync() {
			var value  = field.value.trim();
			var parsed = parseGeo( value );

			ok.hidden  = ! parsed;
			bad.hidden = ! value || !! parsed;

			if ( parsed ) {
				ok.textContent = ok.getAttribute( 'data-template' ).replace( '%1$s', parsed[0] ).replace( '%2$s', parsed[1] );
			}
		}

		field.addEventListener( 'input', sync );
		sync();
	}

	/*
	 * Sichern & Uebertragen: Datei waehlen, Bereiche auswaehlen, einspielen.
	 * Die Datei geht an UNDT_Transfer::ajax_preview() und bleibt dort liegen,
	 * bis „Einspielen“ kommt oder eine andere gewaehlt wird.
	 */
	function initImport() {
		var box = document.querySelector( '[data-undt-import]' );

		if ( ! box || ! window.fetch || ! window.FormData ) {
			return;
		}

		var form    = box.querySelector( '[data-undt-import-form]' );
		var file    = box.querySelector( '[data-undt-import-file]' );
		var drop    = box.querySelector( '[data-undt-drop]' );
		var chosen  = box.querySelector( '[data-undt-import-chosen]' );
		var error   = box.querySelector( '[data-undt-import-error]' );
		var areas   = box.querySelector( '[data-undt-import-areas]' );
		var runbox  = box.querySelector( '[data-undt-import-runbox]' );
		var run     = box.querySelector( '[data-undt-import-run]' );
		var done    = box.querySelector( '[data-undt-import-done]' );
		var steps   = all( '[data-undt-istep]', box );

		function post( action, data ) {
			data.set( 'action', action );
			data.set( 'nonce', box.getAttribute( 'data-nonce' ) );

			return window.fetch( box.getAttribute( 'data-url' ), {
				method: 'POST',
				credentials: 'same-origin',
				body: data
			} ).then( function ( response ) {
				return response.json().then( function ( json ) {
					return json || { success: false, data: {} };
				}, function () {
					return { success: false, data: {} };
				} );
			}, function () {
				return { success: false, data: {} };
			} );
		}

		function step( current ) {
			steps.forEach( function ( item ) {
				var n = parseInt( item.getAttribute( 'data-undt-istep' ), 10 );

				item.classList.toggle( 'undt-steps__item--done', n < current );
				item.classList.toggle( 'undt-steps__item--current', n === current || ( current > 1 && n > 1 ) );
				item.classList.toggle( 'undt-steps__item--open', n > current && current === 1 );
			} );
		}

		function showError( message ) {
			error.textContent = '';
			var dot = document.createElement( 'span' );
			dot.className = 'undt-amber__dot';
			dot.setAttribute( 'aria-hidden', 'true' );
			error.appendChild( dot );
			error.appendChild( document.createTextNode( message || l10n.saveFailed ) );
			error.hidden = false;
			announce( message || l10n.saveFailed );
		}

		function reset() {
			file.value    = '';
			drop.hidden   = false;
			chosen.hidden = true;
			error.hidden  = true;
			areas.hidden  = true;
			runbox.hidden = true;
			areas.textContent = '';
			step( 1 );
		}

		function selected() {
			return all( 'input[type="checkbox"]:checked', areas ).map( function ( box ) {
				return box.value;
			} );
		}

		function syncRun() {
			var count = selected().length;

			run.disabled    = 0 === count;
			run.textContent = 1 === count ? run.getAttribute( 'data-label-one' ) : run.getAttribute( 'data-label' ).replace( '%d', String( count ) );
		}

		function renderAreas( list ) {
			areas.textContent = '';

			list.forEach( function ( area ) {
				var row   = document.createElement( 'div' );
				var label = document.createElement( 'label' );
				var check = document.createElement( 'input' );
				var name  = document.createElement( 'span' );
				var sum   = document.createElement( 'span' );

				row.className   = 'undt-iarea undt-iarea--' + area.state;
				label.className = 'undt-iarea__label';
				check.type      = 'checkbox';
				check.value     = area.key;
				check.checked   = 'change' === area.state;
				check.disabled  = 'change' !== area.state;
				name.className  = 'undt-iarea__name';
				name.textContent = area.label;
				sum.className   = 'undt-iarea__sum';
				sum.textContent = area.summary;

				label.appendChild( check );
				label.appendChild( name );
				label.appendChild( sum );
				row.appendChild( label );

				if ( area.diff && area.diff.length ) {
					var diff = document.createElement( 'div' );

					diff.className = 'undt-iarea__diff';

					area.diff.forEach( function ( line ) {
						var item = document.createElement( 'div' );
						var key  = document.createElement( 'span' );

						item.className  = 'undt-iarea__line';
						key.className   = 'undt-iarea__key';
						key.textContent = line.k;
						item.appendChild( key );

						if ( line.old || line.new ) {
							var before = document.createElement( 'span' );
							var arrow  = document.createElement( 'span' );
							var after  = document.createElement( 'span' );

							before.className   = 'undt-iarea__old';
							before.textContent = line.old;
							arrow.textContent  = '→';
							arrow.setAttribute( 'aria-hidden', 'true' );
							after.className    = 'undt-iarea__new';
							after.textContent  = line.new;
							item.appendChild( before );
							item.appendChild( arrow );
							item.appendChild( after );
						}

						diff.appendChild( item );
					} );

					row.appendChild( diff );
				}

				areas.appendChild( row );
			} );

			areas.hidden  = false;
			runbox.hidden = false;
			syncRun();
		}

		function choose( picked ) {
			if ( ! picked ) {
				return;
			}

			var data = new FormData();

			data.set( 'undt_file', picked );
			error.hidden = true;
			drop.setAttribute( 'aria-busy', 'true' );

			post( 'undt_import_preview', data ).then( function ( json ) {
				drop.removeAttribute( 'aria-busy' );

				if ( ! json.success ) {
					file.value = '';
					showError( json.data && json.data.message );
					return;
				}

				var meta = [];

				if ( json.data.source ) {
					meta.push( ( l10n.importFrom || '%s' ).replace( '%s', json.data.source ) );
				}

				if ( json.data.exported ) {
					meta.push( json.data.exported );
				}

				box.querySelector( '[data-undt-import-name]' ).textContent = picked.name;
				box.querySelector( '[data-undt-import-meta]' ).textContent = meta.length ? '· ' + meta.join( ' · ' ) : '';
				drop.hidden   = true;
				chosen.hidden = false;
				renderAreas( json.data.areas || [] );
				step( 2 );
				announce( l10n.importReady || '' );
			} );
		}

		file.addEventListener( 'change', function () {
			choose( file.files && file.files[0] );
		} );

		[ 'dragenter', 'dragover' ].forEach( function ( type ) {
			drop.addEventListener( type, function ( event ) {
				event.preventDefault();
				drop.classList.add( 'is-over' );
			} );
		} );

		[ 'dragleave', 'drop' ].forEach( function ( type ) {
			drop.addEventListener( type, function () {
				drop.classList.remove( 'is-over' );
			} );
		} );

		drop.addEventListener( 'drop', function ( event ) {
			event.preventDefault();

			if ( event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0] ) {
				choose( event.dataTransfer.files[0] );
			}
		} );

		areas.addEventListener( 'change', syncRun );

		box.querySelector( '[data-undt-import-other]' ).addEventListener( 'click', function () {
			reset();
			file.click();
		} );

		// Ohne JavaScript schickt das Formular die Datei ab; mit gehen die Schritte.
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
		} );

		run.addEventListener( 'click', function () {
			var data = new FormData();

			selected().forEach( function ( area ) {
				data.append( 'areas[]', area );
			} );

			run.disabled = true;
			run.setAttribute( 'aria-busy', 'true' );

			post( 'undt_import_run', data ).then( function ( json ) {
				run.removeAttribute( 'aria-busy' );

				if ( ! json.success ) {
					run.disabled = false;
					showError( json.data && json.data.message );
					return;
				}

				reset();
				done.querySelector( '[data-undt-import-title]' ).textContent = json.data.title;

				// Verworfene Verknuepfungen nennen, mit dem Weg zu den Rechtsseiten.
				var dropped = done.querySelector( '[data-undt-import-dropped]' );
				var relink  = done.querySelector( '[data-undt-import-relink]' );

				if ( dropped ) {
					dropped.hidden = ! json.data.dropped;
					dropped.querySelector( '[data-undt-import-summary]' ).textContent = json.data.summary;
					relink.hidden = ! json.data.relink;
					relink.setAttribute( 'href', json.data.relink || '#' );
				}

				done.hidden = false;
				done.querySelector( '[data-undt-import-undo]' ).focus();
				announce( json.data.title );
			} );
		} );

		done.querySelector( '[data-undt-import-undo]' ).addEventListener( 'click', function ( event ) {
			var undo = event.currentTarget;

			undo.disabled = true;
			undo.setAttribute( 'aria-busy', 'true' );

			post( 'undt_import_undo', new FormData() ).then( function ( json ) {
				undo.disabled = false;
				undo.removeAttribute( 'aria-busy' );

				if ( ! json.success ) {
					showError( json.data && json.data.message );
					return;
				}

				done.hidden = true;
				announce( json.data.message );
				file.focus();
			} );
		} );
	}

	/*
	 * Firma › Beruf: die Auswahl der gesetzlichen Bezeichnungen schreibt ins
	 * Textfeld, das gespeichert wird. „Andere Bezeichnung …“ zeigt das
	 * Textfeld. Darunter Kammer und Regelungen uebernehmen (quick:chamber)
	 * und die erlaubnispflichtige Taetigkeit.
	 */
	var PROFESSION_OTHER = '__other';

	function initProfession() {
		all( '[data-undt-profession-text][data-undt-hide]' ).forEach( function ( text ) {
			text.hidden = true;
		} );

		document.addEventListener( 'change', function ( event ) {
			var target = event.target;

			if ( ! target.matches ) {
				return;
			}

			if ( target.matches( '[data-undt-profession-choice]' ) ) {
				var text = target.closest( '[data-undt-profession]' ).querySelector( '[data-undt-profession-text]' );

				if ( PROFESSION_OTHER === target.value ) {
					text.hidden = false;
					text.focus();
					return;
				}

				text.hidden = true;
				text.value  = target.value;
				changed( text );
				return;
			}

			if ( target.matches( '[data-undt-chamber-choice]' ) ) {
				var apply = target.closest( '.undt-csuggest' ).querySelector( '[data-undt-chamber-apply]' );

				apply.disabled = '' === target.value;
				apply.setAttribute( 'data-undt-chamber-apply', target.value );
				return;
			}

			if ( target.matches( '[data-undt-permit-choice]' ) ) {
				var box    = target.closest( '[data-undt-permit]' );
				var hint   = box.querySelector( '[data-undt-permit-hint]' );
				var option = target.options[ target.selectedIndex ];

				hint.hidden      = '' === target.value;
				hint.textContent = '' === target.value ? '' : hint.getAttribute( 'data-template' ).replace( '%s', option.getAttribute( 'data-authority' ) );
				box.querySelector( '[data-undt-permit-apply]' ).disabled = '' === target.value || ! option.getAttribute( 'data-note' );
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! event.target.closest ) {
				return;
			}

			var chamber = event.target.closest( '[data-undt-chamber-apply]' );

			if ( chamber && ! chamber.disabled ) {
				chamber.disabled = true;
				chamber.setAttribute( 'aria-busy', 'true' );

				quickSave( 'chamber', chamber.getAttribute( 'data-undt-chamber-apply' ), function ( ok, data ) {
					if ( ! ok || ! data || ! data.values ) {
						chamber.disabled = false;
						chamber.removeAttribute( 'aria-busy' );
						announce( l10n.saveFailed );
						return;
					}

					Object.keys( data.values ).forEach( function ( key ) {
						var field = document.getElementById( 'undt-' + key );

						if ( field ) {
							field.value = data.values[ key ];
						}
					} );

					var form = document.querySelector( 'form[data-undt-autosave="company"]' );

					if ( form ) {
						refreshMissing( form );
					}

					announce( l10n.chamberApplied || l10n.saved );
				} );

				return;
			}

			var permit = event.target.closest( '[data-undt-permit-apply]' );

			if ( permit ) {
				var select = permit.closest( '[data-undt-permit]' ).querySelector( '[data-undt-permit-choice]' );
				var note   = select.options[ select.selectedIndex ].getAttribute( 'data-note' );
				var field  = document.getElementById( 'undt-permit_note' );

				if ( field && note ) {
					field.value = note;
					changed( field );
					announce( l10n.saved );
					field.focus();
				}

				return;
			}

			// Suchmaschinen: die Art aus dem Beruf uebernehmen.
			var type = event.target.closest( '[data-undt-type-apply]' );

			if ( type ) {
				var choice = document.getElementById( 'undt-seo-type' );

				if ( choice ) {
					choice.value = type.getAttribute( 'data-undt-type-apply' );
					choice.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					choice.focus();
				}

				var hintBox = type.closest( '[data-undt-type-hint]' );

				if ( hintBox ) {
					hintBox.parentNode.removeChild( hintBox );
				}
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
		initAutosave();
		initQuick();
		initMenu();
		initActions();
		initTodoMore();
		initJump();
		initLegalForm();
		initSuggest();
		initCompany();
		initBlocks();
		initEmbedSearch();
		initPages();
		initHelp();
		initLinks();
		initIconSelects();
		initHoursPage();
		initSocialPage();
		initBannerPage();
		initPricesPage();
		initFaqPage();
		initSeoPage();
		initImport();
		initProfession();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
