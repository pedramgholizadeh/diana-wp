<?php
defined( 'ABSPATH' ) || exit;

class WCPPG_My_Account {
    public function __construct() {
        // Add new endpoint and menu item for transactions
        add_action( 'init', [ $this, 'add_transactions_endpoint' ] );
        add_filter( 'woocommerce_account_menu_items', [ $this, 'add_transactions_menu_item' ] );
        add_action( 'woocommerce_account_transactions_endpoint', [ $this, 'transactions_endpoint_content' ] );

        // Filter the main orders list
        add_filter( 'woocommerce_my_account_my_orders_query', [ $this, 'filter_my_account_orders_query' ] );
        add_filter( 'woocommerce_my_account_orders_columns', [ $this, 'rename_my_account_orders_column' ] );

        // Display payment plan details on the view-order page
        add_action( 'woocommerce_order_details_after_order_table', [ $this, 'display_payment_plan_details' ], 20 );
    }

    public function add_transactions_endpoint() {
        add_rewrite_endpoint( 'transactions', EP_PAGES );
    }

    public function add_transactions_menu_item( $items ) {
        $new_items = [];
        foreach ($items as $key => $value) {
            $new_items[$key] = $value;
            if ('orders' === $key) {
                $new_items['transactions'] = __( 'تراکنش‌ها', 'wcppg' );
            }
        }
        return $new_items;
    }

    public function transactions_endpoint_content() {
        $payment_orders = wc_get_orders([
            'customer_id' => get_current_user_id(),
            'limit'       => -1,
            'meta_key'    => '_wcppg_is_payment_order',
            'meta_value'  => 'yes'
        ]);
        wc_get_template( 'myaccount/transactions-list.php', [ 'orders' => $payment_orders ], '', WCPPG_PLUGIN_PATH . 'templates/' );
    }

    public function filter_my_account_orders_query( $args ) {
        $args['meta_query'][] = [
            'key'     => '_wcppg_is_payment_order',
            'compare' => 'NOT EXISTS',
        ];
        return $args;
    }

    public function rename_my_account_orders_column( $columns ) {
        if ( isset($columns['order-number']) ) {
            $columns['order-number'] = __( 'کد سفارش', 'wcppg' );
        }
        return $columns;
    }

    public function display_payment_plan_details( $order ) {
        $plan = $order->get_meta('_wcppg_payment_plan');
        if ( !empty($plan) && 'full_payment' !== $plan ) {
            // Find related payment orders
            $related_payments = wc_get_orders([
                'limit'      => -1,
                'meta_key'   => '_wcppg_payment_for_order',
                'meta_value' => $order->get_id(),
            ]);
            wc_get_template( 'myaccount/order-payment-details.php', [ 'order' => $order, 'related_payments' => $related_payments ], '', WCPPG_PLUGIN_PATH . 'templates/' );
        }
    }
}