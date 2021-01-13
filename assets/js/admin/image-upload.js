/**
 * Add support for image uploads.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 */
jQuery( document ).ready( function( $ ) {
	// remove image
	$( document ).on( 'click', '.embed-privacy-remove-image', function( event ) {
		event.preventDefault();
		
		var image_item = $( this ).closest( '.embed-privacy-image-item' );
		
		image_item.find( '.embed-privacy-image-input-container, .embed-privacy-image-set-input-container' ).removeClass( 'embed-privacy-hidden' );
		image_item.find( '.embed-privacy-image-container, .embed-privacy-image-set-container' ).addClass( 'embed-privacy-hidden' );
		
		// reset value
		image_item.find( '.embed-privacy-image-input' ).val( '' );
		image_item.find( '.embed-privacy-upload-input' ).val( '' );
		// reset type
		image_item.find( '.embed-privacy-upload-input' ).attr( 'type', 'file' );
	} );
	
	// upload functionality single image
	$( document ).on( 'click', '.embed-privacy-image-upload', function( event ) {
		event.preventDefault();
		
		var container = $( this ).closest( '.embed-privacy-image-item' ).find( '.embed-privacy-image-container' );
		var id_field = $( this ).closest( '.embed-privacy-image-item' ).find( '.embed-privacy-image-input' );
		var meta_image_frame = wp.media.frames.meta_image_frame = wp.media();
		var upload_field = $( this );
		
		meta_image_frame.on( 'select', function() {
			var media_attachment = meta_image_frame.state().get('selection').first().toJSON();
			
			// clear the image container
			container.find( 'img' ).remove();
			// add uploaded/selected image
			container.prepend( '<img src="' + ( typeof media_attachment.sizes !== 'undefined' ? typeof media_attachment.sizes.thumbnail !== 'undefined' ? media_attachment.sizes.thumbnail.url : media_attachment.sizes.full.url : media_attachment.url ) + '" alt="">' );
			// don't hide the container anymore
			container.removeClass( 'embed-privacy-hidden' );
			// store attachment ID
			id_field.val( media_attachment.id );
			
			// hide upload field
			upload_field.parent().addClass( 'embed-privacy-hidden' );
		} );
		
		meta_image_frame.open();
	} );
} );
