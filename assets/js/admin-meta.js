( function ( $ ) {
	'use strict';

	$( document ).on( 'click', '.lp-service-media-field__select', function ( e ) {
		e.preventDefault();
		var $field = $( this ).closest( '.lp-service-media-field' );
		var frame = wp.media( {
			title: '画像を選択',
			multiple: false,
			library: { type: 'image' },
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			$field.find( '.lp-service-media-field__input' ).val( attachment.id );
			$field
				.find( '.lp-service-media-field__preview img' )
				.attr( 'src', attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url )
				.show();
			$field.find( '.lp-service-media-field__remove' ).show();
		} );

		frame.open();
	} );

	$( document ).on( 'click', '.lp-service-media-field__remove', function ( e ) {
		e.preventDefault();
		var $field = $( this ).closest( '.lp-service-media-field' );
		$field.find( '.lp-service-media-field__input' ).val( '' );
		$field.find( '.lp-service-media-field__preview img' ).hide().attr( 'src', '' );
		$( this ).hide();
	} );
} )( jQuery );
