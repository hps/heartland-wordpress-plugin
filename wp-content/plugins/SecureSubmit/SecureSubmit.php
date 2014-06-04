<?php
/*
Plugin Name: WP SecureSubmit
Plugin URI: https://developer.heartlandpaymentsystems.com/SecureSubmit
Description: Heartland Payment Systems SecureSubmit Plugin
Author: Mark Hagan
Version: 1.0.0
Author URI: https://developer.heartlandpaymentsystems.com/SecureSubmit
*/

$secureSubmit = new SecureSubmit();

class SecureSubmit {
    public $dir;
    public $url;
    public $options;
    
    function __construct() {
        $this->dir = (string) dirname(__FILE__);
        
        if ( ! class_exists('HpsConfiguration' ) ) {
            require_once($this->dir . '/lib/Hps.php');
        }

        $this->url = plugins_url('securesubmit');
        $this->options = get_option('securesubmit_options');

        // assign defaults
        if (!isset($this->options['secret_key'])) {
            $this->options['secret_key'] = '';
        }
        if (!isset($this->options['public_key'])) {
            $this->options['public_key'] = '';
        }
        if (!isset($this->options['payment_email'])) {
            $this->options['payment_email'] = '';
        }

        add_action('init', array($this, 'init'));
        add_action('wp_ajax_ssd_save_options', array($this, 'save_options'));
        add_action('wp_ajax_ssd_submit_payment', array($this, 'submit_payment'));
        add_action('wp_ajax_nopriv_ssd_submit_payment', array($this, 'submit_payment'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_shortcode('securesubmit', array($this, 'shortcode'));
    }
    
    function init() {
        wp_enqueue_script('jquery');
        
        if(!session_id())
            session_start();
    }
    
    function admin_menu() {
        add_options_page(
            'SecureSubmit',
            'SecureSubmit',
            'manage_options',
            'securesubmit-donations',
            array($this, 'options_page')
        );
    }
    
    function options_page() {
    ?>
    <script>
    (function($) {
        $(function() {
            $('#submit').click(function() {
                $('#message').hide();
                var data = {
                    'action': 'ssd_save_options',
                    'secret_key': $('#ssd_secret_key').val(),
                    'public_key': $('#ssd_public_key').val(),
                    'payment_email': $('#ssd_payment_email').val()
                };

                $.post(ajaxurl, data, function(response) {
                    $('#message p').html(response);
                    $('#message').show();
                });
            });
        });
    })(jQuery);
    </script>

    <div class="wrap">
        <h2>SecureSubmit Settings</h2>
        <div id="message" class="updated hidden"><p></p></div>
        <h3>API Credentials</h3>
        <p><a href="https://developer.heartlandpaymentsystems.com/SecureSubmit/Account/" target="_blank">Click here</a> to get your SecureSubmit API keys!</p>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">Secret Key</th>
                    <td><input type="text" id="ssd_secret_key" class="regular-text" value="<?php echo esc_attr($this->options['secret_key']); ?>" />
                </tr>
                <tr>
                    <th scope="row">Public Key</th>
                    <td><input type="text" id="ssd_public_key" class="regular-text" value="<?php echo esc_attr($this->options['public_key']); ?>" />
                </tr>
            </tbody>
        </table>
        <h3>Email Options</h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">Payment Email</th>
                    <td><input type="text" id="ssd_payment_email" class="regular-text" value="<?php echo esc_attr($this->options['payment_email']); ?>" />
                </tr>
            </tbody>
        </table>
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
        </p>
    </div>
    <?php
    }
    
    function shortcode($atts) {
        ob_start();
        
        if( !session_id())
            session_start();

        $buttonText = isset($atts['buttontext']) ? $atts['buttontext'] : 'Make Donation';

        if (isset($atts['requirebilling']) && $atts['requirebilling'] === 'false')
            $requireBilling = false;
        else
            $requireBilling = true;
            
        if (isset($atts['requireshipping']) && $atts['requireshipping'] === 'true')
            $requireShipping = 'true';
        else
            $requireShipping = 'false';

        if (isset($atts['modal']) && $atts['modal'] === 'true')
            $modal = true;
        else
            $modal = false;

        //Check for additional_info fields
        $pattern = '/(^additional_info[1-9]$|additional_info$)/';
        $attsKeys = array_keys($atts);
        $additionalFields = preg_grep($pattern,$attsKeys);

        //Check for additional Field types
        $typePattern = '/_type$/';
        $additionalFieldTypes = preg_grep($typePattern,$attsKeys);

        $productid = isset($atts['productid']) ? $atts['productid'] : '';
        $productname = isset($atts['productname']) ? $atts['productname'] : '';
        
        if (empty($productid)) {
            $prefix = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
        } else {
            $prefix = $productid;
        }

        update_option('secure_submit_'.$productid,$atts);
    ?>
    
    <?php if ($modal) { ?>
        <form id="<?php echo $prefix; ?>_form">
        </form>
        <script src="<?php echo plugins_url( 'js/secure.submit-1.0.2.js', __FILE__ ); ?>"></script>
        <script language="javascript" type="text/javascript">
            var requireShipping = <?php echo $requireShipping; ?>;
            var requireAdditionalInfo = <?php echo (count($additionalFields)>0 ? true : false); ?>
            
            jQuery.ajaxSetup({ cache: true });
            if (jQuery('#sss').length == 0)
                jQuery('head').append(jQuery('<link rel="stylesheet" type="text/css" />').attr('href', '<?php echo plugins_url( 'assets/checkout.css', __FILE__ ); ?>').attr('id', 'sss' ));
        
            var trigger_button = jQuery("<div class='pay-button button-main'><a href='#Purchase' id='pay_now'><?php echo $buttonText; ?></a><div class='pay-button-border'>&nbsp;</div></div>");
            jQuery('#<?php echo $prefix; ?>_form').append(trigger_button);
            
            jQuery('#pay_now').bind('click', function() {
                trigger_payment();
            });
            
        // BUILD CONTROLS
            var modal_html = "<div id='modal-background'></div><div id='modal-content'>";
            modal_html += "<a class='boxclose modal-close' id='boxclose'>&times;</a>";
            
            // HEADER
            modal_html += "<div id='modal-header'>";
            
            modal_html += "<div style='float: left;'>";
            <?php if (!isset($atts["productimage"])) { ?>
            modal_html += "<img src='<?php echo plugins_url( 'assets/donation.png', __FILE__ ); ?>' class='checkout-product-image'>";
            <?php } else { ?>
            modal_html += "<img src='<?php echo $atts["productimage"]; ?>' class='checkout-product-image'>";    
            <?php } ?>
            modal_html += "</div>";
            modal_html += "<input type='hidden' name='action' id='action' value='ssd_submit_payment'/>";
            modal_html += "<input type='hidden' name='product_sku' id='product_sku' value='<?php echo $atts['productid']; ?>'/>";
            //modal_html += "<div class='checkout-merchant-name'>" + merchant + "</div>";
            modal_html += "<div class='checkout-product-name'><?php echo $atts['productname']; ?></div>";
            
            if ('<?php echo $atts['amount']; ?>' != '') {
                modal_html += "<div class='checkout-price'>$<?php echo $atts['amount']; ?></div>";
            } else {
                modal_html += "<div class='donation-price'>Donation Amount<br />$&nbsp;<input type='text' name='donation_amount' id='donation_amount' class='checkout-input donation-field' placeholder='100.00'></div>";
            }
            
            modal_html += "</div>";
            
            modal_html += "<div id='modal-body'>";
            
            // BILLING BODY
            var billing_html = "<div id='billing_panel'>";
            billing_html += "<div class='checkout-card-information'>Billing Information</div>";
            billing_html += "<div class='card-number'><input type='text' name='cardholder_name' id='cardholder_name' class='checkout-input checkout-card' placeholder='Cardholder Name'></div>";
            billing_html += "<div class='card-number'><input type='text' name='cardholder_address' id='cardholder_address' class='checkout-input checkout-card' placeholder='Address'></div>";
            billing_html += "<div class='card-number'>";
            billing_html += "<input type='text' name='cardholder_city' id='cardholder_city' class='checkout-input city-field' placeholder='City'>";
            billing_html += "<select name='cardholder_state' id='cardholder_state' class='checkout-input state-field'><option value='AL'>AL</option><option value='AK'>AK</option><option value='AZ'>AZ</option><option value='AR'>AR</option><option value='CA'>CA</option><option value='CO'>CO</option><option value='CT'>CT</option><option value='DE'>DE</option><option value='DC'>DC</option><option value='FL'>FL</option><option value='GA'>GA</option><option value='HI'>HI</option><option value='ID'>ID</option><option value='IL'>IL</option><option value='IN'>IN</option><option value='IA'>IA</option><option value='KS'>KS</option><option value='KY'>KY</option><option value='LA'>LA</option><option value='ME'>ME</option><option value='MD'>MD</option><option value='MA'>MA</option><option value='MI'>MI</option><option value='MN'>MN</option><option value='MS'>MS</option><option value='MO'>MO</option><option value='MT'>MT</option><option value='NE'>NE</option><option value='NV'>NV</option><option value='NH'>NH</option><option value='NJ'>NJ</option><option value='NM'>NM</option><option value='NY'>NY</option><option value='NC'>NC</option><option value='ND'>ND</option><option value='OH'>OH</option><option value='OK'>OK</option><option value='OR'>OR</option><option value='PA'>PA</option><option value='RI'>RI</option><option value='SC'>SC</option><option value='SD'>SD</option><option value='TN'>TN</option><option value='TX'>TX</option><option value='UT'>UT</option><option value='VT'>VT</option><option value='VA'>VA</option><option value='WA'>WA</option><option value='WV'>WV</option><option value='WI'>WI</option><option value='WY'>WY</option></select>";
            billing_html += "<input type='text' name='cardholder_zip' id='cardholder_zip' class='checkout-input zip-field' placeholder='Zip'>";
            billing_html += "</div>";

            // Additional Info BODY
            var additional_html = "<div id='additional_panel'>";
            additional_html += "<div class='back-button'><a href='#billing' id='additional_back'>back</a></div>";
            additional_html += "<div class='checkout-card-information'>Additional Information</div>";
            <?php
                if(count($additionalFields)>0){
                    foreach($additionalFields as $key=>$value){
                    print "value = ".array_key_exists($value.'_type',$additionalFieldTypes);
                        if(array_key_exists($value.'_type',$additionalFieldTypes)){
                            echo 'additional_html += "<div class=\'card-number\' ><input name=\''.$atts[$value].'\' type=\''.$atts[$value.'_type'].'\' id=\''.$atts[$value].'\' class=\'checkout-input checkout-card\' placeholder=\''.$atts[$value].'\'></div>";';
                        }
                        echo 'additional_html += "<div class=\'card-number\' ><input name=\''.$atts[$value].'\' type=\'text\' id=\''.$atts[$value].'\' class=\'checkout-input checkout-card\' placeholder=\''.$atts[$value].'\'></div>";';
                    }
                }
            ?>
            additional_html += "<div class='pay-button button-next'><a href='#Purchase' id='additional_next_button'>Next</a><div class='pay-button-border'>&nbsp;</div></div>";
            additional_html += "<div class='powered_by'><img src='<?php echo plugins_url( 'assets/heart.png', __FILE__ ); ?>' /></div>";
            additional_html += "</div>";


            // TODO: Check if this is checked to skip the shipping screen...
            if (requireShipping) {
                billing_html += "<div class='same_shipping'><input name='shipping_same' type='checkbox' id='shipping_same'>&nbsp;<label for='shipping_same'>Shipping Same As Billing</label></div>";
            }

            billing_html += "<div class='pay-button button-next'><a href='#Purchase' id='billing_next_button'>Next</a><div class='pay-button-border'>&nbsp;</div></div>";
            billing_html += "<div class='powered_by'><img src='<?php echo plugins_url( 'assets/heart.png', __FILE__ ); ?>' /></div>";
            billing_html += "</div>";
            
            // SHIPPING BODY
            var shipping_html = "<div id='shipping_panel'>";
            shipping_html += "<div class='back-button'><a href='#billing' id='shipping_back'>back</a></div>";
            shipping_html += "<div class='checkout-card-information'>Shipping Information</div>";
            shipping_html += "<div class='card-number'><input name='shipping_name' type='text' id='shipping_name' class='checkout-input checkout-card' placeholder='Shipping Name'></div>";
            shipping_html += "<div class='card-number'><input name='shipping_address' type='text' id='shipping_address' class='checkout-input checkout-card' placeholder='Address'></div>";
            shipping_html += "<div class='card-number'>";
            shipping_html += "<input type='text' name='shipping_city' id='shipping_city' class='checkout-input city-field' placeholder='City'>";
            shipping_html += "<select id='shipping_state' name='shipping_state' class='checkout-input state-field'><option value='AL'>AL</option><option value='AK'>AK</option><option value='AZ'>AZ</option><option value='AR'>AR</option><option value='CA'>CA</option><option value='CO'>CO</option><option value='CT'>CT</option><option value='DE'>DE</option><option value='DC'>DC</option><option value='FL'>FL</option><option value='GA'>GA</option><option value='HI'>HI</option><option value='ID'>ID</option><option value='IL'>IL</option><option value='IN'>IN</option><option value='IA'>IA</option><option value='KS'>KS</option><option value='KY'>KY</option><option value='LA'>LA</option><option value='ME'>ME</option><option value='MD'>MD</option><option value='MA'>MA</option><option value='MI'>MI</option><option value='MN'>MN</option><option value='MS'>MS</option><option value='MO'>MO</option><option value='MT'>MT</option><option value='NE'>NE</option><option value='NV'>NV</option><option value='NH'>NH</option><option value='NJ'>NJ</option><option value='NM'>NM</option><option value='NY'>NY</option><option value='NC'>NC</option><option value='ND'>ND</option><option value='OH'>OH</option><option value='OK'>OK</option><option value='OR'>OR</option><option value='PA'>PA</option><option value='RI'>RI</option><option value='SC'>SC</option><option value='SD'>SD</option><option value='TN'>TN</option><option value='TX'>TX</option><option value='UT'>UT</option><option value='VT'>VT</option><option value='VA'>VA</option><option value='WA'>WA</option><option value='WV'>WV</option><option value='WI'>WI</option><option value='WY'>WY</option></select>";
            shipping_html += "<input type='text' name='shipping_zip' id='shipping_zip' class='checkout-input zip-field' placeholder='Zip'>";
            shipping_html += "</div>";
            shipping_html += "<div class='pay-button button-next'><a href='#Purchase' id='shipping_next_button'>Next</a><div class='pay-button-border'>&nbsp;</div></div>";
            shipping_html += "<div class='powered_by'><img src='<?php echo plugins_url( 'assets/heart.png', __FILE__ ); ?>' /></div>";
            shipping_html += "</div>";
            
            // CARD BODY
            var card_html = "<div id='card_panel'>";
            card_html += "<div class='back-button'><a href='#shipping' id='card_back'>back</a></div>";
            card_html += "<div class='checkout-card-information'>Card Information</div>";
            card_html += "<div class='card-number'><input type='text' id='card_number' class='checkout-input checkout-card' placeholder='4111 - 1111 - 1111 - 1111'></div>";
            card_html += "<div class='card-exp'><input type='text' id='card_exp' class='checkout-exp' placeholder='MM/YY'></div>";
            card_html += "<div class='card-cvc'><input type='text' id='card_cvc' class='checkout-exp' placeholder='CVC'></div>";
            card_html += "<div class='clearfixcheckout'>&nbsp;</div>";
            card_html += "<div class='email-reciept'><input name='email_reciept' type='checkbox' id='email_reciept'>&nbsp;<label for='email_reciept'>Email Receipt</label></div>";
            card_html += "<div class='email-address'><input name='email_address' type='text' id='email_address' class='checkout-email' placeholder='myemail@email.com'></div>";
            card_html += "<div class='pay-button button-next'><a href='#Purchase' id='pay_button'><?php echo $buttonText; ?></a><div class='pay-button-border'>&nbsp;</div></div>";
            card_html += "<div class='powered_by'><img src='<?php echo plugins_url( 'assets/heart.png', __FILE__ ); ?>' /></div>";
            card_html += "</div>";

            // PROCESSING BODY
            var processing_html = "<div id='processing_panel'>";
            processing_html += "<div class='transaction-processing'>processing</div>";
            processing_html += "</div>";
            
            // FAILURE BODY
            var failure_html = "<div id='failure_panel'>";
            failure_html += "<div class='checkout-card-information'>Transaction Information</div>";
            failure_html += "<div class='transaction-error'>There was a problem while processing your card.</div>";
            failure_html += "<div class='pay-button button-next'><a href='#Purchase' id='retry_button'>Retry</a><div class='pay-button-border'>&nbsp;</div></div>";
            failure_html += "</div>";
            
            // SUCCESS BODY
            var success_html = "<div id='success_panel'>";
            success_html += "<div class='card-number'>Your Payment Was Successful!</div>";
            success_html += "</div>";
            
            modal_html += billing_html;
            modal_html += additional_html;
            modal_html += shipping_html;
            modal_html += card_html;
            modal_html += processing_html;
            modal_html += success_html;
            modal_html += failure_html;
            
            modal_html += "</div>"; // BODY
            modal_html += "</div>"; // content
            
        // ACTIONS
            function trigger_payment() {
                jQuery('#modal-content').remove();           // a little clean-up
                jQuery('#modal-background').remove();        // a little clean-up
               
                jQuery('#<?php echo $prefix; ?>_form').append(modal_html);  // there could be multiple forms, multiple buttons.
                jQuery("#modal-background").toggleClass("active");
                // show the first panel (billing)
                jQuery("#billing_panel").show();
                
                jQuery("#card_panel").hide();
                jQuery("#additional_panel").hide();
                jQuery("#shipping_panel").hide();
                jQuery("#processing_panel").hide();
                jQuery("#success_panel").hide();
                jQuery("#failure_panel").hide();
                
                jQuery("#modal-content").fadeIn(400);
                
                jQuery("#billing_next_button").on("click", function(event) {
                    jQuery("#billing_panel").hide();

                    if (requireAdditionalInfo){
                        jQuery("#additional_panel").fadeIn();
                    }else if (requireShipping) {
                        jQuery("#card_panel").hide();
                        jQuery("#shipping_panel").fadeIn();
                    } else {
                        jQuery("#shipping_panel").hide();
                        jQuery("#card_panel").fadeIn();
                    }
                });

                jQuery("#additional_next_button").on("click", function(event) {
                    jQuery("#billing_panel").hide();
                    jQuery("#additional_panel").hide();
                    if(requireShipping){
                        jQuery("#shipping_panel").fadeIn();
                    }else{
                        jQuery("#card_panel").fadeIn();
                    }
                });

                jQuery("#shipping_next_button").on("click", function(event) {
                    jQuery("#billing_panel").hide();
                    jQuery("#shipping_panel").hide();
                    jQuery("#card_panel").fadeIn();
                });
                
                jQuery("#retry_button").on("click", function(event) {
                    jQuery("#failure_panel").hide();
                    jQuery("#card_panel").fadeIn();
                });
                
                if (requireShipping) {
                    jQuery("#shipping_back").on("click", function(event) {
                        jQuery("#billing_panel").fadeIn();
                        jQuery("#shipping_panel").hide();
                        jQuery("#card_panel").hide();
                    });
                }
                
                jQuery("#card_back").on("click", function(event) {
                    jQuery("#billing_panel").hide();
                    
                    if (requireShipping) {
                        jQuery("#shipping_panel").show();
                        jQuery("#card_panel").hide();
                    } else {
                        jQuery("#billing_panel").show();
                        jQuery("#card_panel").hide();
                    }
                });
                
                jQuery("#pay_button").on("click", function(event) {
                    jQuery("#card_panel").hide();
                    jQuery("#processing_panel").show();
                    jQuery("#modal-launcher, #modal-background, .modal-close").unbind('click');
                    tokenize();
                });
                
                jQuery("#modal-launcher, #modal-background, .modal-close").click(function () {
                    jQuery('#modal-content').remove();           // a little clean-up
                    jQuery('#modal-background').remove();        // a little clean-up
                });
            }
            
            function tokenize() {
                var expirationparts = jQuery('#card_exp').val().split("/");
                var month = expirationparts[0];
                var year = expirationparts[1];
                var cardnumber = jQuery('#card_number').val().replace(/\D/g,''); // strip out non-numeric
                
                // we need the year as four-digits
                if (year && year.length === 2) {
                    year = '20' + year;
                }
                
                hps.tokenize({
                    data: {
                        public_key: '<?php echo esc_attr($this->options['public_key']); ?>',
                        number: cardnumber,
                        cvc: jQuery('#card_cvc').val(),
                        exp_month: month,
                        exp_year: year
                    },
                    success: function(response) {
                        secureSubmitResponseHandler(response);
                    },
                    error: function(response) {
                        secureSubmitResponseHandler(response);
                    }
                });
            }
            
            function secureSubmitResponseHandler(response) {
                if ( response.message ) {
                    jQuery("#processing_panel").hide();
                    jQuery("#failure_panel").show();
                    jQuery(".transaction-error").text(response.message);
                    
                    // allow the window to go away again
                    jQuery("#modal-launcher, #modal-background, .modal-close").bind('click', function () {
                        jQuery('#modal-content').remove();           // a little clean-up
                        jQuery('#modal-background').remove();        // a little clean-up
                    });
                } else {
                    jQuery('#securesubmit_token').remove();
                    var token_html = "<input type='hidden' id='securesubmit_token' name='securesubmit_token' value='" + response.token_value + "' />";
                    jQuery('#<?php echo $prefix; ?>_form').append(token_html);

                    do_post();
                }
            }
            
            function do_post() {
                var datastring = jQuery('#<?php echo $prefix; ?>_form').serialize();
                var url = "<?php echo admin_url('admin-ajax.php'); ?>";

                jQuery.post(url, datastring, function(response) {
                    if (response.indexOf("successful") >= 0)
                    {
                        jQuery("#processing_panel").hide();
                        jQuery("#success_panel").show();
                        
                        jQuery("#modal-launcher, #modal-background, .modal-close").bind('click', function () {
                            jQuery('#modal-content').remove();           // a little clean-up
                            jQuery('#modal-background').remove();        // a little clean-up
                        });
                    }
                    else
                    {
                        jQuery("#processing_panel").hide();
                        jQuery("#failure_panel").show();
                        jQuery(".transaction-error").text(response);
                        
                        // allow the window to go away again.
                        jQuery("#modal-launcher, #modal-background, .modal-close").bind('click', function () {
                            jQuery('#modal-content').remove();           // a little clean-up
                            jQuery('#modal-background').remove();        // a little clean-up
                        });
                    }
                });
            }
        </script>
    <?php }  else { ?>
    <input type="hidden" id="<?php echo $prefix; ?>_securesubmit_token" />
    <input type="hidden" id="<?php echo $prefix; ?>_product_id" value="<?php echo $productid; ?>" />

    <?php if ($requireBilling) { ?>
    <h3>Billing Information</h3>
    <table width="100%">
        <tr>
            <td width="200">First Name:</td>
            <td><input class="form-text" type="text" id="<?php echo $prefix; ?>_billing_firstname" /></td>
        </tr>
        <tr>
            <td>Last Name:</td>
            <td><input class="form-text" type="text" id="<?php echo $prefix; ?>_billing_lastname" /></td>
        </tr>
        <tr>
            <td>Email Address:</td>
            <td><input class="form-text" type="text" id="<?php echo $prefix; ?>_billing_email" /></td>
        </tr>
        <tr>
            <td>Address:</td>
            <td><input class="form-text" type="text" id="<?php echo $prefix; ?>_billing_address" /></td>
        </tr>
        <tr>
            <td>City:</td>
            <td><input class="form-text" type="text" id="<?php echo $prefix; ?>_billing_city" /></td>
        </tr>
        <tr>
            <td>State:</td>
            <td>
                <select id="<?php echo $prefix; ?>_billing_state">
                    <option value="AL">Alabama</option>
                    <option value="AK">Alaska</option>
                    <option value="AZ">Arizona</option>
                    <option value="AR">Arkansas</option>
                    <option value="CA">California</option>
                    <option value="CO">Colorado</option>
                    <option value="CT">Connecticut</option>
                    <option value="DE">Delaware</option>
                    <option value="FL">Florida</option>
                    <option value="GA">Georgia</option>
                    <option value="HI">Hawaii</option>
                    <option value="ID">Idaho</option>
                    <option value="IL">Illinois</option>
                    <option value="IN">Indiana</option>
                    <option value="IA">Iowa</option>
                    <option value="KS">Kansas</option>
                    <option value="KY">Kentucky</option>
                    <option value="LA">Louisiana</option>
                    <option value="ME">Maine</option>
                    <option value="MD">Maryland</option>
                    <option value="MA">Massachusetts</option>
                    <option value="MI">Michigan</option>
                    <option value="MN">Minnesota</option>
                    <option value="MS">Mississippi</option>
                    <option value="MO">Missouri</option>
                    <option value="MT">Montana</option>
                    <option value="NE">Nebraska</option>
                    <option value="NV">Nevada</option>
                    <option value="NH">New Hampshire</option>
                    <option value="NJ">New Jersey</option>
                    <option value="NM">New Mexico</option>
                    <option value="NY">New York</option>
                    <option value="NC">North Carolina</option>
                    <option value="ND">North Dakota</option>
                    <option value="OH">Ohio</option>
                    <option value="OK">Oklahoma</option>
                    <option value="OR">Oregon</option>
                    <option value="PA">Pennsylvania</option>
                    <option value="RI">Rhode Island</option>
                    <option value="SC">South Carolina</option>
                    <option value="SD">South Dakota</option>
                    <option value="TN">Tennessee</option>
                    <option value="TX">Texas</option>
                    <option value="UT">Utah</option>
                    <option value="VT">Vermont</option>
                    <option value="VA">Virginia</option>
                    <option value="WA">Washington</option>
                    <option value="WV">West Virginia</option>
                    <option value="WI">Wisconsin</option>
                    <option value="WY">Wyoming</option>
                </select>
            </td>
        </tr>
        <tr>
            <td>Zip Code:</td>
            <td><input class="form-text" type="text" id="<?php echo $prefix; ?>_billing_zip" /></td>
        </tr>
    </table>
    <?php } ?>
    
    <?php if ($requireShipping) { ?>
    <h3>Shipping Information</h3>
    <input type="checkbox" id="<?php echo $prefix; ?>_same_as_billing" value="1" checked="true" onclick="jQuery('#shipping_table').toggle();">&nbsp;<label for="same_as_billing">Same as Billing Address</label>
    <table width="100%" style="display:none;" id="shipping_table">
        <tr>
            <td width="200">First Name:</td>
            <td><input class="form-text" type="text" id="<?php echo $prefix; ?>_shipping_firstname" /></td>
        </tr>
        <tr>
            <td>Last Name:</td>
            <td><input class="form-text" type="text" id="<?php echo $prefix; ?>_shipping_lastname" /></td>
        </tr>
        <tr>
            <td>Address:</td>
            <td><input class="form-text" type="text" id="<?php echo $prefix; ?>_shipping_address" /></td>
        </tr>
        <tr>
            <td>City:</td>
            <td><input class="form-text" type="text" id="<?php echo $prefix; ?>_shipping_city" /></td>
        </tr>
        <tr>
            <td>State:</td>
            <td>
                <select id="<?php echo $prefix; ?>_shipping_state">
                    <option value="AL">Alabama</option>
                    <option value="AK">Alaska</option>
                    <option value="AZ">Arizona</option>
                    <option value="AR">Arkansas</option>
                    <option value="CA">California</option>
                    <option value="CO">Colorado</option>
                    <option value="CT">Connecticut</option>
                    <option value="DE">Delaware</option>
                    <option value="FL">Florida</option>
                    <option value="GA">Georgia</option>
                    <option value="HI">Hawaii</option>
                    <option value="ID">Idaho</option>
                    <option value="IL">Illinois</option>
                    <option value="IN">Indiana</option>
                    <option value="IA">Iowa</option>
                    <option value="KS">Kansas</option>
                    <option value="KY">Kentucky</option>
                    <option value="LA">Louisiana</option>
                    <option value="ME">Maine</option>
                    <option value="MD">Maryland</option>
                    <option value="MA">Massachusetts</option>
                    <option value="MI">Michigan</option>
                    <option value="MN">Minnesota</option>
                    <option value="MS">Mississippi</option>
                    <option value="MO">Missouri</option>
                    <option value="MT">Montana</option>
                    <option value="NE">Nebraska</option>
                    <option value="NV">Nevada</option>
                    <option value="NH">New Hampshire</option>
                    <option value="NJ">New Jersey</option>
                    <option value="NM">New Mexico</option>
                    <option value="NY">New York</option>
                    <option value="NC">North Carolina</option>
                    <option value="ND">North Dakota</option>
                    <option value="OH">Ohio</option>
                    <option value="OK">Oklahoma</option>
                    <option value="OR">Oregon</option>
                    <option value="PA">Pennsylvania</option>
                    <option value="RI">Rhode Island</option>
                    <option value="SC">South Carolina</option>
                    <option value="SD">South Dakota</option>
                    <option value="TN">Tennessee</option>
                    <option value="TX">Texas</option>
                    <option value="UT">Utah</option>
                    <option value="VT">Vermont</option>
                    <option value="VA">Virginia</option>
                    <option value="WA">Washington</option>
                    <option value="WV">West Virginia</option>
                    <option value="WI">Wisconsin</option>
                    <option value="WY">Wyoming</option>
                </select>
            </td>
        </tr>
        <tr>
            <td>Shipping Zip Code:</td>
            <td><input class="form-text" type="text" id="<?php echo $prefix; ?>_shipping_zip" /></td>
        </tr>
    </table>
    <?php } ?>
    
    <?php if (empty($productid)) { ?>
    <h3>Donation Information</h3>
    <?php } else { ?>
    <h3>Payment Information</h3>
    <?php } ?>
    <table width="100%">
        <tr>
            <td width="200">Card Number:</td>
            <td><input class="form-text" type="text" id="<?php echo $prefix; ?>_card_number" /></td>
        </tr>
        <tr>
            <td>Expiration:</td>
            <td colspan="2">
                <select id="<?php echo $prefix; ?>_exp_month">
                    <option value="01">01</option>
                    <option value="02">02</option>
                    <option value="03">03</option>
                    <option value="04">04</option>
                    <option value="05">05</option>
                    <option value="06">06</option>
                    <option value="07">07</option>
                    <option value="08">08</option>
                    <option value="09">09</option>
                    <option value="10">10</option>
                    <option value="11">11</option>
                    <option value="12">12</option>
                </select>
/
                <select id="<?php echo $prefix; ?>_exp_year">
                    <option value="2014">2014</option>
                    <option value="2015">2015</option>
                    <option value="2016">2016</option>
                    <option value="2017">2017</option>
                    <option value="2018">2018</option>
                    <option value="2019">2019</option>
                    <option value="2020">2020</option>
                    <option value="2021">2021</option>
                    <option value="2022">2022</option>
                    <option value="2023">2023</option>
                    <option value="2024">2024</option>
                </select>
            </td>
        </tr>
        <tr>
            <td>Card CVC:</td>
            <td><input class="form-text" type="text" id="<?php echo $prefix; ?>_card_cvc" style="width: 45px;" /></td>
        </tr>
        <?php if (empty($productid)) { ?>
        <tr>
            <td>Amount:</td>
            <td>$<input class="form-text" type="text" value="100.00" id="<?php echo $prefix; ?>_donation_amount" /></td>
        </tr>
        <?php } ?>
        <tr>
            <td colspan="2">
                <div id="<?php echo $prefix; ?>-donate-response"></div>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="text-align: center;">
                <button id="<?php echo $prefix; ?>-securesubmit-button" class="button-primary"><?php echo $buttonText; ?></button>
                
                <?php if ($modal) { ?>
                <button id="a<?php echo $prefix; ?>-modal-launcher" class="button-secondary">cancel</button>
                <?php } ?>
            </td>
        </tr>
    </table>
    
<?php } ?>
    
    <script type="text/javascript">
        (function($) {
            $(function() {
                $("#a<?php echo $prefix; ?>-modal-launcher, #a<?php echo $prefix; ?>-modal-background, #a<?php echo $prefix; ?>-modal-close").click(function () {
                    $("#a<?php echo $prefix; ?>-modal-content,#a<?php echo $prefix; ?>-modal-background").toggleClass("active");
                });

                var pk = '<?php echo esc_attr($this->options['public_key']); ?>';
                var url = "<?php echo admin_url('admin-ajax.php'); ?>";
                
                $('#<?php echo $prefix; ?>-securesubmit-button').bind('click', a<?php echo $prefix; ?>_handleSubmit);
                
                function a<?php echo $prefix; ?>_handleSubmit() {
                    hps.tokenize({
                        data: {
                            public_key: pk,
                            number: $('#<?php echo $prefix; ?>_card_number').val(),
                            cvc: $('#<?php echo $prefix; ?>_card_cvc').val(),
                            exp_month: $('#<?php echo $prefix; ?>_exp_month').val(),
                            exp_year: $('#<?php echo $prefix; ?>_exp_year').val()
                        },
                        success: function(response) {
                            a<?php echo $prefix; ?>_secureSubmitResponseHandler(response);
                        },
                        error: function(response) {
                            a<?php echo $prefix; ?>_secureSubmitResponseHandler(response);
                        }
                    });
                };
                
                function a<?php echo $prefix; ?>_secureSubmitResponseHandler(response) {
                    if(response.message) {
                        alert(response.message);
                    } else {
                        $('#<?php echo $prefix; ?>_securesubmit_token').val(response.token_value);
                        a<?php echo $prefix; ?>_chargeToken();
                    }
                }
                
                function a<?php echo $prefix; ?>_chargeToken() {
                    var data = {
                        'action': 'ssd_submit_payment',
                        'securesubmit_token': $('#<?php echo $prefix; ?>_securesubmit_token').val(),
                        'billing_firstname': $('#<?php echo $prefix; ?>_billing_firstname').val(),
                        'billing_lastname': $('#<?php echo $prefix; ?>_billing_lastname').val(),
                        'billing_address': $('#<?php echo $prefix; ?>_billing_address').val(),
                        'billing_city': $('#<?php echo $prefix; ?>_billing_city').val(),
                        'billing_state': $('#<?php echo $prefix; ?>_billing_state').val(),
                        'billing_zip': $('#<?php echo $prefix; ?>_billing_zip').val(),
                        'billing_email': $('#<?php echo $prefix; ?>_billing_email').val(),
                        'shipping_firstname': $('#<?php echo $prefix; ?>_shipping_firstname').val(),
                        'shipping_lastname': $('#<?php echo $prefix; ?>_shipping_lastname').val(),
                        'shipping_address': $('#<?php echo $prefix; ?>_shipping_address').val(),
                        'shipping_city': $('#<?php echo $prefix; ?>_shipping_city').val(),
                        'shipping_state': $('#<?php echo $prefix; ?>_shipping_state').val(),
                        'shipping_zip': $('#<?php echo $prefix; ?>_shipping_zip').val(),
                        'same_as_billing': $('#<?php echo $prefix; ?>_same_as_billing').val(),
                        'donation_amount': $('#<?php echo $prefix; ?>_donation_amount').val(),
                        'product_id': $('#<?php echo $prefix; ?>_product_id').val()
                    };
                    
                    $('#<?php echo $prefix; ?>-securesubmit-button').hide();
                    
                    $.post(url, data, function(response) {
                        if (response.indexOf("successful") >= 0)
                        {
                            alert(response);
                            window.location = '/';
                        }
                        else
                        {
                            $('#<?php echo $prefix; ?>-donate-response').html(response);
                            $('#<?php echo $prefix; ?>-securesubmit-button').show();
                        }
                    });
                }
            });
        })(jQuery);
    </script>
    
    
    <script src="<?php echo plugins_url( 'js/secure.submit-1.0.2.js', __FILE__ ); ?>"></script>
    <?php
        return ob_get_clean();
    }
    
    function save_options() {
        $data = array(
            'secret_key' => $_POST['secret_key'],
            'public_key' => $_POST['public_key'],
            'payment_email' => $_POST['payment_email'],
        );
        update_option('securesubmit_options', $data);
        echo 'Settings saved.';
        exit;
    }
    
    function submit_payment() {
        $body = "";
        $secureToken = isset($_POST['securesubmit_token']) ? $_POST['securesubmit_token'] : '';
        $amount = isset($_POST['donation_amount']) ? $_POST['donation_amount'] : 0;
        
        if ($amount === 0)
        {
            if( !session_id())
                session_start();
                
            $atts = get_option('secure_submit_'. $_POST['product_id']); 
            
            $amount = isset($atts['amount']) ? $atts['amount'] : 0;
            $memo = isset($atts['memo']) ? $atts['memo'] : 0;
            $productid = isset($atts['productid']) ? $atts['productid'] : 0;
            $productname = isset($atts['productname']) ? $atts['productname'] : 0;
        }
        
        $body .= 'Thank you for your payment of $' . $amount . '!';
        if (!empty($productname)) {
            $body .= '<h3>Product Information</h3>';
            $body .= 'Product Id: ' . $productid . '<br/>';
            $body .= 'Product Name: ' . $productname . '<br/>';
        }
        
        // modal
        if (isset($_POST['cardholder_name'])) {
            list($first, $middle, $last) = split(' ', $_POST['cardholder_name']);
            
            if (isset($last)) {
                $billing_firstname = $first;
                $billing_lastname = $last;
            } else {
                $billing_firstname = $first;
                $billing_lastname = $middle;                
            }
            
            list($shipfirst, $shipmiddle, $shiplast) = split(' ', $_POST['shipping_name']);
            
            if (isset($last)) {
                $shipping_firstname = $shipfirst;
                $shipping_lastname = $shiplast;
            } else {
                $shipping_firstname = $shipfirst;
                $shipping_lastname = $shipmiddle;                
            }
            
            $billing_address = isset($_POST['cardholder_address']) ? $_POST['cardholder_address'] : '';
            $billing_city= isset($_POST['cardholder_city']) ? $_POST['cardholder_city'] : '';
            $billing_state = isset($_POST['cardholder_state']) ? $_POST['cardholder_state'] : '';
            $billing_zip = isset($_POST['cardholder_zip']) ? $_POST['cardholder_zip'] : '';
            $billing_email = isset($_POST['email_address']) ? $_POST['email_address'] : '';
            
            $shipping_address = isset($_POST['shipping_address']) ? $_POST['shipping_address'] : '';
            $shipping_city= isset($_POST['shipping_city']) ? $_POST['shipping_city'] : '';
            $shipping_state = isset($_POST['shipping_state']) ? $_POST['shipping_state'] : '';
            $shipping_zip = isset($_POST['shipping_zip']) ? $_POST['shipping_zip'] : '';
        } else {           
            $billing_firstname = isset($_POST['billing_firstname']) ? $_POST['billing_firstname'] : '';
            $billing_lastname = isset($_POST['billing_lastname']) ? $_POST['billing_lastname'] : '';
            $billing_address = isset($_POST['billing_address']) ? $_POST['billing_address'] : '';
            $billing_city= isset($_POST['billing_city']) ? $_POST['billing_city'] : '';
            $billing_state = isset($_POST['billing_state']) ? $_POST['billing_state'] : '';
            $billing_zip = isset($_POST['billing_zip']) ? $_POST['billing_zip'] : '';
            $billing_email = isset($_POST['billing_email']) ? $_POST['billing_email'] : '';
            
            $shipping_firstname = isset($_POST['shipping_firstname']) ? $_POST['shipping_firstname'] : '';
            $shipping_lastname = isset($_POST['shipping_lastname']) ? $_POST['shipping_lastname'] : '';
            $shipping_address = isset($_POST['shipping_address']) ? $_POST['shipping_address'] : '';
            $shipping_city= isset($_POST['shipping_city']) ? $_POST['shipping_city'] : '';
            $shipping_state = isset($_POST['shipping_state']) ? $_POST['shipping_state'] : '';
            $shipping_zip = isset($_POST['shipping_zip']) ? $_POST['shipping_zip'] : '';
        }
        
        $body .= '<h3>Billing Information</h3>';
        $body .= 'Name: ' . $billing_firstname . ' ' . $billing_lastname . '<br/>';
        $body .= 'Address: ' . $billing_address . '<br/>';
        $body .= 'City: ' . $billing_city . '<br/>';
        $body .= 'State: ' . $billing_state . '<br/>';
        $body .= 'Zip: ' . $billing_zip . '<br/>';
        $body .= 'Email: ' . $billing_email . '<br/>';
        
        if (isset($_POST['same_as_billing']) || $shipping_address === '') {
            $shipping_firstname = $billing_firstname;
            $shipping_lastname = $billing_lastname;
            $shipping_address = $billing_address;
            $shipping_city = $billing_city;
            $shipping_state = $billing_state;
            $shipping_zip = $billing_zip;
        }
        
        $body .= '<h3>Shipping Information</h3>';
        $body .= 'Name: ' . $shipping_firstname . ' ' . $shipping_lastname . '<br/>';
        $body .= 'Address: ' . $shipping_address . '<br/>';
        $body .= 'City: ' . $shipping_city . '<br/>';
        $body .= 'State: ' . $shipping_state . '<br/>';
        $body .= 'Zip: ' . $shipping_zip . '<br/>';
        
        $billing_zip = preg_replace("/[^0-9]/", "", $billing_zip);
        
        try {
            $config = new HpsConfiguration();

            $config->secretApiKey = esc_attr($this->options['secret_key']);
            $config->versionNumber = '1648';
            $config->developerId = '002914';

            $chargeService = new HpsChargeService($config);

            $address = new HpsAddress();
            $address->address = $billing_address;
            $address->city = $billing_city;
            $address->state = $billing_state;
            $address->zip = $billing_zip;

            $cardHolder = new HpsCardHolder();
            $cardHolder->firstName = $billing_firstname;
            $cardHolder->lastName = $billing_lastname;
            $cardHolder->emailAddress = $billing_email;
            $cardHolder->address = $address;

            $cardOrToken = new HpsTokenData();
            $cardOrToken->tokenValue = $secureToken;
            
            if (!empty($memo)) {
                $details = new HpsTransactionDetails();
                $details->memo = $memo;
            }
            
            $response = $chargeService->charge(
                $amount,
                'usd',
                $cardOrToken,
                $cardHolder,
                false,
                $details);
                
            add_filter('wp_mail_content_type', create_function('', 'return "text/html"; '));
            wp_mail(esc_attr($this->options['payment_email']), 'SecureSubmit $' . $amount . ' Payment Received', $body );
            
            if (isset($_POST["email_reciept"]) && isset($_POST["email_address"])) {
                wp_mail(esc_attr($_POST["email_address"]), 'Payment for $' . $amount . ' Received', $body );
            }
            
        } catch (HpsException $e) {
            die($e->getMessage());
        }
        
        die('Your Payment was successful! Thank you.');
    }
}