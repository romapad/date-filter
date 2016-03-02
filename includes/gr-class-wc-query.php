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
        add_action( 'init', array( $this, 'cat_layered_nav_init' ) );
        
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
	 * Category Layered Nav Init.
	 */
	public function cat_layered_nav_init() {   
		if ( apply_filters( 'woocommerce_is_date_filter_active', is_active_widget( false, false, 'woocommerce_date_filter', true ) ) && ! is_admin() ) {
            define( 'GR_PRODUCTS_CATEGORY', 'product_cat' ); 
            
        	/**
        	 * ob_add_categories_filter function
        	 *
        	 * Sets the global $chosen_attributes to include the Product Categories
        	 *
        	 * @param array $filtered_posts
        	 *
        	 * @return array $filtered_posts
        	 */
        	function ob_add_categories_filter( $filtered_posts ) {
        		global $_chosen_attributes;
        
        		$taxonomy        = wc_sanitize_taxonomy_name( GR_PRODUCTS_CATEGORY );
        		$name            = 'filter_' . GR_PRODUCTS_CATEGORY;
        		$query_type_name = 'query_type_' . GR_PRODUCTS_CATEGORY;
        
        		if ( ! empty( $_GET[ $name ] ) && taxonomy_exists( $taxonomy ) ) {
        
        			$_chosen_attributes[ $taxonomy ]['terms'] = explode( ',', $_GET[ $name ] );
        
        			if ( empty( $_GET[ $query_type_name ] ) || ! in_array( strtolower( $_GET[ $query_type_name ] ), array(
        					'and',
        					'or'
        				) )
        			) {
        				$_chosen_attributes[ $taxonomy ]['query_type'] = apply_filters( 'woocommerce_layered_nav_default_query_type', 'and' );
        			} else {
        				$_chosen_attributes[ $taxonomy ]['query_type'] = strtolower( $_GET[ $query_type_name ] );
        			}
        
        		}
        
        		return $filtered_posts;
        	}

	        add_filter( 'loop_shop_post_in', 'ob_add_categories_filter', 5, 1 );            
            
            
        }
        
    }
    
	/**
	 * Date filter Init.
	 */
	public function date_filter_init() {
		if ( apply_filters( 'woocommerce_is_date_filter_active', is_active_widget( false, false, 'woocommerce_date_filter', true ) ) && ! is_admin() ) {

			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_register_script( 'wc-jquery-ui-touchpunch', WC()->plugin_url() . '/assets/js/jquery-ui-touch-punch/jquery-ui-touch-punch' . $suffix . '.js', array( 'jquery-ui-slider' ), WC_VERSION, true );
			wp_register_script( 'wc-date-slider', GR_DATE_FILTER_URL . '/assets/js/date-slider' . $suffix . '.js', array( 'jquery-ui-slider', 'wc-jquery-ui-touchpunch' ), WC_VERSION, true );
            wp_register_style( 'wc-date-slider', GR_DATE_FILTER_URL . '/assets/css/date-slider' . $suffix . '.css' );

			wp_localize_script( 'wc-date-slider', 'woocommerce_date_slider_params', array(
				'min_date'			=> isset( $_GET['min_date'] ) ? esc_attr( $_GET['min_date'] ) : '',
				'max_date'			=> isset( $_GET['max_date'] ) ? esc_attr( $_GET['max_date'] ) : ''
			) );            
            
			add_filter( 'loop_shop_post_in', array( $this, 'date_filter' ) );    
                         
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
			$maxd              = isset( $_GET['max_date'] ) ? $_GET['max_date'] : 9999999999;


				$matched_products_query = apply_filters( 'woocommerce_date_filter_results', $wpdb->get_results( $wpdb->prepare( "
					SELECT DISTINCT ID, post_date, post_parent, post_type FROM {$wpdb->posts}
					WHERE post_type IN ( 'product' )
					AND post_status = 'publish'
					AND DATEDIFF(post_date, (SELECT min(post_date) FROM {$wpdb->posts} WHERE post_type IN ( 'product' ) AND post_date != '')) BETWEEN %s AND %s
				", $mind, $maxd ), OBJECT_K ), $mind, $maxd );
            
                // print_r($matched_products_query);
            
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