<?php
defined( 'ABSPATH' ) || exit;

class WCPPG_Checkout {

    public function __construct() {
        add_action( 'wp_ajax_wcppg_update_payment_option', [ $this, 'ajax_update_payment_option' ] );
        add_action( 'wp_ajax_nopriv_wcppg_update_payment_option', [ $this, 'ajax_update_payment_option' ] );
        add_action( 'woocommerce_cart_calculate_fees', [ $this, 'apply_payment_plan_fee' ] );
        add_action( 'woocommerce_checkout_create_order', [ $this, 'save_order_payment_plan' ], 10, 2 );
        add_action( 'woocommerce_thankyou', [ $this, 'redirect_for_check_upload' ], 10, 1 );
    }

    /**
     * Generates the payment options form HTML, BUT only for regular orders.
     * It will return an empty string if it's a debt/installment payment.
     */
    public static function get_payment_options_html() {
        // --- FIX: Check if the cart contains our special debt payment product ---
        if ( function_exists('WC') && WC()->cart ) {
            $debt_product_id = get_option('wcppg_debt_product_id');
            if ( $debt_product_id ) {
                foreach ( WC()->cart->get_cart() as $cart_item ) {
                    if ( $cart_item['product_id'] == $debt_product_id ) {
                        // This is a debt payment, so return nothing to hide the options.
                        return '';
                    }
                }
            }
        }
        
        // If we reach here, it's a regular order. Show the options.
        ob_start();
        $chosen_plan = WC()->session->get('wcppg_payment_plan', 'full_payment');
        ?>
        <div id="wcppg-options-container" class="wcppg-checkout-section">
            <h3><?php echo esc_html__('۱. نحوه پرداخت را انتخاب کنید', 'wcppg'); ?></h3>
            <div class="wcppg-options-list">
                <?php
                $options = [
                    'full_payment' => __('پرداخت کامل مبلغ سفارش', 'wcppg'),
                    'deposit'      => __('پرداخت با بیعانه - ۲۰٪ مبلغ کل', 'wcppg'),
                    'installment'  => __('پرداخت اقساط - ۲۵٪ پیش‌پرداخت', 'wcppg'),
                ];
                foreach ($options as $key => $label) : ?>
                    <div class="wcppg-option">
                        <input type="radio" id="wcppg_plan_<?php echo esc_attr($key); ?>" name="wcppg_payment_plan" value="<?php echo esc_attr($key); ?>" <?php checked($key, $chosen_plan); ?>>
                        <label for="wcppg_plan_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="wcppg-payment-title"><h3><?php echo esc_html__('۲. درگاه پرداخت را انتخاب کنید', 'wcppg'); ?></h3></div>
        <?php
        return ob_get_clean();
    }

    public function ajax_update_payment_option() {
        check_ajax_referer( 'wcppg-checkout-nonce', 'nonce' );
        $plan = isset( $_POST['plan'] ) ? sanitize_key( $_POST['plan'] ) : 'full_payment';
        WC()->session->set( 'wcppg_payment_plan', $plan );
        wp_send_json_success();
    }

    public function apply_payment_plan_fee( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
        $plan = WC()->session->get('wcppg_payment_plan');
        if ( empty($plan) || 'full_payment' === $plan ) return;

        $subtotal = $cart->get_subtotal();
        if ( 'deposit' === $plan ) {
            $cart->add_fee( esc_html__( 'کسر بابت بیعانه (۸۰٪)', 'wcppg' ), $subtotal * -0.80 );
        } elseif ( 'installment' === $plan ) {
            $cart->add_fee( esc_html__( 'کسر بابت پیش‌پرداخت اقساط (۷۵٪)', 'wcppg' ), $subtotal * -0.75 );
        }
    }

    public function save_order_payment_plan( $order, $data ) {
        $plan = WC()->session->get('wcppg_payment_plan');
        if ( $plan && 'full_payment' !== $plan ) {
            $cart = WC()->cart;
            $original_total = $cart->get_subtotal() + $cart->get_shipping_total() + $cart->get_total_tax();
            $order->update_meta_data( '_wcppg_payment_plan', $plan );
            $order->update_meta_data( '_wcppg_original_total', $original_total );

            if ( 'deposit' === $plan ) {
                $order->update_meta_data( '_wcppg_deposit_due_date', date( 'Y-m-d', strtotime( '+7 days' ) ) );
                $order->update_status( 'on-hold', __( 'سفارش بیعانه‌ای جدید. در انتظار پرداخت باقی‌مانده.', 'wcppg' ) );
            } elseif ( 'installment' === $plan ) {
                $order->update_status( 'wc-pending-confirmation', __( 'سفارش اقساطی جدید. در انتظار بارگذاری چک.', 'wcppg' ) );
                $installments = [];
                $remaining_amount = $original_total * 0.75;
                for ($i = 1; $i <= 3; $i++) {
                    $installments[] = [
                        'amount'   => $remaining_amount / 3,
                        'due_date' => date('Y-m-d', strtotime("+{$i} months")),
                        'status'   => 'unpaid',
                        'tx_id'    => ''
                    ];
                }
                $order->update_meta_data('_wcppg_installments', $installments);
            }
        }
        WC()->session->__unset('wcppg_payment_plan');
    }
    
    public function redirect_for_check_upload( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( $order && 'installment' === $order->get_meta('_wcppg_payment_plan') ) {
            $check_upload_page_id = get_option('wcppg_check_upload_page_id');
            if ($check_upload_page_id) {
                wp_safe_redirect( add_query_arg( 'order_id', $order_id, get_permalink( $check_upload_page_id ) ) );
                exit;
            }
        }
    }
}