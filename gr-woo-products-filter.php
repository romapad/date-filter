<?php 
/*
Plugin Name: Woocommerce Date Range Filter
Plugin URI: http://
Description: 
Version: 0.1
Author: Romapad
Author URI: http://
Text Domain: gr-woo-products-filter
Domain Path: /languages

*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! class_exists( 'GR_Date_Filter' ) ) :
class GR_Date_Filter {
    
	/**
	 * Basename of the plugin, retrieved through plugin_basename function
	 *
	 * @since 1.0
	 * @access private
	 * @var string
	 */
	private $plugin_basename;
    
	/**
	* Construct the plugin.
	*/
	public function __construct() {
        
		$this->plugin_basename = plugin_basename( __FILE__ );

		$this->define_constants();        
        
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}
	/**
	* Initialize the plugin.
	*/
	public function init() {
		// Checks if WooCommerce is installed.
		if ( class_exists( 'WC_Query' ) && class_exists( 'WC_Widget' ) ) {
			// Include our class.
			include_once 'includes/gr-class-wc-query.php';
            include_once 'includes/gr-class-date-filter.php';
            include_once 'includes/gr-class-layered-nav-filters.php';
			// Register the integration.
            function gr_register_widgets() {
            	register_widget( 'GR_Widget_Date_Filter' );
                register_widget( 'GR_Widget_Layered_Nav_Filters' );    
            }
            add_action( 'widgets_init', 'gr_register_widgets' );             
		} else {
			// throw an admin error if you like
		}
	}
    
	public function define_constants() {

		define( 'GR_DATE_FILTER_URL', plugin_dir_url( __FILE__ ) );
		define( 'GR_DATE_FILTER_DIR', plugin_dir_path( __FILE__ ) );
	}
         

}
$GR_Date_Filter = new GR_Date_Filter( __FILE__ );
endif;
