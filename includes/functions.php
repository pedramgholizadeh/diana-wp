<?php
defined( 'ABSPATH' ) || exit;

// --- Helper Functions ---
function wcppg_send_sms( $phone, $message, $name = '' ) { /* ... */ }

// --- Custom Order Status Functions ---
function wcppg_register_custom_order_statuses() {
    $statuses = [
        'wc-pending-confirmation' => [
            'label'                     => _x( 'در انتظار تایید چک', 'Order status', 'wcppg' ),
            'public'                    => true,
            'show_in_admin_status_list' => true,
        ],
        'wc-in-production' => [
            'label'                     => _x( 'در مرحله ساخت', 'Order status', 'wcppg' ),
            'public'                    => true,
            'show_in_admin_status_list' => true,
        ],
        // You can add more statuses like 'shipped' here if needed.
    ];

    foreach ($statuses as $status_key => $props) {
        register_post_status( $status_key, $props );
    }
}
add_action( 'init', 'wcppg_register_custom_order_statuses' );

function wcppg_add_custom_order_statuses_to_list( $order_statuses ) {
    if ( ! is_array( $order_statuses ) ) $order_statuses = [];
    
    $order_statuses['wc-pending-confirmation'] = _x( 'در انتظار تایید چک', 'Order status', 'wcppg' );
    $order_statuses['wc-in-production'] = _x( 'در مرحله ساخت', 'Order status', 'wcppg' );
    
    return $order_statuses;
}
add_filter( 'wc_order_statuses', 'wcppg_add_custom_order_statuses_to_list' );

// --- Shortcode Logic ---
function wcppg_create_debt_product_if_not_exists() {
    $product_id = get_option('wcppg_debt_product_id');
    if ($product_id && get_post_status($product_id) === 'publish') {
        return $product_id;
    }
    // Create a hidden simple product for debt payments
    $product = [
        'post_title'   => __('پرداخت بدهی/قسط', 'wcppg'),
        'post_content' => __('این محصول برای پرداخت بدهی یا اقساط به صورت سیستمی ایجاد شده است و نباید حذف شود.', 'wcppg'),
        'post_status'  => 'publish',
        'post_type'    => 'product',
        'post_author'  => get_current_user_id(),
    ];
    $product_id = wp_insert_post($product);
    if (is_wp_error($product_id)) return false;
    update_post_meta($product_id, '_regular_price', 0);
    update_post_meta($product_id, '_price', 0);
    update_post_meta($product_id, '_stock_status', 'instock');
    update_post_meta($product_id, '_visibility', 'hidden');
    update_post_meta($product_id, '_virtual', 'yes');
    update_post_meta($product_id, '_sold_individually', 'yes');
    update_option('wcppg_debt_product_id', $product_id);
    return $product_id;
}
function wcppg_check_upload_form_shortcode() {
    ob_start();
    wc_get_template('checkout/guarantee-check-upload-form.php', [], '', WCPPG_PLUGIN_PATH . 'templates/');
    return ob_get_clean();
}
add_shortcode('guarantee_check_upload_form', 'wcppg_check_upload_form_shortcode');
function wcppg_debt_payment_page_shortcode() {
    if (!is_user_logged_in()) {
        return '<div class="woocommerce-error">' . __('برای مشاهده و پرداخت بدهی یا اقساط، ابتدا وارد حساب کاربری خود شوید.', 'wcppg') . '</div>';
    }
    $user_id = get_current_user_id();
    $orders = wc_get_orders([
        'customer_id' => $user_id,
        'limit'       => -1,
        'orderby'     => 'date',
        'order'       => 'DESC',
        'meta_query'  => [
            [
                'key'     => '_wcppg_payment_plan',
                'compare' => 'EXISTS',
            ],
            [
                'key'     => '_wcppg_payment_plan',
                'value'   => 'full_payment',
                'compare' => '!=',
            ],
        ],
    ]);
    ob_start();
    if (empty($orders)) {
        echo '<div class="woocommerce-info">' . __('هیچ سفارش اقساطی یا بدهی برای شما یافت نشد.', 'wcppg') . '</div>';
    } else {
        echo '<h3>' . __('سفارش‌های دارای بدهی یا اقساط شما', 'wcppg') . '</h3>';
        echo '<ul class="wcppg-debt-orders-list">';
        foreach ($orders as $order) {
            $plan = $order->get_meta('_wcppg_payment_plan');
            $order_url = esc_url($order->get_view_order_url());
            echo '<li>';
            echo '<a href="' . $order_url . '">' . sprintf(__('سفارش #%s', 'wcppg'), $order->get_order_number()) . '</a> ';
            echo '<span class="wcppg-plan-label">(' . ($plan === 'deposit' ? __('بیعانه', 'wcppg') : __('اقساط', 'wcppg')) . ')</span>';
            echo '</li>';
        }
        echo '</ul>';
    }
    return ob_get_clean();
}
add_shortcode( 'debt_payment_page', 'wcppg_debt_payment_page_shortcode' );

// --- Main Payment Link & Cart Logic ---
function wcppg_handle_debt_payment_link() {
    global $post;
    if ( is_a($post, 'WP_Post') && has_shortcode( $post->post_content, 'debt_payment_page' ) && isset($_GET['amount'], $_GET['oid'], $_GET['ptype']) ) {
        $amount = abs( floatval( $_GET['amount'] ) );
        $oid    = absint( $_GET['oid'] );
        $ptype  = sanitize_key( $_GET['ptype'] );
        $pnum   = isset($_GET['pnum']) ? absint( $_GET['pnum'] ) : -1;

        if ( $amount > 0 && $oid > 0 ) {
            $product_id = wcppg_create_debt_product_if_not_exists();
            if ( ! $product_id ) return;

            WC()->cart->empty_cart();
            
            $title = 'deposit' === $ptype 
                ? sprintf( __( 'تسویه سفارش #%s', 'wcppg' ), $oid )
                : sprintf( __( 'پرداخت قسط %d سفارش #%s', 'wcppg' ), $pnum + 1, $oid );

            WC()->cart->add_to_cart( $product_id, 1, 0, [], [
                'wcppg_payment_data' => [ 'amount' => $amount, 'oid' => $oid, 'ptype' => $ptype, 'pnum' => $pnum, 'title' => $title ]
            ]);
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }
    }
}
add_action( 'template_redirect', 'wcppg_handle_debt_payment_link' );

function wcppg_set_debt_cart_item_details( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
    foreach ( $cart->get_cart() as $cart_item ) {
        if ( isset( $cart_item['wcppg_payment_data'] ) ) {
            $cart_item['data']->set_price( $cart_item['wcppg_payment_data']['amount'] );
            $cart_item['data']->set_name( $cart_item['wcppg_payment_data']['title'] );
        }
    }
}
add_action( 'woocommerce_before_calculate_totals', 'wcppg_set_debt_cart_item_details', 100, 1 );

/**
 * Saves reference data to the payment order itself.
 */
function wcppg_save_ref_data_to_payment_order( $order, $data ) {
    $cart = WC()->cart->get_cart();
    foreach ($cart as $cart_item) {
        if ( isset( $cart_item['wcppg_payment_data'] ) ) {
            // This is a payment order.
            $ref_data = $cart_item['wcppg_payment_data'];
            $order->update_meta_data( '_wcppg_is_payment_order', 'yes' );
            $order->update_meta_data( '_wcppg_payment_for_order', $ref_data['oid'] );
            $order->update_meta_data( '_wcppg_ref_data', $ref_data );
            break;
        }
    }
}
add_action( 'woocommerce_checkout_create_order', 'wcppg_save_ref_data_to_payment_order', 20, 2 );

/**
 * This function now runs for both 'processing' and 'completed' statuses
 */
function wcppg_handle_successful_payment( $order_id ) {
    $payment_order = wc_get_order($order_id);
    if ( ! $payment_order || 'yes' === $payment_order->get_meta('_wcppg_original_order_updated') ) {
        return; 
    }

    $ref_data = $payment_order->get_meta('_wcppg_ref_data');
    if ( ! empty($ref_data) ) {
        $original_order_id = absint($ref_data['oid']);
        $original_order = wc_get_order($original_order_id);
        if ( ! $original_order ) return;

        $payment_type = sanitize_key($ref_data['ptype']);
        $payment_num = intval($ref_data['pnum']);
        $paid_amount = floatval($ref_data['amount']);
        
        if ( 'deposit' === $payment_type ) {
            $original_order->update_meta_data('_wcppg_deposit_fully_paid', 'yes');
            $original_order->add_order_note( sprintf( __( 'مبلغ باقی‌مانده بیعانه (%s) طی سفارش پرداخت #%d پرداخت شد.', 'wcppg' ), wc_price($paid_amount), $order_id ) );
            $original_order->update_status('processing', __('پرداخت بیعانه تکمیل شد.', 'wcppg'));
        
        } elseif ( 'installment' === $payment_type && $payment_num >= 0 ) {
            $installments = $original_order->get_meta('_wcppg_installments');
            if ( is_array($installments) && isset($installments[$payment_num]) ) {
                $installments[$payment_num]['status'] = 'paid';
                $installments[$payment_num]['tx_id'] = $order_id;
                $original_order->update_meta_data('_wcppg_installments', $installments);
                $original_order->add_order_note( sprintf( __( 'قسط شماره %d (%s) طی سفارش پرداخت #%d پرداخت شد.', 'wcppg' ), $payment_num + 1, wc_price($paid_amount), $order_id ) );
                
                $all_paid = !in_array('unpaid', array_column($installments, 'status'));
                if ($all_paid) {
                    $original_order->update_status('completed', __('تمام اقساط پرداخت و سفارش تکمیل شد.', 'wcppg'));
                }
                $original_order->save();
            }
        }
        
        $payment_order->update_meta_data('_wcppg_original_order_updated', 'yes');
        $payment_order->save();
    }
}
add_action( 'woocommerce_order_status_processing', 'wcppg_handle_successful_payment', 20, 1 );
add_action( 'woocommerce_order_status_completed', 'wcppg_handle_successful_payment', 20, 1 );