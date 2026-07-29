<?php
namespace epiphyt\Embed_Privacy\admin;

/**
 * Functionality for a single admin field.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Field {
	/**
	 * Get a field.
	 * 
	 * @param	array	$attributes Field attributes
	 * @param	int		$post_id Optional post ID
	 */
	public static function get( array $attributes, $post_id = 0 ) {
		$attributes = \wp_parse_args( $attributes, [
			'classes' => 'regular-text',
			'description' => '',
			'name' => '',
			'option_type' => 'meta',
			'single' => true,
			'title' => '',
			'type' => 'text',
			'validation' => '',
		] );
		
		if ( empty( $attributes['name'] ) || empty( $attributes['title'] ) ) {
			return;
		}
		
		if ( $attributes['option_type'] === 'meta' ) {
			$current_value = (string) \get_post_meta( $post_id, $attributes['name'], $attributes['single'] );
		}
		else {
			$current_value = (string) \get_option( $attributes['name'] );
		}
		
		switch ( $attributes['type'] ) {
			case 'checkbox':
			case 'radio':
				self::get_choice( $attributes, $current_value );
				break;
			default:
				self::get_text( $attributes, $current_value );
				break;
		}
	}
	
	/**
	 * Get the markup of a field description.
	 * 
	 * @since	1.13.1
	 * 
	 * @param	array	$attributes Field attributes
	 * @return	string The description markup or an empty string
	 */
	private static function get_description( array $attributes ) {
		if ( empty( $attributes['description'] ) ) {
			return '';
		}
		
		$validation = ! empty( $attributes['validation'] ) ? $attributes['validation'] : '';
		
		if ( empty( $validation ) ) {
			$description = \esc_html( $attributes['description'] );
		}
		else if ( $validation === 'allow-links' ) {
			$description = \wp_kses( $attributes['description'], [
				'a' => [
					'href' => true,
					'rel' => true,
					'target' => true,
				],
			] );
		}
		else {
			return '';
		}
		
		return '<p class="description" id="' . \esc_attr( self::get_description_id( $attributes ) ) . '">' . $description . '</p>';
	}
	
	/**
	 * Get the ID a field description is referenced by.
	 * 
	 * @since	1.13.1
	 * 
	 * @param	array	$attributes Field attributes
	 * @return	string The description ID
	 */
	private static function get_description_id( array $attributes ) {
		return $attributes['name'] . '-description';
	}
	
	/**
	 * Get the aria-describedby attribute of a field, if it has a description.
	 * 
	 * @since	1.13.1
	 * 
	 * @param	array	$attributes Field attributes
	 * @return	string The attribute or an empty string
	 */
	private static function get_described_by( array $attributes ) {
		if ( empty( self::get_description( $attributes ) ) ) {
			return '';
		}
		
		return ' aria-describedby="' . \esc_attr( self::get_description_id( $attributes ) ) . '"';
	}
	
	/**
	 * Get a choice field (checkbox or radio button).
	 * 
	 * @param	array	$attributes Field attributes
	 * @param	mixed	$current_value Current value
	 */
	public static function get_choice( array $attributes, $current_value ) {
		if ( empty( $attributes['value'] ) ) {
			$attributes['value'] = 'yes';
		}
		
		\ob_start();
		?>
		<label for="<?php echo \esc_attr( $attributes['name'] ); ?>"><input type="<?php echo \esc_attr( $attributes['type'] ); ?>" name="<?php echo \esc_attr( $attributes['name'] ); ?>" id="<?php echo \esc_attr( $attributes['name'] ); ?>" value="<?php echo \esc_attr( $attributes['value'] ); ?>" class="<?php echo \esc_attr( $attributes['classes'] ); ?>"<?php \checked( $current_value, $attributes['value'] ); ?><?php echo self::get_described_by( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>> <?php echo \esc_html( $attributes['title'] ); ?></label>
		<?php
		echo self::get_description( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$input = \ob_get_clean();
		
		if ( $attributes['option_type'] === 'option' ) {
			echo $input; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			
			return;
		}
		?>
		<tr>
			<th scope="row"></th>
			<td>
				<?php echo $input; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</td>
		</tr>
		<?php
	}
	
	/**
	 * Get an image field.
	 * 
	 * @param	int		$post_id Post ID
	 * @param	array	$attributes An array with attributes
	 */
	public static function get_image( $post_id, array $attributes ) {
		$attributes = \wp_parse_args( $attributes, [
			'classes' => '',
			'description' => '',
			'name' => '',
			'single' => true,
			'title' => '',
		] );
		
		if ( empty( $attributes['name'] ) || empty( $attributes['title'] ) ) {
			return;
		}
		
		$attributes['value'] = (string) \get_post_meta( $post_id, $attributes['name'], $attributes['single'] );
		$image = \wp_get_attachment_image( (int) $attributes['value'] );
		$label_id = $attributes['name'] . '-label';
		?>
		<tr>
			<th scope="row">
				<span id="<?php echo \esc_attr( $label_id ); ?>"><?php echo \esc_html( $attributes['title'] ); ?></span>
			</th>
			<td class="embed-privacy-image-item" role="group" aria-labelledby="<?php echo \esc_attr( $label_id ); ?>">
				<input type="hidden" name="<?php echo \esc_attr( $attributes['name'] ); ?>" value="<?php echo \esc_attr( $attributes['value'] ); ?>" class="embed-privacy-image-input">
				
				<div class="embed-privacy-image-input-container<?php echo ! empty( $attributes['value'] ) ? ' embed-privacy-hidden' : ''; ?>">
					<button type="button" class="button button-secondary embed-privacy-image-upload"<?php echo self::get_described_by( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php \esc_html_e( 'Upload or choose file', 'embed-privacy' ); ?></button>
				</div>
				
				<div class="embed-privacy-image-container<?php echo empty( $attributes['value'] ) ? ' embed-privacy-hidden' : ''; ?>">
					<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<button type="button" class="dashicons dashicons-no embed-privacy-icon embed-privacy-remove-image">
						<span class="screen-reader-text"><?= \esc_attr__( 'Remove image', 'embed-privacy' ); ?></span>
					</button>
				</div>
				
				<?php echo self::get_description( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				
				<p class="screen-reader-text embed-privacy-image-status" role="status"></p>
			</td>
		</tr>
		<?php
	}
	
	/**
	 * Get a text field.
	 * 
	 * @param	array	$attributes Field attributes
	 * @param	mixed	$current_value Current value
	 */
	public static function get_text( array $attributes, $current_value ) {
		\ob_start();
		?>
		<input type="<?php echo \esc_attr( $attributes['type'] ); ?>" name="<?php echo \esc_attr( $attributes['name'] ); ?>" id="<?php echo \esc_attr( $attributes['name'] ); ?>" value="<?php echo \esc_attr( $current_value ); ?>" class="<?php echo \esc_attr( $attributes['classes'] ); ?>"<?php echo self::get_described_by( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php
		echo self::get_description( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$input = \ob_get_clean();
		
		if ( $attributes['option_type'] === 'option' ) {
			// unlike a choice field, a text field has no label wrapping it, and
			// the settings section provides none either
			\printf(
				'<label for="%1$s">%2$s</label> ',
				\esc_attr( $attributes['name'] ),
				\esc_html( $attributes['title'] )
			);
			echo $input; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			
			return;
		}
		?>
		<tr>
			<th scope="row">
				<label for="<?php echo \esc_attr( $attributes['name'] ); ?>"><?php echo \esc_html( $attributes['title'] ); ?></label>
			</th>
			<td>
				<?php echo $input; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</td>
		</tr>
		<?php
	}
}
