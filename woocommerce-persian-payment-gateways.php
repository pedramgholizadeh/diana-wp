<?php
/**
 * Plugin Name: Diana WP | افزونه وردپرسی دیانا
 * Plugin URI: https://webiro.ir/product/diana-wp
 * Description: افزونه وردپرسی پرداخت اقساط و پرداخت با بیعانه وردپرس بر پایه ووکامرس
 * Version: 1.0
 * Author: Pedram Gholizadeh
 * Author URI: https://pedramgholizadeh.ir
 * Text Domain: wcppg
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WCPPG_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WCPPG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCPPG_VERSION', '5.3.8' );

require_once WCPPG_PLUGIN_PATH . 'includes/functions.php';
register_activation_hook( __FILE__, 'wcppg_create_debt_product_if_not_exists' );

if ( ! class_exists( 'WCPPG_Plugin' ) ) {
    final class WCPPG_Plugin {
        private static $instance;
        public static function instance() {
            if ( is_null( self::$instance ) ) self::$instance = new self();
            return self::$instance;
        }
        private function __construct() {
            add_action( 'plugins_loaded', [ $this, 'init' ] );
        }
        public function init() {
            if ( ! class_exists( 'WooCommerce' ) ) {
                add_action( 'admin_notices', [ $this, 'notice_woocommerce_needed' ] );
                return;
            }
            $this->load_files();
            $this->init_classes();
            add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
            load_plugin_textdomain( 'wcppg', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        }
        private function load_files() {
            require_once WCPPG_PLUGIN_PATH . 'includes/class-wcppg-checkout.php';
            require_once WCPPG_PLUGIN_PATH . 'includes/class-wcppg-my-account.php';
            require_once WCPPG_PLUGIN_PATH . 'includes/class-wcppg-admin.php';
        }
        private function init_classes() {
            new WCPPG_Checkout();
            new WCPPG_My_Account();
            new WCPPG_Admin();
        }
        public function enqueue_scripts() {
            if ( is_checkout() && ! is_order_received_page() ) {
                wp_enqueue_style( 'wcppg-main-style', WCPPG_PLUGIN_URL . 'assets/css/main.css', [], WCPPG_VERSION );
                wp_enqueue_script( 'wcppg-main-script', WCPPG_PLUGIN_URL . 'assets/js/main.js', [ 'jquery' ], WCPPG_VERSION, true );
                
                // This part is crucial: It passes the form's HTML to the JavaScript file.
                wp_localize_script( 'wcppg-main-script', 'wcppg_params', [
                    'ajax_url'  => admin_url( 'admin-ajax.php' ),
                    'nonce'     => wp_create_nonce( 'wcppg-checkout-nonce' ),
                    'form_html' => WCPPG_Checkout::get_payment_options_html(),
                ]);
            }
        }
        public function enqueue_admin_scripts( $hook_suffix ) {
            $screen = get_current_screen();
            $hpos_screen_id = function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id( 'shop-order' ) : 'woocommerce_page_wc-orders';
            if ( ( 'post.php' === $hook_suffix && isset($screen->post_type) && 'shop_order' === $screen->post_type ) || (isset($screen->id) && $hpos_screen_id === $screen->id) ) {
                wp_enqueue_style( 'wcppg-admin-style', WCPPG_PLUGIN_URL . 'assets/css/admin.css', [], WCPPG_VERSION );
                wp_enqueue_script( 'wcppg-admin-script', WCPPG_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery' ], WCPPG_VERSION, true );
            }
        }
        public function notice_woocommerce_needed() {
            echo '<div class="error"><p><strong>' . esc_html__( 'Persian Payment Plans plugin requires WooCommerce to be active.', 'wcppg' ) . '</strong></p></div>';
        }
    }
}
WCPPG_Plugin::instance();

