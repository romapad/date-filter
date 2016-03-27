<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** CREDITS TO:
 * Layered Navigation Widget extended to include Categories
 *
 * @author   Oscar Bazaldua
 * @category Widgets
 * @package  CategoriesLayeredNavForWoocommerce/Widgets
 * @version  1.0
 * @extends  WC_Widget_Layered_Nav
 */
class GR_Widget_Layered_Nav extends WC_Widget_Layered_Nav {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->widget_cssclass    = 'woocommerce widget_layered_nav';
		$this->widget_description = __( 'Shows a custom attribute in a widget which lets you narrow down the list of products when viewing product categories.', 'woocommerce' );
		$this->widget_id          = 'woocommerce_layered_nav';
		$this->widget_name        = __( 'Categories Layered Nav', 'woocommerce' );
		WC_Widget::__construct();
	}

	/**
	 * Init settings adding the product category taxonomy
	 *
	 * @return void
	 */
	public function init_settings() {
		$attribute_array      = array();
		$category_taxonomy    = get_taxonomies( array( 'name' => GR_PRODUCTS_CATEGORY ), 'objects' );
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		if ( $category_taxonomy ) {
			$category_taxonomy                             = array_pop( $category_taxonomy );
			$attribute_array[ GR_PRODUCTS_CATEGORY ] = $category_taxonomy->label;
		}

		if ( $attribute_taxonomies ) {
			foreach ( $attribute_taxonomies as $tax ) {
                $attribute_key = wc_attribute_taxonomy_name( $tax->attribute_name );
                if ( taxonomy_exists( $attribute_key ) ) {
                    $attribute_array[ $tax->attribute_name ] = $tax->attribute_name;
                }
			}
		}

		$this->settings = array(
			'title' => array(
				'type'  => 'text',
				'std'   => __( 'Filter by', 'woocommerce' ),
				'label' => __( 'Title', 'woocommerce' )
			),
			'attribute' => array(
				'type'    => 'select',
				'std'     => '',
				'label'   => __( 'Attribute', 'woocommerce' ),
				'options' => $attribute_array
			),
			'display_type' => array(
				'type'    => 'select',
				'std'     => 'list',
				'label'   => __( 'Display type', 'woocommerce' ),
				'options' => array(
					'list'     => __( 'List', 'woocommerce' ),
					'dropdown' => __( 'Dropdown', 'woocommerce' )
				)
			),
			'query_type' => array(
				'type'    => 'select',
				'std'     => 'and',
				'label'   => __( 'Query type', 'woocommerce' ),
				'options' => array(
					'and' => __( 'AND', 'woocommerce' ),
					'or'  => __( 'OR', 'woocommerce' )
				)
			),
		);
	}

	/**
	 * Widget Display Function.
	 *
	 * Added ability to hide the widget if its filter attribute is a category
	 * and the current page is category archive
	 * Changed the way it constructs the filters so that it uses the value
	 * instead of recreating the attribute id.
	 *
	 * @param array $args
	 * @param array $instance
	 *
	 * @return void
	 */
	public function widget( $args, $instance ) {
		global $_chosen_attributes;

		if ( ! is_post_type_archive( 'product' ) && ! is_tax( get_object_taxonomies( 'product' ) ) ) {
			return;
		}

		$current_term = is_tax() ? get_queried_object()->term_id : '';
		$current_tax  = is_tax() ? get_queried_object()->taxonomy : '';
        if (isset( $instance['attribute'] )) {
            if ($instance['attribute'] == GR_PRODUCTS_CATEGORY) {
                $taxonomy = $instance['attribute'];
            } else {
                $taxonomy = wc_attribute_taxonomy_name($instance['attribute']);
            }
        } else {
           $taxonomy = $this->settings['attribute']['std'];
        }
        

		$query_type   = isset( $instance['query_type'] ) ? $instance['query_type'] : $this->settings['query_type']['std'];
		$display_type = isset( $instance['display_type'] ) ? $instance['display_type'] : $this->settings['display_type']['std'];

		// Skip Display if we are browsing a product category
		if ( is_product_category() && $taxonomy == GR_PRODUCTS_CATEGORY ) {
			return;
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$get_terms_args = array( 'hide_empty' => '1' );

		$orderby = wc_attribute_orderby( $taxonomy );

		switch ( $orderby ) {
			case 'name' :
				$get_terms_args['orderby']    = 'name';
				$get_terms_args['menu_order'] = false;
			break;
			case 'id' :
				$get_terms_args['orderby']    = 'id';
				$get_terms_args['order']      = 'ASC';
				$get_terms_args['menu_order'] = false;
			break;
			case 'menu_order' :
				$get_terms_args['menu_order'] = 'ASC';
			break;
		}

		$terms = get_terms( $taxonomy, $get_terms_args );

		if ( 0 < count( $terms ) ) {

			ob_start();

			$found = false;

			$this->widget_start( $args, $instance );

			// Force found when option is selected - do not force found on taxonomy attributes
			if ( ! is_tax() && is_array( $_chosen_attributes ) && array_key_exists( $taxonomy, $_chosen_attributes ) ) {
				$found = true;
			}

			if ( 'dropdown' == $display_type ) {

				// skip when viewing the taxonomy
				if ( $current_tax && $taxonomy == $current_tax ) {

					$found = false;

				} else {

					$taxonomy_filter = str_replace( 'pa_', '', $taxonomy );

					$found = false;

					echo '<select class="dropdown_layered_nav_' . $taxonomy_filter . '">';

					echo '<option value="">' . sprintf( __( 'Any %s', 'woocommerce' ), wc_attribute_label( $taxonomy ) ) . '</option>';

					foreach ( $terms as $term ) {

						// If on a term page, skip that term in widget list
						if ( $term->term_id == $current_term ) {
							continue;
						}

						$transient_name = 'wc_ln_count_' . md5( sanitize_key( $taxonomy ) . sanitize_key( $term->term_taxonomy_id ) );

						if ( false === ( $_products_in_term = get_transient( $transient_name ) ) ) {

							$_products_in_term = get_objects_in_term( $term->term_id, $taxonomy );

							set_transient( $transient_name, $_products_in_term, YEAR_IN_SECONDS );
						}

						$option_is_set = ( isset( $_chosen_attributes[ $taxonomy ] ) && in_array( $term->term_id, $_chosen_attributes[ $taxonomy ]['terms'] ) );

						// If this is an AND query, only show options with count > 0
						if ( 'and' == $query_type ) {

							$count = sizeof( array_intersect( $_products_in_term, WC()->query->filtered_product_ids ) );

							if ( 0 < $count ) {
								$found = true;
							}

							if ( 0 == $count && ! $option_is_set ) {
								continue;
							}

						// If this is an OR query, show all options so search can be expanded
						} else {

							$count = sizeof( array_intersect( $_products_in_term, WC()->query->unfiltered_product_ids ) );

							if ( 0 < $count ) {
								$found = true;
							}

						}

						echo '<option value="' . esc_attr( $term->term_id ) . '" ' . selected( isset( $_GET[ 'filter_' . $taxonomy_filter ] ) ? $_GET[ 'filter_' . $taxonomy_filter ] : '' , $term->term_id, false ) . '>' . esc_html( $term->name ) . '</option>';
					}

					echo '</select>';

					wc_enqueue_js( "
						jQuery( '.dropdown_layered_nav_$taxonomy_filter' ).change( function() {
							var term_id = parseInt( jQuery( this ).val(), 10 );
							location.href = '" . preg_replace( '%\/page\/[0-9]+%', '', str_replace( array( '&amp;', '%2C' ), array( '&', ',' ), esc_js( add_query_arg( 'filtering', '1', remove_query_arg( array( 'page', 'filter_' . $taxonomy_filter ) ) ) ) ) ) . "&filter_$taxonomy_filter=' + ( isNaN( term_id ) ? '' : term_id );
						});
					" );

				}

			} else {

				// List display
				echo '<ul>';

				foreach ( $terms as $term ) {

					$transient_name = 'wc_ln_count_' . md5( sanitize_key( $taxonomy ) . sanitize_key( $term->term_taxonomy_id ) );

					if ( false === ( $_products_in_term = get_transient( $transient_name ) ) ) {

						$_products_in_term = get_objects_in_term( $term->term_id, $taxonomy );

						set_transient( $transient_name, $_products_in_term );
					}

					$option_is_set = ( isset( $_chosen_attributes[ $taxonomy ] ) && in_array( $term->term_id, $_chosen_attributes[ $taxonomy ]['terms'] ) );

					// skip the term for the current archive
					if ( $current_term == $term->term_id ) {
						continue;
					}

					// If this is an AND query, only show options with count > 0
					if ( 'and' == $query_type ) {

						$count = sizeof( array_intersect( $_products_in_term, WC()->query->filtered_product_ids ) );

						if ( 0 < $count && $current_term !== $term->term_id ) {
							$found = true;
						}

						if ( 0 == $count && ! $option_is_set ) {
							continue;
						}

					// If this is an OR query, show all options so search can be expanded
					} else {

						$count = sizeof( array_intersect( $_products_in_term, WC()->query->unfiltered_product_ids ) );

						if ( 0 < $count ) {
							$found = true;
						}
					}

					$arg = 'filter_' . sanitize_title( $instance['attribute'] );

					$current_filter = ( isset( $_GET[ $arg ] ) ) ? explode( ',', $_GET[ $arg ] ) : array();

					if ( ! is_array( $current_filter ) ) {
						$current_filter = array();
					}

					$current_filter = array_map( 'esc_attr', $current_filter );

					if ( ! in_array( $term->term_id, $current_filter ) ) {
						$current_filter[] = $term->term_id;
					}

					// Base Link decided by current page
					if ( defined( 'SHOP_IS_ON_FRONT' ) ) {
						$link = home_url();
					} elseif ( is_post_type_archive( 'product' ) || is_page( wc_get_page_id('shop') ) ) {
						$link = get_post_type_archive_link( 'product' );
					} else {
						$link = get_term_link( get_query_var('term'), get_query_var('taxonomy') );
					}

					// All current filters
					if ( $_chosen_attributes ) {
						foreach ( $_chosen_attributes as $name => $data ) {
							if ( $name !== $taxonomy ) {

								// Exclude query arg for current term archive term
								while ( in_array( $current_term, $data['terms'] ) ) {
									$key = array_search( $current_term, $data );
									unset( $data['terms'][$key] );
								}

								// Remove pa_ and sanitize
								$filter_name = sanitize_title( str_replace( 'pa_', '', $name ) );

								if ( ! empty( $data['terms'] ) ) {
									$link = add_query_arg( 'filter_' . $filter_name, implode( ',', $data['terms'] ), $link );
								}

								if ( 'or' == $data['query_type'] ) {
									$link = add_query_arg( 'query_type_' . $filter_name, 'or', $link );
								}
							}
						}
					}

					// Min/Max
					if ( isset( $_GET['min_price'] ) ) {
						$link = add_query_arg( 'min_price', $_GET['min_price'], $link );
					}

					if ( isset( $_GET['max_price'] ) ) {
						$link = add_query_arg( 'max_price', $_GET['max_price'], $link );
					}
                    
					if ( isset( $_GET['min_date'] ) ) {
						$link = add_query_arg( 'min_date', $_GET['min_date'], $link );
					}

					if ( isset( $_GET['max_date'] ) ) {
						$link = add_query_arg( 'max_date', $_GET['max_date'], $link );
					}                    

					// Orderby
					if ( isset( $_GET['orderby'] ) ) {
						$link = add_query_arg( 'orderby', $_GET['orderby'], $link );
					}

					

					$class = '';
					$link  = add_query_arg( $arg, implode( ',', $current_filter ), $link );


					// Search Arg
					if ( get_search_query() ) {
						$link = add_query_arg( 's', get_search_query(), $link );
					}

					// Post Type Arg
					if ( isset( $_GET['post_type'] ) ) {
						$link = add_query_arg( 'post_type', $_GET['post_type'], $link );
					}

					// Query type Arg
					if ( $query_type == 'or' && ! ( sizeof( $current_filter ) == 1 && isset( $_chosen_attributes[ $taxonomy ]['terms'] ) && is_array( $_chosen_attributes[ $taxonomy ]['terms'] ) && in_array( $term->term_id, $_chosen_attributes[ $taxonomy ]['terms'] ) ) ) {
						$link = add_query_arg( 'query_type_' . sanitize_title( $instance['attribute'] ), 'or', $link );
					}

					echo '<li ' . $class . '>';

					echo ( $count > 0 || $option_is_set ) ? '<a href="' . esc_url( apply_filters( 'woocommerce_layered_nav_link', $link ) ) . '">' : '<span>';

					echo $term->name;

					echo ( $count > 0 || $option_is_set ) ? '</a>' : '</span>';

					echo ' <small class="count">' . $count . '</small></li>';

				}

				echo '</ul>';

			} // End display type conditional

			$this->widget_end( $args );

			if ( ! $found ) {
				ob_end_clean();
			} else {
				echo ob_get_clean();
			}
		}
	}
}