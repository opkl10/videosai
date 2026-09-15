/* global jQuery, wp, socialHubAdmin, ajaxurl */
( function ( $ ) {
	'use strict';

	var settings = window.socialHubAdmin || {};

	function post( action, data ) {
		return $.post(
			window.ajaxurl,
			$.extend( { action: action, nonce: settings.nonce }, data )
		);
	}

	function message( response ) {
		if ( response && response.data && response.data.message ) {
			return response.data.message;
		}

		return settings.genericError;
	}

	function testConnection( event ) {
		var button = $( event.currentTarget );
		var network = button.data( 'social-hub-test' );
		var result = $( '[data-social-hub-test-result="' + network + '"]' );

		button.prop( 'disabled', true );
		result.removeClass( 'is-success is-error' ).text( settings.testing );

		post( 'social_hub_test_connection', { network: network } )
			.done( function ( response ) {
				if ( response && response.success ) {
					result.addClass( 'is-success' ).text( message( response ) );
				} else {
					result.addClass( 'is-error' ).text( message( response ) );
				}
			} )
			.fail( function () {
				result.addClass( 'is-error' ).text( settings.genericError );
			} )
			.always( function () {
				button.prop( 'disabled', false );
			} );
	}

	function shareNow( event ) {
		var button = $( event.currentTarget );
		var postId = button.data( 'social-hub-share-now' );
		var result = $( '[data-social-hub-share-result]' );
		var status = $( '[data-social-hub-status]' );

		button.prop( 'disabled', true );
		result.removeClass( 'is-success is-error' ).text( settings.sharing );

		post( 'social_hub_share_now', { post_id: postId } )
			.done( function ( response ) {
				if ( response && response.success ) {
					result.addClass( 'is-success' ).text( message( response ) );

					if ( response.data.html ) {
						status.html( response.data.html );
					}
				} else {
					result.addClass( 'is-error' ).text( message( response ) );
				}
			} )
			.fail( function () {
				result.addClass( 'is-error' ).text( settings.genericError );
			} )
			.always( function () {
				button.prop( 'disabled', false );
			} );
	}

	function selectImage( event ) {
		var wrapper = $( event.currentTarget ).closest( '[data-social-hub-media]' );
		var frame;

		if ( ! wp || ! wp.media ) {
			return;
		}

		frame = wp.media( {
			title: settings.mediaTitle,
			button: { text: settings.mediaButton },
			library: { type: 'image' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var url = attachment.url;

			if ( attachment.sizes && attachment.sizes.medium ) {
				url = attachment.sizes.medium.url;
			}

			wrapper.find( '[data-social-hub-media-value]' ).val( attachment.id );
			wrapper.find( '[data-social-hub-media-preview]' ).html( $( '<img>', { src: url, alt: '' } ) );
		} );

		frame.open();
	}

	function clearImage( event ) {
		var wrapper = $( event.currentTarget ).closest( '[data-social-hub-media]' );

		wrapper.find( '[data-social-hub-media-value]' ).val( '0' );
		wrapper.find( '[data-social-hub-media-preview]' ).empty();
	}

	$( function () {
		$( document )
			.on( 'click', '[data-social-hub-test]', testConnection )
			.on( 'click', '[data-social-hub-share-now]', shareNow )
			.on( 'click', '[data-social-hub-media-select]', selectImage )
			.on( 'click', '[data-social-hub-media-clear]', clearImage );
	} );
} )( jQuery );
