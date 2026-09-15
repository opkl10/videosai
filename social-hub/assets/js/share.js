/* global socialHubShare */
( function () {
	'use strict';

	var settings = window.socialHubShare || {};

	function openPopup( event ) {
		var link = event.currentTarget;
		var width = settings.popupWidth || 620;
		var height = settings.popupHeight || 640;
		var left = window.screenX + Math.max( 0, ( window.outerWidth - width ) / 2 );
		var top = window.screenY + Math.max( 0, ( window.outerHeight - height ) / 2 );
		var features = 'popup=yes,width=' + width + ',height=' + height + ',left=' + left + ',top=' + top;
		var popup = window.open( link.href, 'social-hub-share', features );

		if ( popup ) {
			event.preventDefault();
			popup.focus();
		}
	}

	function flash( button, message ) {
		var label = button.querySelector( '.social-hub-share__label' );
		var original;

		button.classList.add( 'is-copied' );

		if ( label ) {
			original = label.textContent;
			label.textContent = message;
		}

		window.setTimeout( function () {
			button.classList.remove( 'is-copied' );

			if ( label ) {
				label.textContent = original;
			}
		}, 2000 );
	}

	function legacyCopy( value ) {
		var field = document.createElement( 'textarea' );
		var copied = false;

		field.value = value;
		field.setAttribute( 'readonly', 'readonly' );
		field.style.position = 'fixed';
		field.style.opacity = '0';
		document.body.appendChild( field );
		field.select();

		try {
			copied = document.execCommand( 'copy' );
		} catch ( error ) {
			copied = false;
		}

		document.body.removeChild( field );

		return copied;
	}

	function copyLink( event ) {
		var button = event.currentTarget;
		var value = button.getAttribute( 'data-social-hub-copy' );

		if ( ! value ) {
			return;
		}

		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( value ).then(
				function () {
					flash( button, settings.copied || 'Link copied' );
				},
				function () {
					flash( button, legacyCopy( value ) ? settings.copied : settings.copyFailed );
				}
			);

			return;
		}

		flash( button, legacyCopy( value ) ? settings.copied : settings.copyFailed );
	}

	function init() {
		document.querySelectorAll( '[data-social-hub-popup]' ).forEach( function ( link ) {
			link.addEventListener( 'click', openPopup );
		} );

		document.querySelectorAll( '[data-social-hub-copy]' ).forEach( function ( button ) {
			button.addEventListener( 'click', copyLink );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
