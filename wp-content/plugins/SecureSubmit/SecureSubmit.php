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
define('WP_SECURESUBMIT_PATH', WP_PLUGIN_URL . '/' . end(explode(DIRECTORY_SEPARATOR, dirname(__FILE__))));

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
            $requireShipping = true;
        else
            $requireShipping = false;
            
        if (isset($atts['modal']) && $atts['modal'] === 'true')
            $modal = false;
        else
            $requireShipping = false;
        
        if (isset($atts['modal']) && $atts['modal'] === 'true')
            $modal = true;
        else
            $modal = false;
        
        $productid = isset($atts['productid']) ? $atts['productid'] : '';
        $productname = isset($atts['productname']) ? $atts['productname'] : '';
        
        if (empty($productid)) {
            $prefix = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
        } else {
            $prefix = $productid;
        }

        $_SESSION['_' . $productid] = $atts;
    ?>
    
    <?php if ($modal) { ?>
    <style>
    #a<?php echo $prefix; ?>-modal-background {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: #000;
        opacity: .50;
        -webkit-opacity: .5;
        -moz-opacity: .5;
        filter: alpha(opacity=50);
        z-index: 1000;
    }
    
    #a<?php echo $prefix; ?>-modal-content {
        background-color: white;
        border-radius: 10px;
        -webkit-border-radius: 10px;
        -moz-border-radius: 10px;
        box-shadow: 0 0 20px 0 #222;
        -webkit-box-shadow: 0 0 20px 0 #222;
        -moz-box-shadow: 0 0 20px 0 #222;
        display: none;
        padding: 10px;
        position: fixed;
        overflow-y: scroll;
        left: 50%;
        top: 50px;
        width: 400px;
        height: 600px;
        margin-left: -200px;
        transform: translate(-50%, -50%);
        z-index: 1000;
    }

    #a<?php echo $prefix; ?>-modal-background.active, #a<?php echo $prefix; ?>-modal-content.active {
        display: block;
    }
    </style>
    <button id="a<?php echo $prefix; ?>-modal-launcher"><?php echo $buttonText; ?></button>
    <div id="a<?php echo $prefix; ?>-modal-background"></div>
    <div id="a<?php echo $prefix; ?>-modal-content">
    <?php } ?>
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
    
    <?php if ($modal) { ?>
    </div>
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
    
    
    <script src="<?php echo WP_SECURESUBMIT_PATH; ?>/js/secure.submit-1.0.2.js"></script>
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
        
        if (empty($amount))
        {
            if( !session_id())
                session_start();
                
            $atts = $_SESSION['_' . $_POST['product_id']];
            
            $amount = isset($atts['amount']) ? $atts['amount'] : 0;
            $memo = isset($atts['memo']) ? $atts['memo'] : 0;
            $productid = isset($atts['productid']) ? $atts['productid'] : 0;
            $productname = isset($atts['productname']) ? $atts['productname'] : 0;
        }
        
        $body .= 'A Payment of $' . $amount . ' was just received!';
        
        if (!empty($productname)) {
            $body .= '<h3>Product Information</h3>';
            $body .= 'Product Id: ' . $productid . '<br/>';
            $body .= 'Product Name: ' . $productname . '<br/>';
        }
        
        //billing info
        $billing_firstname = isset($_POST['billing_firstname']) ? $_POST['billing_firstname'] : '';
        $billing_lastname = isset($_POST['billing_lastname']) ? $_POST['billing_lastname'] : '';
        $billing_address = isset($_POST['billing_address']) ? $_POST['billing_address'] : '';
        $billing_city= isset($_POST['billing_city']) ? $_POST['billing_city'] : '';
        $billing_state = isset($_POST['billing_state']) ? $_POST['billing_state'] : '';
        $billing_zip = isset($_POST['billing_zip']) ? $_POST['billing_zip'] : '';
        $billing_email = isset($_POST['billing_email']) ? $_POST['billing_email'] : '';
        
        $body .= '<h3>Billing Information</h3>';
        $body .= 'Name: ' . $billing_firstname . ' ' . $billing_lastname . '<br/>';
        $body .= 'Address: ' . $billing_address . '<br/>';
        $body .= 'City: ' . $billing_city . '<br/>';
        $body .= 'State: ' . $billing_state . '<br/>';
        $body .= 'Zip: ' . $billing_zip . '<br/>';
        $body .= 'Email: ' . $billing_email . '<br/>';
        
        //shipping info
        $shipping_firstname = isset($_POST['shipping_firstname']) ? $_POST['shipping_firstname'] : '';
        $shipping_lastname = isset($_POST['shipping_lastname']) ? $_POST['shipping_lastname'] : '';
        $shipping_address = isset($_POST['shipping_address']) ? $_POST['shipping_address'] : '';
        $shipping_city= isset($_POST['shipping_city']) ? $_POST['shipping_city'] : '';
        $shipping_state = isset($_POST['shipping_state']) ? $_POST['shipping_state'] : '';
        $shipping_zip = isset($_POST['shipping_zip']) ? $_POST['shipping_zip'] : '';
        
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
            $config->versionNumber = '0000';
            $config->developerId = '000000';

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
        } catch (HpsException $e) {
            die($e->getMessage());
        }
        
        add_filter('wp_mail_content_type', create_function('', 'return "text/html"; '));
        wp_mail(esc_attr($this->options['payment_email']), 'SecureSubmit $' . $amount . ' Payment Received', $body );
        
        die('Your Payment was successful! Thank you.');
    }
}