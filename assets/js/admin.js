jQuery(function($) {
    // Admin-side JS for approving the check.
    // Use event delegation for elements inside the meta box, as it might be loaded via AJAX.
    $(document.body).on('click', '.wcppg-approve-check', function(e) {
        e.preventDefault();
        var button = $(this);

        if (confirm('آیا از تایید این چک و فعال‌سازی اقساط مطمئن هستید؟')) {
            button.prop('disabled', true).text('در حال پردازش...');
            $.ajax({
                type: 'POST',
                url: ajaxurl, // ajaxurl is a global variable in WordPress admin
                data: {
                    action: 'wcppg_approve_check',
                    order_id: button.data('order-id'),
                    nonce: button.data('nonce')
                },
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        button.prop('disabled', false).text('تایید چک');
                        alert('خطایی رخ داد: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function() {
                    button.prop('disabled', false).text('تایید چک');
                    alert('خطای ارتباطی با سرور رخ داد.');
                }
            });
        }
    });
});