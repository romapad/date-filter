<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Date Filter Widget and related functions.
 *
 * Generates a range slider to filter products by date.
 *
 * @author   WooThemes
 * @category Widgets
 * @package  WooCommerce/Widgets
 * @version  2.3.0
 * @extends  WC_Widget
 */
class GR_Widget_Date_Filter extends WC_Widget {
    
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->widget_cssclass    = 'woocommerce widget_date_filter';
		$this->widget_description = __( 'Shows a date filter slider in a widget which lets you narrow down the list of shown products when viewing product categories.', 'woocommerce' );
		$this->widget_id          = 'woocommerce_date_filter';
		$this->widget_name        = __( 'Woo Date Filter', 'woocommerce' );
		$this->settings           = array(
			'title'  => array(
				'type'  => 'text',
				'std'   => __( 'Filter by Date', 'woocommerce' ),
				'label' => __( 'Title', 'woocommerce' )
			)
		);

		parent::__construct();
	}

	/**
	 * Output widget.
	 *
	 * @see WP_Widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		global $_chosen_attributes, $wpdb, $wp;

		if ( ! is_post_type_archive( 'product' ) && ! is_tax( get_object_taxonomies( 'product' ) ) ) {
			return;
		}

		if ( sizeof( WC()->query->unfiltered_product_ids ) == 0 ) {
			return; // None shown - return
		}

		$min_date = isset( $_GET['min_date'] ) ? esc_attr( $_GET['min_date'] ) : '';
		$max_date = isset( $_GET['max_date'] ) ? esc_attr( $_GET['max_date'] ) : '';

		wp_enqueue_script( 'wc-date-slider' );

		// Remember current filters/search
		$fields = '';

		if ( get_search_query() ) {
			$fields .= '<input type="hidden" name="s" value="' . get_search_query() . '" />';
		}

		if ( ! empty( $_GET['post_type'] ) ) {
			$fields .= '<input type="hidden" name="post_type" value="' . esc_attr( $_GET['post_type'] ) . '" />';
		}

		if ( ! empty ( $_GET['product_cat'] ) ) {
			$fields .= '<input type="hidden" name="product_cat" value="' . esc_attr( $_GET['product_cat'] ) . '" />';
		}

		if ( ! empty( $_GET['product_tag'] ) ) {
			$fields .= '<input type="hidden" name="product_tag" value="' . esc_attr( $_GET['product_tag'] ) . '" />';
		}

		if ( ! empty( $_GET['orderby'] ) ) {
			$fields .= '<input type="hidden" name="orderby" value="' . esc_attr( $_GET['orderby'] ) . '" />';
		}

		if ( $_chosen_attributes ) {
			foreach ( $_chosen_attributes as $attribute => $data ) {
				$taxonomy_filter = 'filter_' . str_replace( 'pa_', '', $attribute );

				$fields .= '<input type="hidden" name="' . esc_attr( $taxonomy_filter ) . '" value="' . esc_attr( implode( ',', $data['terms'] ) ) . '" />';

				if ( 'or' == $data['query_type'] ) {
					$fields .= '<input type="hidden" name="' . esc_attr( str_replace( 'pa_', 'query_type_', $attribute ) ) . '" value="or" />';
				}
			}
		}

		if ( 0 === sizeof( WC()->query->layered_nav_product_ids ) ) {
			$min = $wpdb->get_var( "
				SELECT min(post_date)
				FROM {$wpdb->posts} as posts
				WHERE post_date != ''
			" );
			$max = $wpdb->get_var( "
				SELECT max(post_date)
				FROM {$wpdb->posts} as posts
			" );
		} else {
			$min = $wpdb->get_var( "
				SELECT min(post_date)
				FROM {$wpdb->posts} as posts
				WHERE post_date != ''
				AND (
					posts.ID IN (" . implode( ',', array_map( 'absint', WC()->query->layered_nav_product_ids ) ) . ")
					OR (
						posts.post_parent IN (" . implode( ',', array_map( 'absint', WC()->query->layered_nav_product_ids ) ) . ")
						AND posts.post_parent != 0
					)
				)
			" );
			$max = $wpdb->get_var( "
				SELECT max(post_date)
				FROM {$wpdb->posts} as posts
				WHERE (
					posts.ID IN (" . implode( ',', array_map( 'absint', WC()->query->layered_nav_product_ids ) ) . ")
					OR (
						posts.post_parent IN (" . implode( ',', array_map( 'absint', WC()->query->layered_nav_product_ids ) ) . ")
						AND posts.post_parent != 0
					)
				)
			" );
		}

		if ( $min == $max ) {
			return;
		}

		$this->widget_start( $args, $instance );

		if ( '' == get_option( 'permalink_structure' ) ) {
			$form_action = remove_query_arg( array( 'page', 'paged' ), add_query_arg( $wp->query_string, '', home_url( $wp->request ) ) );
		} else {
			$form_action = preg_replace( '%\/page/[0-9]+%', '', home_url( trailingslashit( $wp->request ) ) );
		}



		echo '<form method="get" action="' . esc_url( $form_action ) . '">
			<div class="date_filter_wrapper">
                <div class="date_slider" style="display:none;"></div>
				<div class="date_slider_amount">
					<input type="text" id="min_date" name="min_date" value="' . esc_attr( $min_date ) . '" data-min="' . esc_attr( apply_filters( 'woocommerce_date_filter_widget_min_date', $min ) ) . '" placeholder="' . esc_attr__('Min Date', 'woocommerce' ) . '" />
					<input type="text" id="max_date" name="max_date" value="' . esc_attr( $max_date ) . '" data-max="' . esc_attr( apply_filters( 'woocommerce_date_filter_widget_max_date', $max ) ) . '" placeholder="' . esc_attr__( 'Max Date', 'woocommerce' ) . '" />
					<button type="submit" class="button">' . __( 'Filter', 'woocommerce' ) . '</button>
					<div class="date_label" style="display:none;">
						' . __( 'Date:', 'woocommerce' ) . ' last <span class="from"></span> days <span class="to"></span>
					</div>
					' . $fields . '
					<div class="clear"></div>
				</div>
			</div>
		</form>';
        echo $min. '<br>';
        echo $max;
	}
}

function gr_register_widgets() {
	register_widget( 'GR_Widget_Date_Filter' );
}
add_action( 'widgets_init', 'gr_register_widgets' );    