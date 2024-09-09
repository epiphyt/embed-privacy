<?php
namespace epiphyt\Embed_Privacy;

/**
 * Filter the output of widgets.
 * 
 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\handler\Widget instead
 * @see			https://github.com/philipnewcomer/widget-output-filters
 * @since		1.1.0
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 */
class Embed_Privacy_Widget_Output_Filter {
	/**
	 * @var		string The alternative option name of this filter
	 */
	public $alt_option_name = '';
	
	/**
	 * @var		string The ID of this filter
	 */
	public $id = '';
	
	/**
	 * @var		string The ID base of this filter
	 */
	public $id_base = 'embed_privacy_widget_output_filter';
	
	/**
	 * @var		string The name of this filter
	 */
	public $name = 'Embed Privacy';
	
	/**
	 * @var		string The option name of this filter
	 */
	public $option_name = 'widget_embed_privacy_widget_output_filter';
	
	/**
	 * @var		bool The updated value of this filter
	 */
	public $updated = false;
	
	/**
	 * @var		array The widget options of this filter
	 */
	public $widget_options = [];
	
	/**
	 * @var		\epiphyt\Embed_Privacy\Embed_Privacy_Widget_Output_Filter
	 */
	private static $instance;
	
	/**
	 * Return the single instance of this class.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\handler\Widget::get_instance() instead
	 * 
	 * @return	\epiphyt\Embed_Privacy\Embed_Privacy_Widget_Output_Filter The single instance of this class
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	/**
	 * Initialize the functionality by registering actions and filters.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\handler\Widget instead
	 */
	private function __construct() {
		\add_filter( 'dynamic_sidebar_params', [ $this, 'filter_dynamic_sidebar_params' ], \PHP_INT_MAX );
	}
	
	/**
	 * Replace the widget's display callback with the Dynamic Sidebar Params display callback, storing the original callback for use later.
	 * The $sidebar_params variable is not modified; it is only used to get the current widget's ID.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\handler\Widget::filter_dynamic_sidebar_params() instead
	 * 
	 * @param	array	$sidebar_params The sidebar parameters
	 * @return	array The sidebar parameters
	 */
	public function filter_dynamic_sidebar_params( $sidebar_params ) {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\handler\Widget::filter_dynamic_sidebar_params()'
			),
			'1.10.0'
		);
		
		if ( \is_admin() ) {
			return $sidebar_params;
		}
		
		global $wp_registered_widgets;
		$current_widget_id = $sidebar_params[0]['widget_id'];
		
		$wp_registered_widgets[ $current_widget_id ]['original_callback'] = $wp_registered_widgets[ $current_widget_id ]['callback']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_registered_widgets[ $current_widget_id ]['callback'] = [ $this, 'display_widget' ]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		
		return $sidebar_params;
	}
	
	/**
	 * Execute the widget's original callback function, filtering its output.
	 * 
	 * @deprecated	1.10.0 Use epiphyt\Embed_Privacy\handler\Widget::display_widget() instead
	 */
	public function display_widget() {
		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: alternative method */
				\esc_html__( 'Use %s instead', 'embed-privacy' ),
				'epiphyt\Embed_Privacy\handler\Widget::display_widget()'
			),
			'1.10.0'
		);
		
		global $wp_registered_widgets;
		$original_callback_params = \func_get_args();
		
		$widget_id = isset( $original_callback_params[0]['widget_id'] ) ? $original_callback_params[0]['widget_id'] : null;
		$original_callback = $wp_registered_widgets[ $widget_id ]['original_callback'];
		
		$wp_registered_widgets[ $widget_id ]['callback'] = $original_callback; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		
		$sidebar_id = isset( $original_callback_params[0]['id'] ) ? $original_callback_params[0]['id'] : null;
		
		if ( \is_callable( $original_callback ) ) {
			\ob_start();
			\call_user_func_array( $original_callback, $original_callback_params ); // phpcs:ignore NeutronStandard.Functions.DisallowCallUserFunc.CallUserFunc
			$widget_output = \ob_get_clean();
			
			/**
			 * Filter the widget's output.
			 * 
			 * @param	string	$widget_output The widget's output
			 * @param	string	$widget_id The widget's full ID
			 * @param	string	$sidebar_id The current sidebar ID
			 */
			echo \apply_filters( 'embed_privacy_widget_output', $widget_output, $widget_id, $sidebar_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}
