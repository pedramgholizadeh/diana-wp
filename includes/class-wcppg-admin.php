<?php
defined( 'ABSPATH' ) || exit;

class WCPPG_Admin {
    public function __construct() {
        add_filter( 'woocommerce_settings_tabs_array', [ $this, 'add_settings_tab' ], 50 );
        add_action( 'woocommerce_settings_tabs_wcppg_settings', [ $this, 'settings_tab_content' ] );
        add_action( 'woocommerce_update_options_wcppg_settings', [ $this, 'update_settings' ] );
        
        // This single action now correctly handles both HPOS and legacy screens.
        add_action( 'add_meta_boxes', [ $this, 'add_order_meta_box' ], 10, 2 );
        
        add_action( 'wp_ajax_wcppg_approve_check', [ $this, 'ajax_approve_check' ] );
    }

    public function add_settings_tab( $tabs ) {
        $tabs['wcppg_settings'] = __( 'طرح‌های پرداخت فارسی', 'wcppg' );
        return $tabs;
    }

    public function settings_tab_content() {
        woocommerce_admin_fields( $this->get_settings() );
    }

    public function update_settings() {
        woocommerce_update_options( $this->get_settings() );
    }

    public function get_settings() {
        $pages_options = ['' => __('یک برگه را انتخاب کنید...', 'wcppg')];
        foreach ( get_pages() as $page ) {
            $pages_options[ $page->ID ] = $page->post_title;
        }
        return [
            'section_title' => [
                'name' => __( 'تنظیمات طرح‌های پرداخت', 'wcppg' ),
                'type' => 'title',
            ],
            'admin_phone' => [
                'name' => __( 'شماره تلفن مدیر (برای پیامک)', 'wcppg' ),
                'type' => 'text',
                'id'   => 'wcppg_admin_phone_number'
            ],
            'check_upload_page' => [
                'name'    => __( 'صفحه بارگذاری چک تضمین', 'wcppg' ),
                'type'    => 'select',
                'options' => $pages_options,
                'desc'    => __( 'این برگه باید حاوی کد کوتاه [guarantee_check_upload_form] باشد.', 'wcppg' ),
                'id'      => 'wcppg_check_upload_page_id'
            ],
            'debt_payment_page' => [
                'name'    => __( 'صفحه پرداخت بدهی/اقساط', 'wcppg' ),
                'type'    => 'select',
                'options' => $pages_options,
                'desc'    => __( 'این برگه باید حاوی کد کوتاه [debt_payment_page] باشد.', 'wcppg' ),
                'id'      => 'wcppg_debt_payment_page_id'
            ],
            'section_end' => [ 'type' => 'sectionend' ]
        ];
    }
    
    /**
     * Adds the meta box to both HPOS and legacy order screens.
     */
    public function add_order_meta_box( $post_type_or_screen_id ) {
        // Determine the screens where the meta box should appear.
        $screens = [ 'shop_order' ];
        if ( function_exists('wc_get_page_screen_id') ) {
            $screens[] = wc_get_page_screen_id( 'shop-order' );
        }

        // Add the meta box if the current screen is one of the order screens.
        if ( in_array( $post_type_or_screen_id, $screens, true ) ) {
            add_meta_box(
                'wcppg_order_details_metabox',
                __( 'جزئیات طرح پرداخت', 'wcppg' ),
                [ $this, 'render_order_meta_box' ],
                $post_type_or_screen_id, // Use the current screen ID
                'side',
                'high'
            );
        }
    }
    
    /**
     * Renders the meta box content. Works for both HPOS and legacy screens.
     */
    public function render_order_meta_box( $post_or_order_object ) {
        // Get the order object in a way that's compatible with both systems.
        $order = ( $post_or_order_object instanceof WP_Post ) 
                 ? wc_get_order( $post_or_order_object->ID ) 
                 : $post_or_order_object;

        if ( ! is_a( $order, 'WC_Order' ) ) {
            // In HPOS, the global $theorder might be available.
            global $theorder;
            if (is_a($theorder, 'WC_Order')) {
                $order = $theorder;
            } else {
                echo '<p>' . __('خطا: امکان دریافت اطلاعات سفارش وجود ندارد.', 'wcppg') . '</p>';
                return;
            }
        }
        
        $plan = $order->get_meta( '_wcppg_payment_plan' );

        if ( !$plan || 'full_payment' === $plan ) {
            echo '<p>' . __( 'این سفارش با پرداخت کامل ثبت شده است.', 'wcppg' ) . '</p>';
            return;
        }

        echo '<p><strong>' . __( 'طرح انتخابی:', 'wcppg' ) . '</strong> ';
        echo 'deposit' === $plan ? __( 'بیعانه', 'wcppg' ) : __( 'اقساط', 'wcppg' );
        echo '</p><p><strong>' . __( 'مبلغ کل اصلی سفارش:', 'wcppg' ) . '</strong> ' . wc_price( $order->get_meta( '_wcppg_original_total' ) ) . '</p><hr>';

        if ( 'installment' === $plan ) {
            $check_image_id = $order->get_meta( '_guarantee_check_image_id' );
            $check_status = $order->get_meta( '_guarantee_check_status' );
            
            echo '<h4>' . __( 'مدیریت چک تضمین', 'wcppg' ) . '</h4>';
            if ( $check_image_id ) {
                echo '<a href="' . esc_url( wp_get_attachment_url( $check_image_id ) ) . '" target="_blank"><img src="' . esc_url( wp_get_attachment_thumb_url( $check_image_id ) ) . '" style="max-width:100%; height:auto; border-radius:3px;"></a>';
                
                if ( 'approved' !== $check_status ) {
                    echo '<p><button type="button" class="button button-primary wcppg-approve-check" data-order-id="' . $order->get_id() . '" data-nonce="' . wp_create_nonce('wcppg-admin-nonce') . '">' . __( 'تایید چک و فعال‌سازی اقساط', 'wcppg' ) . '</button></p>';
                } else {
                     echo '<p class="wcppg-approved-notice">' . __( 'چک تایید شده است.', 'wcppg' ) . '</p>';
                }
            } else {
                echo '<p>' . __( 'مشتری هنوز چک را بارگذاری نکرده است.', 'wcppg' ) . '</p>';
            }
        }
    }
    
    public function ajax_approve_check() {
        if ( !check_ajax_referer('wcppg-admin-nonce', 'nonce') || !current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( ['message' => 'Unauthorized!'] );
        }
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $order = wc_get_order($order_id);
        
        if ($order) {
            $order->update_meta_data('_guarantee_check_status', 'approved');
            $order->update_status('processing', __('چک تضمین تایید شد. سفارش در حال پردازش است.', 'wcppg'));
            $order->save();
            wp_send_json_success();
        } else {
            wp_send_json_error( ['message' => 'Order not found.'] );
        }
    }
}