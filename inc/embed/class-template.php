<?php
namespace epiphyt\Embed_Privacy\embed;

use epiphyt\Embed_Privacy\Embed_Privacy;
use epiphyt\Embed_Privacy\Provider;

/**
 * Embed Privacy template related functionality.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Template {
	/**
	 * Get an overlay template.
	 * 
	 * @param	string	$provider The embed provider
	 * @param	string	$provider_name The embed provider without spaces and in lowercase
	 * @param	string	$output The output before replacing it
	 * @param	array	$args Additional arguments
	 * @return	string The overlay template
	 */
	public static function get( $provider, $provider_name, $output, $args = [] ) {
		/**
		 * Filter the embed provider name.
		 * 
		 * @since	1.10.0
		 * 
		 * @param	string	$provider_name Embed provider name
		 * @param	string	$provider_title Embed provider title
		 * @param	array	$args Additional arguments
		 * @param	string	$output Output before replacing it
		 */
		$provider_name = \apply_filters( 'embed_privacy_provider_name', $provider_name, $provider, $args, $output );
		
		/**
		 * Filter the overlay arguments.
		 * 
		 * @since	1.9.0
		 * 
		 * @param	array	$args Template arguments
		 * @param	string	$provider The embed provider
		 * @param	string	$provider_name The embed provider without spaces and in lowercase
		 * @param	string	$output The output before replacing it
		 */
		$args = (array) \apply_filters( 'embed_privacy_overlay_args', $args, $provider, $provider_name, $output );
		
		if ( ! empty( $args['post_id'] ) ) {
			$embed_post = \get_post( $args['post_id'] );
			
			if ( Provider::is_disabled( $embed_post ) ) {
				return $output;
			}
		}
		else {
			$embed_post = null;
		}
		
		if ( $provider_name === 'youtube' ) {
			$output = \str_replace( 'youtube.com', 'youtube-nocookie.com', $output );
		}
		
		$embed_class = 'embed-' . ( ! empty( $provider_name ) ? $provider_name : 'default' );
		$embed_classes = $embed_class;
		$style = new Style( $provider_name, $embed_post, $args );
		
		if ( ! empty( $args['align'] ) ) {
			$embed_classes .= ' align' . $args['align'];
		}
		
		if ( ! empty( $args['assets'] ) ) {
			$output = Assets::get_static( $args['assets'], $provider_name ) . $output;
		}
		
		$embed_md5 = \md5( $output . \wp_generate_uuid4() );
		
		/**
		 * Fires before the overlay output is generated.
		 * 
		 * @since	1.10.0
		 * 
		 * @param	string								$provider The embed provider
		 * @param	string								$provider_name The embed provider without spaces and in lowercase
		 * @param	\epiphyt\Embed_Privacy\embed\Style	$style The overlay style object
		 * @param	array								$args Additional arguments
		 */
		\do_action( 'embed_privacy_before_overlay_output', $provider, $provider_name, $style, $args );
		
		\ob_start();
		?>
		<p>
		<?php
		if ( ! empty( $provider ) ) {
			if ( $embed_post ) {
				$allowed_tags = [
					'a' => [
						'href',
						'target',
					],
				];
				echo $embed_post->post_content . \PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$privacy_policy = \get_post_meta( $embed_post->ID, 'privacy_policy_url', true );
				
				if ( $privacy_policy ) {
					?>
					<br>
					<?php
					/* translators: 1: embed provider, 2: opening <a> tag to the privacy policy, 3: closing </a> */
					\printf( \wp_kses( \__( 'Learn more in %1$s’s %2$sprivacy policy%3$s.', 'embed-privacy' ), $allowed_tags ), \esc_html( $provider ), '<a href="' . \esc_url( $privacy_policy ) . '" target="_blank">', '</a>' );
				}
			}
			else {
				/* translators: embed provider */
				\printf( \esc_html__( 'Click here to display content from %s', 'embed-privacy' ), \esc_html( $provider ) );
			}
		}
		else {
			\esc_html_e( 'Click here to display content from an external service.', 'embed-privacy' );
		}
		?>
		</p>
		<?php
		$checkbox_id = 'embed-privacy-store-' . $provider_name . '-' . $embed_md5;
		
		if ( $provider_name !== 'default' ) :
		?>
		<p class="embed-privacy-input-wrapper">
			<input id="<?php echo \esc_attr( $checkbox_id ); ?>" type="checkbox" value="1" class="embed-privacy-input" data-embed-provider="<?php echo \esc_attr( $provider_name ); ?>">
			<label for="<?php echo \esc_attr( $checkbox_id ); ?>" class="embed-privacy-label" data-embed-provider="<?php echo \esc_attr( $provider_name ); ?>">
				<?php
				/* translators: the embed provider */
				\printf( \esc_html__( 'Always display content from %s', 'embed-privacy' ), \esc_html( $provider ) );
				?>
			</label>
		</p>
		<?php
		endif;
		
		$content = \ob_get_clean();
		
		/**
		 * Filter the content of the embed overlay.
		 * 
		 * @param	string		$content The content
		 * @param	string		$provider The embed provider of this embed
		 */
		$content = \apply_filters( 'embed_privacy_content', $content, $provider );
		
		\ob_start();
		
		$footer_content = '';
		
		if ( ! empty( $args['embed_url'] ) ) {
			$footer_content = '<div class="embed-privacy-footer">';
			
			if ( ! \get_option( 'embed_privacy_disable_link' ) ) {
				$footer_content .= '<span class="embed-privacy-url"><a href="' . \esc_url( $args['embed_url'] ) . '">';
				$footer_content .= \sprintf(
				/* translators: content name or 'content' */
					\esc_html__( 'Open "%s" directly', 'embed-privacy' ),
					! empty( $args['embed_title'] ) ? $args['embed_title'] : \__( 'content', 'embed-privacy' )
				);
				$footer_content .= '</a></span>';
			}
			
			$footer_content .= '</div>' . \PHP_EOL;
			
			/**
			 * Filter the overlay footer.
			 * 
			 * @param	string	$footer_content The footer content
			 */
			$footer_content = \apply_filters( 'embed_privacy_overlay_footer', $footer_content );
		}
		
		$container_style = $style->get( 'container' );
		$logo_style = $style->get( 'logo' );
		?>
		<div class="embed-privacy-container is-disabled <?php echo \esc_attr( $embed_classes ); ?>" data-embed-id="oembed_<?php echo \esc_attr( $embed_md5 ); ?>" data-embed-provider="<?php echo \esc_attr( $provider_name ); ?>"<?php echo ! empty( $container_style ) ? ' style="' . \esc_attr( $container_style ) . '"' : ''; ?>>
			<?php
			/* translators: embed provider */
			$button_text = \sprintf( \__( 'Display content from %s', 'embed-privacy' ), \esc_html( $provider ) );
			
			if ( ! empty( $args['embed_title'] ) ) {
				/* translators: 1: embed title, 2: embed provider */
				$button_text = \sprintf( \__( 'Display "%1$s" from %2$s', 'embed-privacy' ), $args['embed_title'], \esc_html( $provider ) );
			}
			?>
			<button class="embed-privacy-enable screen-reader-text"><?php echo \esc_html( $button_text ); ?></button>
			
			<div class="embed-privacy-overlay">
				<div class="embed-privacy-inner">
					<?php
					echo ( ! empty( $logo_style ) ? '<div class="embed-privacy-logo" style="' . \esc_attr( $logo_style ) . '"></div>' . \PHP_EOL : '' );
					echo $content . \PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</div>
				
				<?php echo $footer_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			
			<div class="embed-privacy-content">
				<script>var _oembed_<?php echo $embed_md5; ?> = '<?php echo \addslashes( \wp_json_encode( [ 'embed' => \htmlentities( \preg_replace( '/\s+/S', ' ', $output ) ) ] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>';</script>
			</div>
		</div>
		<?php
		/**
		 * Filter the complete markup of the embed.
		 * 
		 * @param	string	$markup The markup
		 * @param	string	$provider The embed provider of this embed
		 */
		$markup = \apply_filters( 'embed_privacy_markup', \ob_get_clean(), $provider );
		
		Embed_Privacy::get_instance()->has_embed = true;
		
		if ( ! empty( $args['strip_newlines'] ) ) {
			$markup = \str_replace( \PHP_EOL, '', $markup );
		}
		
		return $markup;
	}
}
