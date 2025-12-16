<?php
defined( 'ABSPATH' ) || exit;
$order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
$order = $order_id ? wc_get_order($order_id) : null;

if ( !$order || $order->get_customer_id() !== get_current_user_id() ) {
    wc_print_notice( __( 'سفارش معتبری یافت نشد.', 'wcppg' ), 'error' );
    return;
}

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['submit_check_image']) && isset($_FILES['guarantee_check']) ) {
    if ( ! function_exists( 'media_handle_upload' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
    }

    $attachment_id = media_handle_upload('guarantee_check', 0);

    if (is_wp_error($attachment_id)) {
        wc_print_notice( $attachment_id->get_error_message(), 'error' );
    } else {
        $order->update_meta_data('_guarantee_check_image_id', $attachment_id);
        $order->add_order_note(__('چک تضمین توسط مشتری بارگذاری شد.', 'wcppg'));
        $order->save();
        wc_print_notice( __('چک شما با موفقیت بارگذاری شد. پس از بررسی، وضعیت سفارش شما به‌روز خواهد شد.', 'wcppg'), 'success' );
    }
}
?>
<div class="wcppg-check-upload-form">
    <h3><?php printf( __( 'بارگذاری چک تضمین برای سفارش #%s', 'wcppg' ), $order->get_order_number() ); ?></h3>
    <p><?php _e('لطفاً چک تضمین را طبق دستورالعمل زیر پر کرده و تصویر واضحی از آن را بارگذاری نمایید.', 'wcppg'); ?></p>
    
    <div class="wcppg-instructions">
        <h4><?php _e('دستورالعمل پر کردن چک', 'wcppg'); ?></h4>
        <ul>
            <li><?php printf( __( 'در وجه: %s', 'wcppg' ), 'نام شرکت شما' ); ?></li>
            <li><?php printf( __( 'مبلغ چک: %s', 'wcppg' ), wc_price( $order->get_meta('_wcppg_original_total') * 0.75 ) ); ?></li>
            <li><?php _e('تاریخ چک: بدون تاریخ', 'wcppg'); ?></li>
            <li><?php _e('امضای صاحب چک الزامی است.', 'wcppg'); ?></li>
        </ul>
    </div>

    <form method="post" enctype="multipart/form-data">
        <p class="form-row">
            <label for="guarantee_check"><?php _e('انتخاب تصویر چک:', 'wcppg'); ?></label>
            <input type="file" class="input-text" name="guarantee_check" id="guarantee_check" required accept="image/*">
        </p>
        <p class="form-row">
            <button type="submit" class="button" name="submit_check_image"><?php _e('ارسال و ثبت نهایی', 'wcppg'); ?></button>
        </p>
    </form>
</div>