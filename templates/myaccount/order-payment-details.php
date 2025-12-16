<?php
defined( 'ABSPATH' ) || exit;
$plan = $order->get_meta('_wcppg_payment_plan');
$original_total = $order->get_meta('_wcppg_original_total');
?>
<section class="wcppg-myaccount-details">
    <h2 class="woocommerce-order-details__title"><?php _e('جزئیات طرح پرداخت', 'wcppg'); ?></h2>

    <?php // --- Deposit Logic --- ?>
    <?php if ('deposit' === $plan): ?>
        <p><strong><?php _e('طرح پرداخت:', 'wcppg'); ?></strong> <?php _e('بیعانه', 'wcppg'); ?></p>
        <p><strong><?php _e('مبلغ کل سفارش:', 'wcppg'); ?></strong> <?php echo wc_price($original_total); ?></p>
        <?php $deposit_due_date = $order->get_meta('_wcppg_deposit_due_date'); ?>
        <p><strong><?php _e('مهلت پرداخت باقی‌مانده:', 'wcppg'); ?></strong> <?php echo esc_html($deposit_due_date ? wc_format_datetime($deposit_due_date) : '-'); ?></p>
        <?php $deposit_paid = $order->get_meta('_wcppg_deposit_fully_paid') === 'yes'; ?>
        <p><strong><?php _e('وضعیت بیعانه:', 'wcppg'); ?></strong> <?php echo $deposit_paid ? '<span class="wcppg-status-paid">پرداخت کامل</span>' : '<span class="wcppg-status-unpaid">در انتظار پرداخت باقی‌مانده</span>'; ?></p>
        <?php if (!$deposit_paid): ?>
            <a href="<?php echo esc_url(add_query_arg(['amount' => $original_total * 0.8, 'oid' => $order->get_id(), 'ptype' => 'deposit'], get_permalink(get_option('wcppg_debt_payment_page_id')))); ?>" class="button pay wcppg-pay-deposit"><?php _e('پرداخت باقی‌مانده بیعانه', 'wcppg'); ?></a>
        <?php endif; ?>
    <?php endif; ?>

    <?php // --- Installment Logic --- ?>
    <?php if ('installment' === $plan): 
        $check_status = $order->get_meta('_guarantee_check_status');
        $installments = $order->get_meta('_wcppg_installments');
    ?>
        <p><strong><?php _e('طرح پرداخت:', 'wcppg'); ?></strong> <?php _e('اقساط', 'wcppg'); ?></p>
        <p><strong><?php _e('مبلغ کل سفارش:', 'wcppg'); ?></strong> <?php echo wc_price($original_total); ?></p>
        <p><strong><?php _e('وضعیت چک تضمین:', 'wcppg'); ?></strong> 
            <?php echo 'approved' === $check_status ? '<span class="wcppg-status-approved">تایید شده</span>' : '<span class="wcppg-status-pending">در انتظار بررسی</span>'; ?>
        </p>
        <?php if ('approved' === $check_status && is_array($installments)): ?>
            <table class="woocommerce-table woocommerce-table--installments shop_table shop_table_responsive">
                <thead>
                    <tr>
                        <th><?php _e('قسط', 'wcppg'); ?></th>
                        <th><?php _e('مبلغ', 'wcppg'); ?></th>
                        <th><?php _e('تاریخ سررسید', 'wcppg'); ?></th>
                        <th><?php _e('وضعیت', 'wcppg'); ?></th>
                        <th><?php _e('عملیات', 'wcppg'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($installments as $i => $inst): ?>
                        <tr>
                            <td><?php echo esc_html($i + 1); ?></td>
                            <td><?php echo wc_price($inst['amount']); ?></td>
                            <td><?php echo esc_html($inst['due_date']); ?></td>
                            <td>
                                <?php
                                if ($inst['status'] === 'paid') {
                                    echo '<span class="wcppg-status-paid">' . __('پرداخت شده', 'wcppg') . '</span>';
                                } else {
                                    echo '<span class="wcppg-status-unpaid">' . __('در انتظار پرداخت', 'wcppg') . '</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($inst['status'] !== 'paid'): ?>
                                    <a href="<?php echo esc_url(add_query_arg([
                                        'amount' => $inst['amount'],
                                        'oid'    => $order->get_id(),
                                        'ptype'  => 'installment',
                                        'pnum'   => $i
                                    ], get_permalink(get_option('wcppg_debt_payment_page_id')))); ?>" class="button pay wcppg-pay-installment"><?php _e('پرداخت این قسط', 'wcppg'); ?></a>
                                <?php else: ?>
                                    <?php echo !empty($inst['tx_id']) ? sprintf(__('کد پرداخت: %s', 'wcppg'), esc_html($inst['tx_id'])) : '-'; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif ('approved' !== $check_status): ?>
            <div class="woocommerce-info"><?php _e('تا زمانی که چک تضمین تایید نشود، امکان پرداخت اقساط فعال نمی‌شود.', 'wcppg'); ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <?php // --- NEW SECTION: Related Transactions --- ?>
    <?php if (!empty($related_payments)): ?>
        <h3 class="woocommerce-order-details__title" style="margin-top: 2em;"><?php _e('تراکنش‌های مرتبط با این سفارش', 'wcppg'); ?></h3>
        <table class="woocommerce-table woocommerce-table--transactions shop_table shop_table_responsive">
            <thead>
                <tr>
                    <th><?php _e('کد تراکنش (سفارش)', 'wcppg'); ?></th>
                    <th><?php _e('تاریخ', 'wcppg'); ?></th>
                    <th><?php _e('مبلغ', 'wcppg'); ?></th>
                    <th><?php _e('وضعیت', 'wcppg'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($related_payments as $payment_order): ?>
                <tr>
                    <td data-title="<?php _e('کد تراکنش', 'wcppg'); ?>"><a href="<?php echo esc_url($payment_order->get_view_order_url()); ?>">#<?php echo $payment_order->get_order_number(); ?></a></td>
                    <td data-title="<?php _e('تاریخ', 'wcppg'); ?>"><?php echo wc_format_datetime($payment_order->get_date_created()); ?></td>
                    <td data-title="<?php _e('مبلغ', 'wcppg'); ?>"><?php echo $payment_order->get_formatted_order_total(); ?></td>
                    <td data-title="<?php _e('وضعیت', 'wcppg'); ?>"><?php echo wc_get_order_status_name($payment_order->get_status()); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>