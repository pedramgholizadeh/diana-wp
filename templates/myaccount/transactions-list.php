<?php
defined( 'ABSPATH' ) || exit;
?>
<h3><?php _e('لیست تمام تراکنش‌های شما', 'wcppg'); ?></h3>
<p><?php _e('در این بخش می‌توانید تمام پرداخت‌های مربوط به اقساط یا تسویه حساب‌ها را مشاهده کنید.', 'wcppg'); ?></p>

<?php if ( $orders ) : ?>
    <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
        <thead>
            <tr>
                <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-number"><span class="nobr"><?php _e('کد تراکنش (سفارش)', 'wcppg'); ?></span></th>
                <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-date"><span class="nobr"><?php _e('تاریخ', 'wcppg'); ?></span></th>
                <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-status"><span class="nobr"><?php _e('وضعیت', 'wcppg'); ?></span></th>
                <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-total"><span class="nobr"><?php _e('مبلغ', 'wcppg'); ?></span></th>
                <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-actions"><span class="nobr">&nbsp;</span></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $orders as $customer_order ) :
                $order = wc_get_order( $customer_order );
            ?>
            <tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr( $order->get_status() ); ?> order">
                <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-number" data-title="<?php esc_attr_e('کد تراکنش', 'wcppg'); ?>">
                    <a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
                        #<?php echo $order->get_order_number(); ?>
                    </a>
                </td>
                <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-date" data-title="<?php esc_attr_e('تاریخ', 'wcppg'); ?>">
                    <time datetime="<?php echo esc_attr( $order->get_date_created()->date( 'c' ) ); ?>"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></time>
                </td>
                <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-status" data-title="<?php esc_attr_e('وضعیت', 'wcppg'); ?>" style="text-align:right; white-space:nowrap;">
                    <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
                </td>
                <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-total" data-title="<?php esc_attr_e('مبلغ', 'wcppg'); ?>">
                    <?php echo $order->get_formatted_order_total(); ?>
                </td>
                <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-actions">
                    <a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" class="woocommerce-button button view"><?php _e('مشاهده', 'wcppg'); ?></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else : ?>
    <div class="woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info woocommerce-info">
        <?php _e('هیچ تراکنشی یافت نشد.', 'wcppg'); ?>
    </div>
<?php endif; ?>