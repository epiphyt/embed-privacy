<?php
namespace epiphyt\Embed_Privacy\embed;

use epiphyt\Embed_Privacy\data\Providers;
use epiphyt\Embed_Privacy\Embed_Privacy;

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
	 * @param	\epiphyt\Embed_Privacy\embed\Provider|string	$provider The embed provider
	 * @param	string	$output The output before replacing it
	 * @param	array	$attributes Additional attributes
	 * @return	string The overlay template
	 */
	public static function get( $provider, $output, $attributes = [] ) {
		if ( ! $provider instanceof Provider ) {
			\_doing_it_wrong(
				__METHOD__,
				\sprintf(
					/* translators: alternative method */
					\esc_html__( 'Providing a string as parameter %1$s is deprecated. Use an object of type %2$s instead.', 'embed-privacy' ),
					'$provider',
					'epiphyt\Embed_Privacy\embed\Provider'
				),
				'1.10.0'
			);
			
			$provider = Providers::get_instance()->get_by_name( Providers::sanitize_name( $provider ) );
		}
		
		/**
		 * Filter the overlay arguments.
		 * 
		 * @deprecated	1.10.0 Use embed_privacy_template_attributes instead
		 * @since		1.9.0
		 * 
		 * @param	array	$attributes Template arguments
		 * @param	string	$provider The embed provider
		 * @param	string	$provider_name The embed provider without spaces and in lowercase
		 * @param	string	$output The output before replacing it
		 */
		$attributes = (array) \apply_filters_deprecated(
			'embed_privacy_overlay_args',
			[
				$attributes,
				$provider,
				$provider->get_name(),
				$output,
			],
			'1.10.0',
			'embed_privacy_template_attributes'
		);
		
		/**
		 * Filter the template attributes.
		 * 
		 * @since	1.10.0
		 * 
		 * @param	array									$attributes Template attributes
		 * @param	\epiphyt\Embed_privacy\embed\Provider	$provider The embed provider
		 * @param	string									$output The output before replacing it
		 */
		$attributes = (array) \apply_filters( 'embed_privacy_template_attributes', $attributes, $provider, $output );
		
		if ( ! empty( $attributes['post_id'] ) ) {
			$embed_post = \get_post( $attributes['post_id'] );
			
			if ( Providers::is_disabled( $embed_post ) ) {
				return $output;
			}
		}
		else if ( ! empty( $attributes['provider'] ) ) {
			if ( $attributes['provider'] instanceof Provider && $attributes['provider']->is_disabled() ) {
				return $output;
			}
		}
		else {
			$embed_post = null;
		}
		
		if ( $provider->is( 'youtube' ) ) {
			$output = \str_replace( 'youtube.com', 'youtube-nocookie.com', $output );
		}
		
		$embed_class = 'embed-' . ( ! empty( $provider->get_name() ) ? $provider->get_name() : 'default' );
		$embed_classes = $embed_class;
		$style = new Style( $provider->get_name(), $embed_post, $attributes );
		
		if ( ! empty( $attributes['align'] ) ) {
			$embed_classes .= ' align' . $attributes['align'];
		}
		
		if ( ! empty( $attributes['assets'] ) ) {
			$output = Assets::get_static( $attributes['assets'], $provider->get_name() ) . $output;
		}
		
		$embed_md5 = \md5( $output . \wp_generate_uuid4() );
		$checkbox_id = 'embed-privacy-store-' . $provider->get_name() . '-' . $embed_md5;
		
		/**
		 * Fires before the template output is generated.
		 * 
		 * @since	1.10.0
		 * 
		 * @param	\epiphyt\Embed_Privacy\embed\Provider	$provider The embed provider
		 * @param	\epiphyt\Embed_Privacy\embed\Style		$style The overlay style object
		 * @param	array									$attributes Additional attributes
		 */
		\do_action( 'embed_privacy_before_template_output', $provider, $style, $attributes );
		
		\ob_start();
		?>
		<p>
		<?php
		if ( ! empty( $provider->get_name() ) ) {
			if ( $embed_post || ! empty( $provider->get_description() ) ) {
				$allowed_tags = [
					'a' => [
						'href',
						'target',
					],
				];
				
				if ( $embed_post ) {
					echo $embed_post->post_content . \PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					$privacy_policy = \get_post_meta( $embed_post->ID, 'privacy_policy_url', true );
				}
				else {
					echo $provider->get_description() . \PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					$privacy_policy = $provider->get_privacy_policy_url();
				}
				
				if ( $privacy_policy ) {
					?>
					<br>
					<?php
					/* translators: 1: embed provider, 2: opening <a> tag to the privacy policy, 3: closing </a> */
					\printf( \wp_kses( \__( 'Learn more in %1$s’s %2$sprivacy policy%3$s.', 'embed-privacy' ), $allowed_tags ), \esc_html( $provider->get_title() ), '<a href="' . \esc_url( $privacy_policy ) . '" target="_blank">', '</a>' );
				}
			}
			else {
				/* translators: embed provider */
				\printf( \esc_html__( 'Click here to display content from %s', 'embed-privacy' ), \esc_html( $provider->get_title() ) );
			}
		}
		else {
			\esc_html_e( 'Click here to display content from an external service.', 'embed-privacy' );
		}
		?>
		</p>
		<p class="embed-privacy-input-wrapper">
			<input id="<?php echo \esc_attr( $checkbox_id ); ?>" type="checkbox" value="1" class="embed-privacy-input" data-embed-provider="<?php echo \esc_attr( $provider->get_name() ); ?>">
			<label for="<?php echo \esc_attr( $checkbox_id ); ?>" class="embed-privacy-label" data-embed-provider="<?php echo \esc_attr( $provider->get_name() ); ?>">
				<?php
				/* translators: the embed provider */
				\printf( \esc_html__( 'Always display content from %s', 'embed-privacy' ), \esc_html( $provider->get_title() ) );
				?>
			</label>
		</p>
		<?php
		$content = \ob_get_clean();
		
		/**
		 * Filter the content of the embed overlay.
		 * 
		 * @deprecated	1.10.0 Use embed_privacy_template_content instead
		 * 
		 * @param	string		$content The content
		 * @param	string		$provider The embed provider of this embed
		 */
		$content = \apply_filters_deprecated(
			'embed_privacy_content',
			[
				$content,
				$provider->get_title(),
			],
			'1.10.0',
			'embed_privacy_template_content'
		);
		
		/**
		 * Filter the content of the embed overlay.
		 * 
		 * @since	1.10.0
		 * 
		 * @param	string		$content The content
		 * @param	string		$provider The embed provider of this embed
		 */
		$content = \apply_filters( 'embed_privacy_template_content', $content, $provider );
		
		\ob_start();
		
		$footer_content = '';
		
		if ( ! empty( $attributes['embed_url'] ) ) {
			$footer_content = '<div class="embed-privacy-footer">';
			
			if ( ! \get_option( 'embed_privacy_disable_link' ) ) {
				$footer_content .= '<span class="embed-privacy-url"><a href="' . \esc_url( $attributes['embed_url'] ) . '">';
				$footer_content .= \sprintf(
				/* translators: content name or 'content' */
					\esc_html__( 'Open "%s" directly', 'embed-privacy' ),
					! empty( $attributes['embed_title'] ) ? $attributes['embed_title'] : \__( 'content', 'embed-privacy' )
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
		<div class="embed-privacy-container is-disabled <?php echo \esc_attr( $embed_classes ); ?>" data-embed-id="oembed_<?php echo \esc_attr( $embed_md5 ); ?>" data-embed-provider="<?php echo \esc_attr( $provider->get_name() ); ?>"<?php echo ! empty( $container_style ) ? ' style="' . \esc_attr( $container_style ) . '"' : ''; ?>>
			<?php
			/* translators: embed provider */
			$button_text = \sprintf( \__( 'Display content from %s', 'embed-privacy' ), \esc_html( $provider->get_title() ) );
			
			if ( ! empty( $attributes['embed_title'] ) ) {
				/* translators: 1: embed title, 2: embed provider */
				$button_text = \sprintf( \__( 'Display "%1$s" from %2$s', 'embed-privacy' ), $attributes['embed_title'], \esc_html( $provider->get_title() ) );
			}
			?>
			<button class="embed-privacy-enable screen-reader-text"><?php echo \esc_html( $button_text ); ?></button>
			
			<div class="embed-privacy-overlay">
				<div class="embed-privacy-inner">
					<?php
					echo ! empty( $logo_style ) ? '<div class="embed-privacy-logo" style="' . \esc_attr( $logo_style ) . '"></div>' . \PHP_EOL : '';
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
		$markup = \ob_get_clean();
		
		/**
		 * Filter the complete markup of the embed.
		 * 
		 * @deprecated	1.10.0 Use embed_privacy_template_markup instead
		 * 
		 * @param	string	$markup The markup
		 * @param	string	$provider_name The embed provider name of this embed
		 */
		$markup = \apply_filters_deprecated(
			'embed_privacy_markup',
			[
				$markup,
				$provider->get_title(),
			],
			'1.10.0',
			'embed_privacy_template_markup'
		);
		
		/**
		 * Filter the complete markup of the embed.
		 * 
		 * @since	1.10.0
		 * 
		 * @param	string									$markup The markup
		 * @param	\epiphyt\Embed_Privacy\embed\Provider	$provider The embed provider of this embed
		 */
		$markup = \apply_filters( 'embed_privacy_template_markup', $markup, $provider );
		
		Embed_Privacy::get_instance()->has_embed = true;
		
		if ( ! empty( $attributes['strip_newlines'] ) ) {
			$markup = \str_replace( \PHP_EOL, '', $markup );
		}
		
		return $markup;
	}
}
