jQuery(function($) {
    // Ensure this runs only on the checkout page
    if ($('form.woocommerce-checkout').length) {
        
        // Function to insert the form. This is robust against AJAX refreshes.
        function insertPaymentOptionsForm() {
            // Check if the form has not been inserted yet and if the HTML is available
            if ($('#wcppg-options-container').length === 0 && wcppg_params.form_html) {
                // The most reliable place to insert is before the #payment container
                $('#payment').before(wcppg_params.form_html);
            }
        }

        // Run on initial page load and after any AJAX update on the checkout page.
        // Using 'init_checkout' and 'updated_checkout' ensures it works in all scenarios.
        $(document.body).on('init_checkout updated_checkout', function() {
            insertPaymentOptionsForm();
        });

        // Event handler for changing the payment plan. Use delegation for dynamic elements.
        $(document.body).on('change', 'input[name="wcppg_payment_plan"]', function() {
            var selectedPlan = $(this).val();

            // Show loading overlay on the order review section while updating.
            $('#order_review').block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });

            $.ajax({
                type: 'POST',
                url: wcppg_params.ajax_url,
                data: {
                    action: 'wcppg_update_payment_option',
                    plan: selectedPlan,
                    nonce: wcppg_params.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Trigger WooCommerce to refresh the order summary and payment methods.
                        $(document.body).trigger('update_checkout');
                    } else {
                        $('#order_review').unblock();
                    }
                },
                error: function() {
                    $('#order_review').unblock();
                }
            });
			
			$('.blockOverlay').each(function() {
                $(this).css('display','none');
                $(this).addClass('d-none');
            });
        });
    }
});