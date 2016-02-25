<?php
/**
 * Contains the query functions for WooCommerce which alter the front-end post queries and loops
 *
 * @class 		WC_Query
 * @version		2.3.0
 * @package		WooCommerce/Classes
 * @category	Class
 * @author 		WooThemes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'GR_WC_Query' ) ) :

/**
 * WC_Query Class extension.
 */
class GR_WC_Query extends WC_Query {
    
	/**
	 * Constructor for the query class. Hooks in methods.
	 *
	 * @access public
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'date_filter_init' ) );

		if ( ! is_admin() ) {
			add_action( 'wp_loaded', array( $this, 'get_errors' ), 20 );
			add_filter( 'query_vars', array( $this, 'add_query_vars'), 0 );
			add_action( 'parse_request', array( $this, 'parse_request'), 0 );
			add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
			add_filter( 'the_posts', array( $this, 'the_posts' ), 11, 2 );
			add_action( 'wp', array( $this, 'remove_product_query' ) );
			add_action( 'wp', array( $this, 'remove_ordering_args' ) );
		}      
        
		$this->init_query_vars();
        
	} 
    
	/**
	 * Date filter Init.
	 */
	public function date_filter_init() {
		if ( apply_filters( 'woocommerce_is_date_filter_active', is_active_widget( false, false, 'woocommerce_date_filter', true ) ) && ! is_admin() ) {

			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_register_script( 'wc-jquery-ui-touchpunch', WC()->plugin_url() . '/assets/js/jquery-ui-touch-punch/jquery-ui-touch-punch' . $suffix . '.js', array( 'jquery-ui-slider' ), WC_VERSION, true );
			wp_register_script( 'wc-date-slider', GR_DATE_FILTER_URL . '/assets/js/date-slider' . $suffix . '.js', array( 'jquery-ui-slider', 'wc-jquery-ui-touchpunch' ), WC_VERSION, true );

			wp_localize_script( 'wc-date-slider', 'woocommerce_date_slider_params', array(
				'min_date'			=> isset( $_GET['min_date'] ) ? esc_attr( $_GET['min_date'] ) : '',
				'max_date'			=> isset( $_GET['max_date'] ) ? esc_attr( $_GET['max_date'] ) : ''
			) );            
            
			add_filter( 'loop_shop_post_in', array( $this, 'date_filter' ) );
            
        function date_filter_style_to_head () { ?>
<style>
.widget_date_filter .date_slider_amount .button:hover,
.widget_date_filter .ui-slider .ui-slider-range,
.woocommerce .widget_date_filter .ui-slider .ui-slider-range {
    background: #eb1b23 !important;
}
.widget .date_slider_wrapper,
.widget_date_filter .date_slider_amount {
    margin: 20px 0 0 0 !important;
}
.woocommerce .widget_date_filter .date_slider_wrapper .ui-slider {
    height: 6px;
    background: #d7d7d7 !important;
    border-radius: 1em !important;
    border: 0;
    position: relative;
    text-align: left;
    margin-left: .5em;
    margin-right: .5em;
    margin-bottom: 1em;	
}
.woocommerce .widget_date_filter .ui-slider .ui-slider-range {
    position: absolute !important;
    z-index: 1 !important;
    font-size: .7em !important;
    display: block !important;
    border: 0 !important;
    box-shadow: inset 0 0 0 0 rgba(0,0,0,0.5) !important;
    -webkit-box-shadow: inset 0 0 0 0 rgba(0,0,0,0.5) !important;
    -moz-box-shadow: inset 0 0 0 0 rgba(0,0,0,0.5) !important;
    border-radius: 0 !important;
    background-color: #a46497;
    top: 0;
    height: 100%;	
}
.woocommerce .widget_date_filter .ui-slider .ui-slider-handle {
    margin-left: -.5em;
    color: #f6f6f6;
    border: 1px solid #ccc !important;
    background: #717171 !important;
    width: 15px !important;
    height: 15px !important;
    cursor: pointer !important;
    outline: none !important;
    border-radius: 1em !important;
    -webkit-box-shadow: 0 1px 2px rgba(0,0,0,0.3), inset 0 0 0 5px rgba(255,255,255,0.9) !important;
    -moz-box-shadow: 0 1px 2px rgba(0,0,0,0.3), inset 0 0 0 5px rgba(255,255,255,0.9) !important;
    box-shadow: 0 1px 2px rgba(0,0,0,0.3), inset 0 0 0 5px rgba(255,255,255,0.9 !important);
    position: absolute !important;
    top: -6px !important;
    z-index: 2 !important;
    transition: none;
    -webkit-transition: none;
}
.woocommerce .widget_date_filter .date_slider_amount {
    text-align: right;
    line-height: 2.4;
    font-size: .8751em;
}
.widget_date_filter .date_slider_amount .button {
    float: right !important;
	padding: 10px 15px !important;
}
.widget_date_filter .date_label {
    text-align: left !important;
    padding: 5px 0;
}
.widget_date_filter .date_label {
    font-size: 0;
}    
.widget_date_filter .date_label span {
    font-size: 12px;
}
.widget_date_filter .date_label span:first-of-type:after {
    content: "-";
    display: inline-block;
    margin: 0 5px;
}     
</style>
        	
        <?php }      
        add_action('wp_print_styles', 'date_filter_style_to_head'); 
                    
            
            
		}
        
	}
    
	/**
	 * Date Filter post filter.
	 *
	 * @param array $filtered_posts
	 * @return array
	 */
	public function date_filter( $filtered_posts = array() ) {
		global $wpdb;

		if ( isset( $_GET['max_date'] ) || isset( $_GET['min_date'] ) ) {

			$matched_products = array();
			$mind              = isset( $_GET['min_date'] ) ? $_GET['min_date'] : 0;
			$maxd              = isset( $_GET['max_date'] ) ? $_GET['max_date'] : 999999999999999999;


				$matched_products_query = apply_filters( 'woocommerce_date_filter_results', $wpdb->get_results( $wpdb->prepare( "
					SELECT DISTINCT ID, post_date, post_parent, post_type FROM {$wpdb->posts}
					WHERE post_type IN ( 'product' )
					AND post_status = 'publish'
					AND (post_date + 0) BETWEEN %f AND %f
				", $mind, $maxd ), OBJECT_K ), $mind, $maxd );
            
            print_r($matched_products_query);
            
				if ( $matched_products_query ) {
					foreach ( $matched_products_query as $product ) {
						if ( $product->post_type == 'product' ) {
							$matched_products[] = $product->ID;
						}
						if ( $product->post_parent > 0 ) {
							$matched_products[] = $product->post_parent;
						}
					}
				}

			$matched_products = array_unique( $matched_products );

			// Filter the id's
			if ( 0 === sizeof( $filtered_posts ) ) {
				$filtered_posts = $matched_products;
			} else {
				$filtered_posts = array_intersect( $filtered_posts, $matched_products );
			}
			$filtered_posts[] = 0;
		} 

		return (array) $filtered_posts;
	}    
    
    
}

endif;
return new GR_WC_Query();