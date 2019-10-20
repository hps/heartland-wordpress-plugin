(function($) {
    $(function() {
        var list = document.getElementsByTagName('script');
       
        var tag = $('#secureSubmitForm');
        var pk = tag.data('public-key');
        var url = tag.data('post-url');
        var prefix = tag.data('prefix');
        
        $('#' + prefix + '-securesubmit-button').bind('click', handleSubmit);
        
        function handleSubmit() {
            hps.tokenize({
                data: {
                    public_key: pk,
                    number: $('#' + prefix + 'card_number').val().replace(/\D/g, ''),
                    cvc: $('#' + prefix + 'card_cvc').val(),
                    exp_month: $('#' + prefix + 'exp_month').val(),
                    exp_year: $('#' + prefix + 'exp_year').val()
                },
                success: function(response) {
                    secureSubmitResponseHandler(response);
                },
                error: function(response) {
                    secureSubmitResponseHandler(response);
                }
            });
        };
        
        function secureSubmitResponseHandler(response) {
            if(response.message) {
                alert(response.message);
            } else {
                $('#' + prefix + 'securesubmit_token').val(response.token_value);
                chargeToken();
            }
        }
        
        function chargeToken() {
            var data = {
                'action': 'ssd_submit_payment',
                'securesubmit_token': $('#' + prefix + '_securesubmit_token').val(),
                'billing_firstname': $('#' + prefix + '_billing_firstname').val(),
                'billing_lastname': $('#' + prefix + '_billing_lastname').val(),
                'billing_address': $('#' + prefix + '_billing_address').val(),
                'billing_city': $('#' + prefix + '_billing_city').val(),
                'billing_state': $('#' + prefix + '_billing_state').val(),
                'billing_zip': $('#' + prefix + '_billing_zip').val(),
                'billing_email': $('#' + prefix + '_billing_email').val(),
                'shipping_firstname': $('#' + prefix + '_shipping_firstname').val(),
                'shipping_lastname': $('#' + prefix + '_shipping_lastname').val(),
                'shipping_address': $('#' + prefix + '_shipping_address').val(),
                'shipping_city': $('#' + prefix + '_shipping_city').val(),
                'shipping_state': $('#' + prefix + '_shipping_state').val(),
                'shipping_zip': $('#' + prefix + '_shipping_zip').val(),
                'same_as_billing': $('#' + prefix + '_same_as_billing').val(),
                'donation_amount': $('#' + prefix + '_donation_amount').val(),
                'product_id': $('#' + prefix + '_product_id').val()
            };
            
            $('#' + prefix + '-securesubmit-button').hide();
            
            $.post(url, data, function(response) {
                if (response.indexOf("successful") >= 0)
                {
                    alert(response);
                    window.location = '/';
                }
                else
                {
                    $('#' + prefix + '-donate-response').html(response);
                    $('#securesubmit-button').show();
                }
            });
        }
    });
})(jQuery);
