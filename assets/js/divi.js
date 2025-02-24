/**
 * Divi functionality
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 */
document.addEventListener( 'DOMContentLoaded', () => {
	// Generate responsive video wrapper for Embed Privacy container
	if ( $.fn.fitVids ) {
		$( '#main-content' ).fitVids( {
			customSelector: ".embed-privacy-container"
		} );
	}
	
	window.embed_privacy_et_window=$(window);
	window.embed_privacy_et_is_mobile_device=navigator.userAgent.match(/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/)!==null||'standalone'in window.navigator&&!window.navigator.standalone;
	window.embed_privacy_et_pb_get_current_window_mode = function() {
		var window_width = embed_privacy_et_window.width();
		var current_mode = 'desktop';
		if (window_width <= 980 && window_width > 767) {
			current_mode = 'tablet';
		} else if (window_width <= 767) {
			current_mode = 'phone';
		}
		return current_mode;
	}
	window.embed_privacy_et_pb_map_init = function(this_map_container) {
		if ('undefined' === typeof google || 'undefined' === typeof google.maps) {
			return;
		}
		var current_mode = embed_privacy_et_pb_get_current_window_mode();
		et_animation_breakpoint = current_mode;
		var suffix = current_mode !== 'desktop' ? '-'.concat(current_mode) : '';
		var prev_suffix = 'phone' === current_mode ? '-tablet' : '';
		var grayscale_value = this_map_container.attr('data-grayscale'.concat(suffix)) || 0;
		if (!grayscale_value) {
			grayscale_value = this_map_container.attr('data-grayscale'.concat(prev_suffix)) || this_map_container.attr('data-grayscale') || 0;
		}
		var this_map = this_map_container.children('.et_pb_map');
		var this_map_grayscale = grayscale_value;
		var is_draggable = embed_privacy_et_is_mobile_device && this_map.data('mobile-dragging') !== 'off' || !embed_privacy_et_is_mobile_device;
		var infowindow_active;
		if (this_map_grayscale !== 0) {
			this_map_grayscale = '-'.concat(this_map_grayscale.toString());
		}
		var data_center_lat = parseFloat(this_map.attr('data-center-lat')) || 0;
		var data_center_lng = parseFloat(this_map.attr('data-center-lng')) || 0;
		this_map_container.data('map', new google.maps.Map(this_map[0], {
			zoom: parseInt(this_map.attr('data-zoom')),
			center: new google.maps.LatLng(data_center_lat, data_center_lng),
			mapTypeId: google.maps.MapTypeId.ROADMAP,
			scrollwheel: 'on' == this_map.attr('data-mouse-wheel'),
			draggable: is_draggable,
			panControlOptions: {
				position: this_map_container.is('.et_beneath_transparent_nav') ? google.maps.ControlPosition.LEFT_BOTTOM : google.maps.ControlPosition.LEFT_TOP
			},
			zoomControlOptions: {
				position: this_map_container.is('.et_beneath_transparent_nav') ? google.maps.ControlPosition.LEFT_BOTTOM : google.maps.ControlPosition.LEFT_TOP
			},
			styles: [{
				stylers: [{
					saturation: parseInt(this_map_grayscale)
				}]
			}]
		}));
		this_map_container.find('.et_pb_map_pin').each(function() {
			var this_marker = $(this);
			var marker = new google.maps.Marker({
				position: new google.maps.LatLng(parseFloat(this_marker.attr('data-lat')), parseFloat(this_marker.attr('data-lng'))),
				map: this_map_container.data('map'),
				title: this_marker.attr('data-title'),
				icon: {
					url: ''.concat(et_pb_custom.builder_images_uri, '/marker.png'),
					size: new google.maps.Size(46, 43),
					anchor: new google.maps.Point(16, 43)
				},
				shape: {
					coord: [1, 1, 46, 43],
					type: 'rect'
				},
				anchorPoint: new google.maps.Point(0, -45)
			});
			if (this_marker.find('.infowindow').length) {
				var infowindow = new google.maps.InfoWindow({
					content: this_marker.html()
				});
				google.maps.event.addListener(this_map_container.data('map'), 'click', function() {
					infowindow.close();
				});
				google.maps.event.addListener(marker, 'click', function() {
					if (infowindow_active) {
						infowindow_active.close();
					}
					infowindow_active = infowindow;
					infowindow.open(this_map_container.data('map'), marker);
					this_marker.closest('.et_pb_module').trigger('mouseleave');
					setTimeout(function() {
						this_marker.closest('.et_pb_module').trigger('mouseenter');
					}, 1);
				});
			}
		});
	};
} );
