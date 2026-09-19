/**
 * Block-Bindungen im Editor.
 *
 * Die Quelle „unternehmensdaten/feld“ ist in PHP angemeldet, damit die Werte
 * auf der Website erscheinen. Der Editor fragt dagegen im Browser nach, und
 * ohne Antwort stuende dort der Platzhalter aus der Block-Auszeichnung. Diese
 * Datei reicht die Werte nach, die das Backend mitgeschickt hat.
 *
 * Geaendert werden kann im Editor nichts: die Angaben stehen unter
 * Unternehmensdaten, nicht im Beitrag. WordPress zeigt gebundene Bloecke
 * deshalb von sich aus als gesperrt an.
 */
( function () {
	'use strict';

	var blocks = window.wp && window.wp.blocks;
	var data = window.undtBindings;

	// registerBlockBindingsSource gibt es erst ab WordPress 6.7. Darunter bleibt
	// es beim Platzhalter im Editor, die Ausgabe stimmt trotzdem.
	if ( ! blocks || ! blocks.registerBlockBindingsSource || ! data ) {
		return;
	}

	/**
	 * Der Wert eines Schluessels, ersatzweise seine Beschriftung.
	 *
	 * Ein leeres Feld wuerde einen leeren Block ergeben, der sich im Editor kaum
	 * noch treffen laesst. Dort steht dann die Beschriftung, so wie WordPress es
	 * bei leeren Feldern auch macht.
	 *
	 * @param {string} key Schluessel.
	 * @return {string} Wert.
	 */
	function valueOf( key ) {
		var value = data.values[ key ];

		if ( value ) {
			return value;
		}

		return data.labels[ key ] || '';
	}

	blocks.registerBlockBindingsSource( {
		name: data.source,
		label: data.label,
		getValues: function ( args ) {
			var bindings = ( args && args.bindings ) || {};
			var values = {};

			Object.keys( bindings ).forEach( function ( attribute ) {
				var binding = bindings[ attribute ] || {};
				var key = ( binding.args && binding.args.key ) || '';

				values[ attribute ] = valueOf( key );
			} );

			return values;
		},
	} );
}() );
