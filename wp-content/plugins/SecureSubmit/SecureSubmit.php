<?php
/*
Plugin Name: WP SecureSubmit
Plugin URI: https://developer.heartlandpaymentsystems.com/SecureSubmit
Description: Heartland Payment Systems SecureSubmit Plugin
Author: Mark Hagan
Version: 1.2.2
Author URI: https://developer.heartlandpaymentsystems.com/SecureSubmit
*/
global $jal_db_version;
global $wpdb;
global $table_name;

$table_name = $wpdb->prefix . "securesubmit";
$jal_db_version = "1.2.0";
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
        add_action('init', array($this, 'report_export'));
        add_action('plugins_loaded', array($this,'jal_update_db_check'));
        add_action('admin_menu',array($this,'securesubmit_menu'));
        add_shortcode('securesubmit', array($this, 'shortcode'));
        register_activation_hook(__FILE__, array($this, 'jal_install'));
    }
    
    function init() {
        wp_enqueue_script('jquery');
        
        if(!session_id())
            session_start();
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
                    'payment_email': $('#ssd_payment_email').val(),
                    'from_email': $('#ssd_from_email').val(),
                    'from_name': $('#ssd_from_name').val()
                };

                // If block is check to make sure the keys match each other
                if( (data['secret_key'].match(/cert/) && data['public_key'].match(/cert/)) ||
                    (data['secret_key'].match(/prod/) && data['public_key'].match(/prod/)) ){
                    data['secret_key'] = $.trim(data['secret_key']);
                    data['public_key'] = $.trim(data['public_key']);
                    $.post(ajaxurl, data, function(response) {
                        $('#message').html(response);
                        $('#message').show();
                    });
                }else{
                    alert("Your keys must be both cert or both production. ");
                }
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
                    <th scope="row">From Name</th>
                    <td><input type="text" id="ssd_from_name" class="regular-text" value="<?php echo esc_attr($this->options['from_name']); ?>" />
                </tr>
                <tr>
                    <th scope="row">From Email</th>
                    <td><input type="text" id="ssd_from_email" class="regular-text" value="<?php echo esc_attr($this->options['from_email']); ?>" />
                </tr>
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

    function report_page(){
        global $wpdb;
        global $table_name;

        $shipping = false;
        $additional = false;

        if($_POST['ship'] == 'on'){ $shipping = true;}
        if($_POST['additional'] == 'on'){ $additional = true;}
        ?>
        <style>
            .even{
                background-color: #bbb;
            }
        </style>
        <div class="wrap">
        <form name="report_data" method="post" action="admin.php?page=sub-reporting">
            <h2>SecureSubmit Reporting</h2>
            <div id="message" class="updated hidden"><p></p></div>
            <br>
            <h3>Report Options</h3>
            <input type="checkbox" name="ship" <?php if($shipping){echo 'checked="checked"'; } ?> >Include Shipping Information
            <br>
            <input type="checkbox" name="additional" <?php if($additional){echo 'checked="checked"'; } ?> >Include Additional Information
            <br><br>
            <input type="submit" class="button-primary" value="View Transactions">
        </form>
        <br><br>
        <form name="export_data" method="post" action="">
            <input type="hidden" name="export_transaction" value="true">
            <input type="submit" class="button-primary" value="Export Transactions">
        </form>

        <?php if($_SERVER['REQUEST_METHOD'] =='POST'){
            $transactions = $wpdb->get_results('select * from '.$table_name.' order by id desc limit 10000;' , 'ARRAY_A');
            $count = 0;

        ?>
            <br><br><br><br>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Transaction ID</th><th>Amount</th><th>Product ID</th><th>Time</th><th>Billing Name</th>
                        <th>Billing Address</th><th>Billing City</th><th>Billing State</th><th>Billing Zip</th><th>Billing Email</th>
                        <?php if($shipping){ ?>
                            <th>Shipping Name</th><th>Shipping Address</th><th>Shipping City</th><th>Shipping State</th>
                            <th>Shipping Zip</th>
                        <?php }
                        if($additional){
                            ?>
                            <th>Additional Info 1</th><th>Additional Info 2</th><th>Additional Info 3</th>
                            <th>Additional Info 4</th><th>Additional Info 5</th><th>Additional Info 6</th><th>Additional Info 7</th>
                            <th>Additional Info 8</th><th>Additional Info 9</th><th>Additional Info 10</th>
                        <?php } ?>
                    </tr>
                    <?php foreach($transactions as $key=>$row){
                        if ($count % 2 == 0){
                            echo '<tr class="even">';
                        }else{
                            echo '<tr class="odd">';
                        } ?>
                            <td><?php echo $row['transaction_id']; ?></td>
                            <td><?php echo $row['amount']; ?></td>
                            <td><?php echo $row['product_id']; ?></td>
                            <td><?php echo $row['time']; ?></td>
                            <td><?php echo $row['billing_name']; ?></td>
                            <td><?php echo $row['billing_address']; ?></td>
                            <td><?php echo $row['billing_city']; ?></td>
                            <td><?php echo $row['billing_state']; ?></td>
                            <td><?php echo $row['billing_zip']; ?></td>
                            <td><?php echo $row['billing_email']; ?></td>
                            <?php if($shipping){ ?>
                                <td><?php echo $row['shipping_name']; ?></td>
                                <td><?php echo $row['shipping_address']; ?></td>
                                <td><?php echo $row['shipping_city']; ?></td>
                                <td><?php echo $row['shipping_state']; ?></td>
                                <td><?php echo $row['shipping_zip']; ?></td>
                            <?php }
                            if($additional){ ?>
                                <td><?php echo $row['additional_info1']; ?></td>
                                <td><?php echo $row['additional_info2']; ?></td>
                                <td><?php echo $row['additional_info3']; ?></td>
                                <td><?php echo $row['additional_info4']; ?></td>
                                <td><?php echo $row['additional_info5']; ?></td>
                                <td><?php echo $row['additional_info6']; ?></td>
                                <td><?php echo $row['additional_info7']; ?></td>
                                <td><?php echo $row['additional_info8']; ?></td>
                                <td><?php echo $row['additional_info9']; ?></td>
                                <td><?php echo $row['additional_info10']; ?></td>
                            <?php } ?>
                        </tr>
                    <?php
                        $count++;
                    } ?>
                </thead>
            </table>
        </div>
        <?php
        }
    }

    function report_export(){
        if(isset($_POST['export_transaction'])){
            global $wpdb;
            global $table_name;

            $siteName = sanitize_key( get_bloginfo( 'name' ) );
            if ( ! empty( $siteName ) )
                $siteName .= '.';
            $fileName = $siteName . 'users.' . date( 'Y-m-d-H-i-s' ) . '.csv';


            header( 'Content-Description: File Transfer' );
            header( 'Content-Disposition: attachment; filename=' . $fileName );
            header( 'Content-Type: text/csv; charset=' . get_option( 'blog_charset' ), true );

            $fields = array(
                'transaction_id','amount','product_id','time','billing_name','billing_address','billing_city',
                'billing_state','billing_zip','billing_email','shipping_name','shipping_address','shipping_city',
                'shipping_state','shipping_zip','additional_info1','additional_info2','additional_info3',
                'additional_info4','additional_info5','additional_info6','additional_info7',
                'additional_info8','additional_info9','additional_info10');


            $transactions = $wpdb->get_results('select * from '.$table_name.' order by id desc;' , 'ARRAY_A');

            $headers = array();
            foreach ( $fields as $key => $field ) {
              $headers[] = '"' . strtolower( $field ) . '"';
            }
            echo implode( ',', $headers ) . "\n";
            foreach ( $transactions as $transaction ) {
                $data = array();
                foreach ( $fields as $field ) {
                  $value = isset( $transaction[$field] ) ? $transaction[$field] : '';
                  $value = is_array( $value ) ? serialize( $value ) : $value;
                  $data[] = '"' . str_replace( '"', '""', $value ) . '"';
                }
                echo implode( ',', $data ) . "\n";
            }

            exit;
        }
    }

    function faq_page(){
        ?>
        <div class="wrap">
            <h2>SecureSubmit FAQ</h2>
            <div id="message" class="updated hidden"><p></p></div>

            <br><br><br>
            <h2>How do I get started?</h2>
                <p>The default usage for SecureSubmit is as easy as putting the following in any page or post.</p>
                <pre>[securesubmit modal='true']</pre>
                <p> This will create a "Make Donation" button on the page. Which when clicked will open a modal window.<br>
                    Where the user can input their info and process the payment.</p>
            <br>
            <h2>I don't want to do a donation. How do I change the button text?</h2>
                <p>To change the button text you just add the field 'buttontext' to your setup and give it a value as follows.</p>
                <pre>[securesubmit modal='true' buttontext='Pay Now']</pre>

            <br>
            <h2>I need to collect extra information. How do I do that?</h2>
                <p>The plugin allows you to collect up to 10 additional fields of information. The field names are additional_info1 additional_info2 and so on.<br>
                    For the value just set the name of the field. The information collected will be included in the email you receive and will be stored on your server
                    for later retrieval.</p>
                <pre>[securesubmit modal='true' additional_info1='Invoice Number' additional_info2='messagebox']</pre>

            <br>
            <h2>Can I set a default value other than $100?</h2>
                <p>Yes you can. Just add the attribute "amountdefault" and set the value equal to the amount you would like to see.</p>
                <pre>[securesubmit modal='true' additional_info1='Invoice Number' additional_info2='messagebox' buttontext='Pay Now' amountdefault='25.00']</pre>
        </div>
        <?php
    }

    function securesubmit_menu(){
        add_menu_page('Settings', 'SecureSubmit','manage_securesubmit','securesubmit',
            null, 'dashicons-shield-alt', 66);

        add_submenu_page( 'securesubmit' , 'Settings', 'Settings', 'manage_options', 'sub-settings',array($this,'options_page'));
        add_submenu_page( 'securesubmit' , 'Reportings', 'Reporting', 'manage_options', 'sub-reporting',array($this,'report_page'));
        add_submenu_page( 'securesubmit' , 'FAQ', 'FAQ', 'manage_options', 'sub-faq',array($this,'faq_page'));
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

        if (isset($atts['amountdefault']) && $atts['amountdefault'] != '')
            $amountdefault = $atts['amountdefault'];
        else
            $amountdefault = '100.00';

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
            $prefix = substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
        } else {
            $prefix = $productid;
        }

        update_option('secure_submit_'.$productid,$atts);

	if (isset($atts['ignorelinebreaks']) && $atts['ignorelinebreaks'] === 'true') {
    ?>
    [raw]
    <?php 
	}
	if ($modal) { ?>
        <form id="<?php echo $prefix; ?>_form">
        </form>
        <script src="<?php echo plugins_url( 'js/secure.submit-1.1.0.js', __FILE__ ); ?>"></script>
        <script language="javascript" type="text/javascript">
            var <?php echo $prefix; ?>_requireShipping = <?php echo $requireShipping; ?>;

            <?php
            if(count($additionalFields)>0){
                echo "var " . $prefix . "_requireAdditionalInfo = true;";
            } else {
                echo "var " . $prefix . "_requireAdditionalInfo = false;";
            }
            ?>
            
            jQuery.ajaxSetup({ cache: true });
            if (jQuery('#sss').length == 0)
                jQuery('head').append(jQuery('<link rel="stylesheet" type="text/css" />').attr('href', '<?php echo plugins_url( 'assets/checkout.css', __FILE__ ); ?>').attr('id', 'sss' ));
        
            var trigger_button = jQuery("<div class='pay-button button-main'><a href='#Purchase' id='<?php echo $prefix; ?>_pay_now'><?php echo $buttonText; ?></a><div class='pay-button-border'>&nbsp;</div></div>");
            jQuery('#<?php echo $prefix; ?>_form').append(trigger_button);

            jQuery('#<?php echo $prefix; ?>_pay_now').unbind().bind('click', function() {
                <?php echo $prefix; ?>_trigger_payment();
            });
            
        // BUILD CONTROLS
            var <?php echo $prefix; ?>_modal_html = "<div id='modal-background'></div><div id='modal-content'>";
            <?php echo $prefix; ?>_modal_html += "<a class='boxclose modal-close' id='boxclose'>&times;</a>";
            
            // HEADER
            <?php echo $prefix; ?>_modal_html += "<div id='modal-header'>";
            
            <?php echo $prefix; ?>_modal_html += "<div style='float: left;'>";
            <?php if (!isset($atts["productimage"])) { ?>
            <?php echo $prefix; ?>_modal_html += "<img src='<?php echo plugins_url( 'assets/donation.png', __FILE__ ); ?>' class='checkout-product-image'>";
            <?php } else if($atts["productimage"] == 'none') { ?>
            <?php echo $prefix; ?>_modal_html += "<img src='<?php echo plugins_url( 'assets/transparent.png', __FILE__ ); ?>' class='checkout-product-image'>";
            <?php } else{ ?>
            <?php echo $prefix; ?>_modal_html += "<img src='<?php echo $atts["productimage"]; ?>' class='checkout-product-image'>";    
            <?php } ?>
            <?php echo $prefix; ?>_modal_html += "</div>";
            <?php echo $prefix; ?>_modal_html += "<input type='hidden' name='action' id='action' value='ssd_submit_payment'/>";
            <?php echo $prefix; ?>_modal_html += "<input type='hidden' name='product_sku' id='product_sku' value='<?php echo $atts['productid']; ?>'/>";
            <?php echo $prefix; ?>_modal_html += "<input type='hidden' name='product_id' id='product_id' value='<?php echo $atts['productid']; ?>'/>";
            <?php echo $prefix; ?>_modal_html += "<div class='checkout-product-name'><?php echo $atts['productname']; ?></div>";
            
            if ('<?php echo $atts['amount']; ?>' != '') {
                <?php echo $prefix; ?>_modal_html += "<div class='checkout-price'>$<?php echo $atts['amount']; ?></div>";
            } else {
                <?php echo $prefix; ?>_modal_html += "<div class='donation-price'>Dollar Amount<br />$&nbsp;<input type='text' name='donation_amount' id='donation_amount' class='checkout-input donation-field' placeholder='<?php echo $amountdefault; ?>'></div>";
            }
            
            <?php echo $prefix; ?>_modal_html += "</div>";
            
            <?php echo $prefix; ?>_modal_html += "<div id='modal-body'>";
            
            // BILLING BODY
            var <?php echo $prefix; ?>_billing_html = "<div id='<?php echo $prefix; ?>_billing_panel'>";
            <?php echo $prefix; ?>_billing_html += "<div class='checkout-card-information'>Billing Information</div>";
            <?php echo $prefix; ?>_billing_html += "<div class='card-number'><input type='text' name='cardholder_name' id='cardholder_name' class='checkout-input checkout-card' placeholder='Cardholder Name'></div>";
            <?php echo $prefix; ?>_billing_html += "<div class='card-number'><input type='text' name='cardholder_address' id='cardholder_address' class='checkout-input checkout-card' placeholder='Address'></div>";
            <?php echo $prefix; ?>_billing_html += "<div class='card-number'>";
            <?php echo $prefix; ?>_billing_html += "<input type='text' name='cardholder_city' id='cardholder_city' class='checkout-input city-field' placeholder='City'>";
            <?php echo $prefix; ?>_billing_html += "<select name='cardholder_state' id='cardholder_state' class='checkout-input state-field'><option value='AL'>AL</option><option value='AK'>AK</option><option value='AZ'>AZ</option><option value='AR'>AR</option><option value='CA'>CA</option><option value='CO'>CO</option><option value='CT'>CT</option><option value='DE'>DE</option><option value='DC'>DC</option><option value='FL'>FL</option><option value='GA'>GA</option><option value='HI'>HI</option><option value='ID'>ID</option><option value='IL'>IL</option><option value='IN'>IN</option><option value='IA'>IA</option><option value='KS'>KS</option><option value='KY'>KY</option><option value='LA'>LA</option><option value='ME'>ME</option><option value='MD'>MD</option><option value='MA'>MA</option><option value='MI'>MI</option><option value='MN'>MN</option><option value='MS'>MS</option><option value='MO'>MO</option><option value='MT'>MT</option><option value='NE'>NE</option><option value='NV'>NV</option><option value='NH'>NH</option><option value='NJ'>NJ</option><option value='NM'>NM</option><option value='NY'>NY</option><option value='NC'>NC</option><option value='ND'>ND</option><option value='OH'>OH</option><option value='OK'>OK</option><option value='OR'>OR</option><option value='PA'>PA</option><option value='RI'>RI</option><option value='SC'>SC</option><option value='SD'>SD</option><option value='TN'>TN</option><option value='TX'>TX</option><option value='UT'>UT</option><option value='VT'>VT</option><option value='VA'>VA</option><option value='WA'>WA</option><option value='WV'>WV</option><option value='WI'>WI</option><option value='WY'>WY</option></select>";
            <?php echo $prefix; ?>_billing_html += "<input type='text' name='cardholder_zip' id='cardholder_zip' class='checkout-input zip-field' placeholder='Zip'>";
            <?php echo $prefix; ?>_billing_html += "</div>";

            // Additional Info BODY
            var <?php echo $prefix; ?>_additional_html = "<div id='<?php echo $prefix; ?>_additional_panel'>";
            <?php echo $prefix; ?>_additional_html += "<div class='back-button'><a href='#billing' id='<?php echo $prefix; ?>_additional_back'>back</a></div>";
            <?php echo $prefix; ?>_additional_html += "<div class='checkout-card-information'>Additional Information</div>";
            <?php echo $prefix; ?>_additional_html += "<div style='overflow-y: auto; height: 200px;'>";
            <?php
                if(count($additionalFields)>0){
                    foreach($additionalFields as $key=>$value){
                        $field_type = "text";

                        foreach ($additionalFieldTypes as $key2=>$value2) {
                            if ($value . "_type" == $value2) {
                                $field_type = $atts[$value2];
                            }
                        }

                        if ($field_type == "textarea") {
                            echo $prefix . '_additional_html += "<div class=\'card-number\' ><textarea name=\''.$value.'\' id=\''.$value.'\' class=\'donation-textarea\' placeholder=\''.$atts[$value].'\'></textarea></div>";';
                        }
                        else if ($field_type == "dropdown") { 
                            echo $prefix . '_additional_html += "<div class=\'card-number\' ><select name=\''.$value.'\' id=\''.$value.'\' class=\'donation-textarea\'>";';
                            $options = explode("|", $atts[$value]);
                            foreach($options as $option) {
                               echo $prefix . '_additional_html += "<option>' . $option . '</option>";';
                            }
                            echo $prefix . '_additional_html += "</select></div>";';
                        }
                        else
                        {
                            echo $prefix . '_additional_html += "<div class=\'card-number\' ><input name=\''.$value.'\' type=\''.$field_type.'\' id=\''.$value.'\' class=\'checkout-input checkout-card\' placeholder=\''.$atts[$value].'\'></div>";';
                        }
                    }
                }
            ?>
            <?php echo $prefix; ?>_additional_html += "</div>";
            <?php echo $prefix; ?>_additional_html += "<div class='pay-button button-next'><a href='#Purchase' id='<?php echo $prefix; ?>_additional_next_button'>Next</a><div class='pay-button-border'>&nbsp;</div></div>";
            <?php echo $prefix; ?>_additional_html += "<div class='powered_by'><img src='<?php echo plugins_url( 'assets/heart.png', __FILE__ ); ?>' /></div>";
            <?php echo $prefix; ?>_additional_html += "</div>";


            // TODO: Check if this is checked to skip the shipping screen...
            if (<?php echo $prefix; ?>_requireShipping) {
                <?php echo $prefix; ?>_billing_html += "<div class='same_shipping'><input name='shipping_same' type='checkbox' id='shipping_same'>&nbsp;<label for='shipping_same'>Shipping Same As Billing</label></div>";
            }

            <?php echo $prefix; ?>_billing_html += "<div class='pay-button button-next'><a href='#Purchase' id='<?php echo $prefix; ?>_billing_next_button'>Next</a><div class='pay-button-border'>&nbsp;</div></div>";
            <?php echo $prefix; ?>_billing_html += "<div class='powered_by'><img src='<?php echo plugins_url( 'assets/heart.png', __FILE__ ); ?>' /></div>";
            <?php echo $prefix; ?>_billing_html += "</div>";
            
            // SHIPPING BODY
            var <?php echo $prefix; ?>_shipping_html = "<div id='<?php echo $prefix; ?>_shipping_panel'>";
            <?php echo $prefix; ?>_shipping_html += "<div class='back-button'><a href='#billing' id='<?php echo $prefix; ?>_shipping_back'>back</a></div>";
            <?php echo $prefix; ?>_shipping_html += "<div class='checkout-card-information'>Shipping Information</div>";
            <?php echo $prefix; ?>_shipping_html += "<div class='card-number'><input name='shipping_name' type='text' id='shipping_name' class='checkout-input checkout-card' placeholder='Shipping Name'></div>";
            <?php echo $prefix; ?>_shipping_html += "<div class='card-number'><input name='shipping_address' type='text' id='shipping_address' class='checkout-input checkout-card' placeholder='Address'></div>";
            <?php echo $prefix; ?>_shipping_html += "<div class='card-number'>";
            <?php echo $prefix; ?>_shipping_html += "<input type='text' name='shipping_city' id='shipping_city' class='checkout-input city-field' placeholder='City'>";
            <?php echo $prefix; ?>_shipping_html += "<select id='shipping_state' name='shipping_state' class='checkout-input state-field'><option value='AL'>AL</option><option value='AK'>AK</option><option value='AZ'>AZ</option><option value='AR'>AR</option><option value='CA'>CA</option><option value='CO'>CO</option><option value='CT'>CT</option><option value='DE'>DE</option><option value='DC'>DC</option><option value='FL'>FL</option><option value='GA'>GA</option><option value='HI'>HI</option><option value='ID'>ID</option><option value='IL'>IL</option><option value='IN'>IN</option><option value='IA'>IA</option><option value='KS'>KS</option><option value='KY'>KY</option><option value='LA'>LA</option><option value='ME'>ME</option><option value='MD'>MD</option><option value='MA'>MA</option><option value='MI'>MI</option><option value='MN'>MN</option><option value='MS'>MS</option><option value='MO'>MO</option><option value='MT'>MT</option><option value='NE'>NE</option><option value='NV'>NV</option><option value='NH'>NH</option><option value='NJ'>NJ</option><option value='NM'>NM</option><option value='NY'>NY</option><option value='NC'>NC</option><option value='ND'>ND</option><option value='OH'>OH</option><option value='OK'>OK</option><option value='OR'>OR</option><option value='PA'>PA</option><option value='RI'>RI</option><option value='SC'>SC</option><option value='SD'>SD</option><option value='TN'>TN</option><option value='TX'>TX</option><option value='UT'>UT</option><option value='VT'>VT</option><option value='VA'>VA</option><option value='WA'>WA</option><option value='WV'>WV</option><option value='WI'>WI</option><option value='WY'>WY</option></select>";
            <?php echo $prefix; ?>_shipping_html += "<input type='text' name='shipping_zip' id='shipping_zip' class='checkout-input zip-field' placeholder='Zip'>";
            <?php echo $prefix; ?>_shipping_html += "</div>";
            <?php echo $prefix; ?>_shipping_html += "<div class='pay-button button-next'><a href='#Purchase' id='<?php echo $prefix; ?>_shipping_next_button'>Next</a><div class='pay-button-border'>&nbsp;</div></div>";
            <?php echo $prefix; ?>_shipping_html += "<div class='powered_by'><img src='<?php echo plugins_url( 'assets/heart.png', __FILE__ ); ?>' /></div>";
            <?php echo $prefix; ?>_shipping_html += "</div>";
            
            // CARD BODY
            var <?php echo $prefix; ?>_card_html = "<div id='<?php echo $prefix; ?>_card_panel'>";
            <?php echo $prefix; ?>_card_html += "<div class='back-button'><a href='#shipping' id='<?php echo $prefix; ?>_card_back'>back</a></div>";
            <?php echo $prefix; ?>_card_html += "<div class='checkout-card-information'>Card Information</div>";
            <?php echo $prefix; ?>_card_html += "<div class='card-number'><input type='text' id='card_number' class='checkout-input checkout-card' placeholder='4111 - 1111 - 1111 - 1111'></div>";
            <?php echo $prefix; ?>_card_html += "<div class='card-exp'><input type='text' id='card_exp' class='checkout-exp' placeholder='MM/YY'></div>";
            <?php echo $prefix; ?>_card_html += "<div class='card-cvc'><input type='text' id='card_cvc' class='checkout-exp' placeholder='CVC'></div>";
            <?php echo $prefix; ?>_card_html += "<div class='clearfixcheckout'>&nbsp;</div>";
            <?php echo $prefix; ?>_card_html += "<div class='email-reciept'><input name='email_reciept' type='checkbox' id='email_reciept'>&nbsp;<label for='email_reciept'>Email Receipt</label></div>";
            <?php echo $prefix; ?>_card_html += "<div class='email-address'><input name='email_address' type='text' id='email_address' class='checkout-email' placeholder='myemail@email.com'></div>";
            <?php echo $prefix; ?>_card_html += "<div class='pay-button button-next'><a href='#Purchase' id='<?php echo $prefix; ?>_pay_button'><?php echo $buttonText; ?></a><div class='pay-button-border'>&nbsp;</div></div>";
            <?php echo $prefix; ?>_card_html += "<div class='powered_by'><img src='<?php echo plugins_url( 'assets/heart.png', __FILE__ ); ?>' /></div>";
            <?php echo $prefix; ?>_card_html += "</div>";

            // PROCESSING BODY
            var <?php echo $prefix; ?>_processing_html = "<div id='<?php echo $prefix; ?>_processing_panel'>";
            <?php echo $prefix; ?>_processing_html += "<div class='transaction-processing'>processing</div>";
            <?php echo $prefix; ?>_processing_html += "</div>";
            
            // FAILURE BODY
            var <?php echo $prefix; ?>_failure_html = "<div id='<?php echo $prefix; ?>_failure_panel'>";
            <?php echo $prefix; ?>_failure_html += "<div class='checkout-card-information'>Transaction Information</div>";
            <?php echo $prefix; ?>_failure_html += "<div class='transaction-error'>There was a problem while processing your card.</div>";
            <?php echo $prefix; ?>_failure_html += "<div class='pay-button button-next'><a href='#Purchase' id='<?php echo $prefix; ?>_retry_button'>Retry</a><div class='pay-button-border'>&nbsp;</div></div>";
            <?php echo $prefix; ?>_failure_html += "</div>";
            
            // SUCCESS BODY
            var <?php echo $prefix; ?>_success_html = "<div id='<?php echo $prefix; ?>_success_panel'>";
            <?php echo $prefix; ?>_success_html += "<div class='card-number'>Your Payment Was Successful!</div>";
            <?php echo $prefix; ?>_success_html += "</div>";
            
            <?php echo $prefix; ?>_modal_html += <?php echo $prefix; ?>_billing_html;
            <?php echo $prefix; ?>_modal_html += <?php echo $prefix; ?>_additional_html;
            <?php echo $prefix; ?>_modal_html += <?php echo $prefix; ?>_shipping_html;
            <?php echo $prefix; ?>_modal_html += <?php echo $prefix; ?>_card_html;
            <?php echo $prefix; ?>_modal_html += <?php echo $prefix; ?>_processing_html;
            <?php echo $prefix; ?>_modal_html += <?php echo $prefix; ?>_success_html;
            <?php echo $prefix; ?>_modal_html += <?php echo $prefix; ?>_failure_html;
            
            <?php echo $prefix; ?>_modal_html += "</div>"; // BODY
            <?php echo $prefix; ?>_modal_html += "</div>"; // content
            
        // ACTIONS
            function <?php echo $prefix; ?>_trigger_payment() {
                jQuery('#modal-content').remove();           // a little clean-up
                jQuery('#modal-background').remove();        // a little clean-up
               
                jQuery('#<?php echo $prefix; ?>_form').append(<?php echo $prefix; ?>_modal_html);  // there could be multiple forms, multiple buttons.
                jQuery("#modal-background").toggleClass("active");

                jQuery(function(){    
        			if(jQuery.browser.msie && jQuery.browser.version <= 9){
            			jQuery("[placeholder]").focus(function(){
                				if(jQuery(this).val()==jQuery(this).attr("placeholder")) jQuery(this).val("");
            			}).blur(function(){
                		if(jQuery(this).val()=="") jQuery(this).val(jQuery(this).attr("placeholder"));
            		}).blur();
                }});

                // show the first panel (billing)
                jQuery("#<?php echo $prefix; ?>_billing_panel").show();
                
                jQuery("#<?php echo $prefix; ?>_card_panel").hide();
                jQuery("#<?php echo $prefix; ?>_additional_panel").hide();
                jQuery("#<?php echo $prefix; ?>_shipping_panel").hide();
                jQuery("#<?php echo $prefix; ?>_processing_panel").hide();
                jQuery("#<?php echo $prefix; ?>_success_panel").hide();
                jQuery("#<?php echo $prefix; ?>_failure_panel").hide();
                
                jQuery("#modal-content").fadeIn(400);
                
                jQuery("#<?php echo $prefix; ?>_billing_next_button").on("click", function(event) {
                    jQuery("#<?php echo $prefix; ?>_billing_panel").hide();

                    if (<?php echo $prefix; ?>_requireAdditionalInfo){
                        jQuery("#<?php echo $prefix; ?>_additional_panel").fadeIn();
                    }else if (<?php echo $prefix; ?>_requireShipping) {
                        jQuery("#<?php echo $prefix; ?>_card_panel").hide();
                        jQuery("#<?php echo $prefix; ?>_shipping_panel").fadeIn();
                    } else {
                        jQuery("#<?php echo $prefix; ?>_shipping_panel").hide();
                        jQuery("#<?php echo $prefix; ?>_card_panel").fadeIn();
                    }
                });

                jQuery("#<?php echo $prefix; ?>_additional_back").on("click", function(event) {
                    jQuery("#<?php echo $prefix; ?>_additional_panel").hide();
                    jQuery("#<?php echo $prefix; ?>_billing_panel").fadeIn();
                });

                jQuery("#<?php echo $prefix; ?>_additional_next_button").on("click", function(event) {
                    jQuery("#<?php echo $prefix; ?>_billing_panel").hide();
                    jQuery("#<?php echo $prefix; ?>_additional_panel").hide();
                    if(<?php echo $prefix; ?>_requireShipping){
                        jQuery("#<?php echo $prefix; ?>_shipping_panel").fadeIn();
                    }else{
                        jQuery("#<?php echo $prefix; ?>_card_panel").fadeIn();
                    }
                });

                jQuery("#<?php echo $prefix; ?>_shipping_next_button").on("click", function(event) {
                    jQuery("#<?php echo $prefix; ?>_billing_panel").hide();
                    jQuery("#<?php echo $prefix; ?>_shipping_panel").hide();
                    jQuery("#<?php echo $prefix; ?>_card_panel").fadeIn();
                });
                
                jQuery("#<?php echo $prefix; ?>_retry_button").on("click", function(event) {
                    jQuery('.donation-price').show();
                    jQuery('.checkout-price').show();
                    jQuery("#<?php echo $prefix; ?>_failure_panel").hide();
                    jQuery("#<?php echo $prefix; ?>_card_panel").fadeIn();
                });
                
                if (<?php echo $prefix; ?>_requireShipping) {
                    jQuery("#<?php echo $prefix; ?>_shipping_back").on("click", function(event) {
                        jQuery("#<?php echo $prefix; ?>_billing_panel").fadeIn();
                        jQuery("#<?php echo $prefix; ?>_shipping_panel").hide();
                        jQuery("#<?php echo $prefix; ?>_card_panel").hide();
                    });
                }
                
                jQuery("#<?php echo $prefix; ?>_card_back").on("click", function(event) {
                    jQuery("#<?php echo $prefix; ?>_billing_panel").hide();
                    
                    if (<?php echo $prefix; ?>_requireShipping) {
                        jQuery("#<?php echo $prefix; ?>_shipping_panel").show();
                        jQuery("#<?php echo $prefix; ?>_card_panel").hide();
                    } else {
                        <?php if(count($additionalFields)>0){ ?>
                            jQuery("#<?php echo $prefix; ?>_additional_panel").fadeIn();
                            jQuery("#<?php echo $prefix; ?>_card_panel").hide();
                        <?php } else { ?>
                            jQuery("#<?php echo $prefix; ?>_billing_panel").fadeIn();
                            jQuery("#<?php echo $prefix; ?>_card_panel").hide();
                        <?php } ?>
                    }
                });
                
                jQuery("#<?php echo $prefix; ?>_pay_button").on("click", function(event) {
                    if (!jQuery('#donation_amount').val()) {
                        jQuery('#donation_amount').val(jQuery('#donation_amount').attr('placeholder'));
                    }

                    jQuery('.donation-price').hide();
                    jQuery('.checkout-price').hide();

                    jQuery("#<?php echo $prefix; ?>_card_panel").hide();
                    jQuery("#<?php echo $prefix; ?>_processing_panel").show();
                    jQuery("#modal-launcher, #modal-background, .modal-close").unbind('click');
                    <?php echo $prefix; ?>_tokenize();
                });
                
                jQuery("#modal-launcher, #modal-background, .modal-close").click(function () {
                    jQuery('#modal-content').remove();           // a little clean-up
                    jQuery('#modal-background').remove();        // a little clean-up
                });
            }
            
            function <?php echo $prefix; ?>_tokenize() {
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
                        <?php echo $prefix; ?>_secureSubmitResponseHandler(response);
                    },
                    error: function(response) {
                        <?php echo $prefix; ?>_secureSubmitResponseHandler(response);
                    }
                });
            }
            
            function <?php echo $prefix; ?>_secureSubmitResponseHandler(response) {
                if ( response.message ) {
                    jQuery("#<?php echo $prefix; ?>_processing_panel").hide();
                    jQuery("#<?php echo $prefix; ?>_failure_panel").show();
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

                    <?php echo $prefix; ?>_do_post();
                }
            }
            
            function <?php echo $prefix; ?>_do_post() {
                var datastring = jQuery('#<?php echo $prefix; ?>_form').serialize();
                var url = "<?php echo admin_url('admin-ajax.php'); ?>";

		if(jQuery.browser.msie && jQuery.browser.version <= 9){
			jQuery(this).find('[placeholder]').each(function() {
                		if (jQuery(this).val() == jQuery(this).attr("placeholder")) {
                    			jQuery(this).val("");
                		}
            		});
		}

                jQuery.post(url, datastring, function(response) {
                    if (response.indexOf("successful") >= 0)
                    {
                        jQuery("#<?php echo $prefix; ?>_processing_panel").hide();
                        jQuery("#<?php echo $prefix; ?>_success_panel").show();
                        
                        jQuery("#modal-launcher, #modal-background, .modal-close").bind('click', function () {
                            jQuery('#modal-content').remove();           // a little clean-up
                            jQuery('#modal-background').remove();        // a little clean-up
                        });
                    }
                    else
                    {
                        jQuery("#<?php echo $prefix; ?>_processing_panel").hide();
                        jQuery("#<?php echo $prefix; ?>_failure_panel").show();
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
    <?php } else { ?>
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
    <h3>Card Information</h3>
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
            <td>$<input class="form-text" type="text" value="<?php echo $amountdefault; ?>" id="<?php echo $prefix; ?>_donation_amount" /></td>
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
	<?php if (isset($atts['ignorelinebreaks']) && $atts['ignorelinebreaks'] === 'true') { ?>
    [/raw]
	<?php } ?>
    <script src="<?php echo plugins_url( 'js/secure.submit-1.0.2.js', __FILE__ ); ?>"></script>
    <?php
        return ob_get_clean();
    }
    
    function save_options() {
        $data = array(
            'secret_key' => $_POST['secret_key'],
            'public_key' => $_POST['public_key'],
            'payment_email' => $_POST['payment_email'],
            'from_email' => $_POST['from_email'],
            'from_name' => $_POST['from_name'],
        );
        update_option('securesubmit_options', $data);
        echo 'Settings saved.';
        exit;
    }
    
    function submit_payment() {
        global $wpdb;
        global $table_name;

        $body = "";
        $secureToken = isset($_POST['securesubmit_token']) ? $_POST['securesubmit_token'] : '';
        $amount = isset($_POST['donation_amount']) ? $_POST['donation_amount'] : 0;

        if ($amount === 0)
        {
            if(!session_id())
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

        if (isset($_POST['additional_info1']) && !empty($_POST['additional_info1'])) {
            $body .= '<h3>Additional Information</h3>';
            for ($i=1; $i < 100; $i++) { 
                if (isset($_POST['additional_info' . strval($i)]) && !empty($_POST['additional_info' . strval($i)])) {
                    $body .= 'additional_info' . strval($i) . ": " . $_POST['additional_info' . strval($i)] . '<br/>';
                }else {
                    break;
                }
            }
        }
        
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

            if (isset($_POST["email_reciept"]) && isset($_POST["email_address"])) {
                if($this->options['from_name']){
                    $fromAddress = esc_attr($this->options['from_email']);
                    $fromName = esc_attr($this->options['from_name']);
                    $header = 'From: "' . $fromName . '" <'.$fromAddress.'>'."\r\n";
                    wp_mail(esc_attr($this->options['payment_email']), 'SecureSubmit $' . $amount . ' Payment Received', $body, $header );
                    wp_mail(esc_attr($_POST["email_address"]), 'Payment for $' . $amount . ' Received', $body,$header);
                }else{
                    wp_mail(esc_attr($this->options['payment_email']), 'SecureSubmit $' . $amount . ' Payment Received', $body );
                    wp_mail(esc_attr($_POST["email_address"]), 'Payment for $' . $amount . ' Received', $body);
                }
            }

            // Save to Data Base
            $transaction_id = $response->transactionId;

            $insert_array = array();
            $insert_array['time']               = current_time('mysql');
            $insert_array['billing_name']       = $billing_firstname . ' ' . $billing_lastname;
            $insert_array['billing_address']    = $billing_address;
            $insert_array['billing_city']       = $billing_city;
            $insert_array['billing_state']      = $billing_state;
            $insert_array['billing_zip']        = $billing_zip;
            $insert_array['billing_email']      = $billing_email;
            $insert_array['shipping_name']      = $shipping_firstname . ' ' . $shipping_lastname;
            $insert_array['shipping_address']   = $shipping_address;
            $insert_array['shipping_city']      = $shipping_city;
            $insert_array['shipping_state']     = $shipping_state;
            $insert_array['shipping_zip']       = $shipping_zip;
            $insert_array['product_id']         = $productid;
            $insert_array['amount']             = $amount;
            $insert_array['transaction_id']     = $transaction_id;

            if (isset($_POST['additional_info1']) && !empty($_POST['additional_info1'])) {
               for ($i=1; $i < 100; $i++) {
                    if (isset($_POST['additional_info' . strval($i)]) && !empty($_POST['additional_info' . strval($i)])) {
                        $insert_array['additional_info' . strval($i)] = $_POST['additional_info' . strval($i)];
                    }else {
                        break;
                    }
                }
            }

            $rows_affected = $wpdb->insert($table_name, $insert_array);

        } catch (HpsException $e) {
            die($e->getMessage());
        }
        
        die('Your Payment was successful! Thank you.' . $body);
    }

    function jal_install(){
        global $wpdb;
        global $jal_db_version;
        global $table_name;

        $installed_ver = $this->options['jal_db_version'];

        if($installed_ver != $jal_db_version){
        $sql = "CREATE TABLE $table_name (
            id bigint NOT NULL AUTO_INCREMENT,
            product_id varchar(255) NOT NULL,
            amount varchar(255) NOT NULL,
            transaction_id int NOT NULL,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            billing_name varchar(255) NOT NULL,
            billing_address varchar(255) NOT NULL,
            billing_city varchar(255) NOT NULL,
            billing_state varchar(255) NOT NULL,
            billing_zip  varchar(255) NOT NULL,
            billing_email varchar(255) NOT NULL,
            shipping_name varchar(255) NOT NULL,
            shipping_address varchar(255) NOT NULL,
            shipping_city varchar(255) NOT NULL,
            shipping_state varchar(255) NOT NULL,
            shipping_zip varchar(255) NOT NULL,
            additional_info1 varchar(255) NOT NULL,
            additional_info2 varchar(255) NOT NULL,
            additional_info3 varchar(255) NOT NULL,
            additional_info4 varchar(255) NOT NULL,
            additional_info5 varchar(255) NOT NULL,
            additional_info6 varchar(255) NOT NULL,
            additional_info7 varchar(255) NOT NULL,
            additional_info8 varchar(255) NOT NULL,
            additional_info9 varchar(255) NOT NULL,
            additional_info10 varchar(255) NOT NULL,
            UNIQUE  KEY id (id)
           );";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        update_option( "jal_db_version", $jal_db_version);

        }
    }

    function jal_update_db_check(){
        global $jal_db_version;
        if (get_site_option( 'jal_db_version' ) != $jal_db_version){
            $this->jal_install();
        }
    }
}
