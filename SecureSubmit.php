<?php
/*
Plugin Name: WP SecureSubmit
Plugin URI: https://developer.heartlandpaymentsystems.com/SecureSubmit
Description: Heartland Payment Systems SecureSubmit Plugin
Author: SecureSubmit
Version: 1.5.7
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

    const RECAPTCHA_CLIENT_URL = 'https://www.google.com/recaptcha/api.js?onload=ssdRenderCaptcha&render=explicit';
    const RECAPTCHA_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
    private $recaptchaSecretKey = '';
    private $recaptchaSiteKey = '';
    private $isRecaptchaEnabled = false;

    function __construct() {
        $this->dir = (string) dirname(__FILE__);

        if ( ! class_exists('HpsServicesConfig' ) ) {
            require_once($this->dir . '/lib/Hps.php');
        }

        $this->url = plugins_url('securesubmit');
        $this->options = get_option('securesubmit_options');

        // assign defaults
        if (!isset($this->options['enable_fraud'])) {
            $this->options['enable_fraud'] = 'true';
        }
        if (!isset($this->options['fraud_message'])) {
            $this->options['fraud_message'] = 'Please contact us to complete the transaction.';
        }
        if (!isset($this->options['fraud_velocity_attempts'])) {
            $this->options['fraud_velocity_attempts'] = 3;
        }
        if (!isset($this->options['fraud_velocity_timeout'])) {
            $this->options['fraud_velocity_timeout'] = 10;
        }
        if (!isset($this->options['secret_key'])) {
            $this->options['secret_key'] = '';
        }
        if (!isset($this->options['public_key'])) {
            $this->options['public_key'] = '';
        }
        if (!isset($this->options['payment_email'])) {
            $this->options['payment_email'] = '';
        }
        if (isset($this->options['recaptcha_site_key'])) {
            $this->recaptchaSiteKey = trim($this->options['recaptcha_site_key']);
        }
        if (isset($this->options['recaptcha_secret_key'])) {
            $this->recaptchaSecretKey = trim($this->options['recaptcha_secret_key']);
        }
        if (isset($this->options['enable_recaptcha']) && $this->options['enable_recaptcha']=="true") {
            $this->isRecaptchaEnabled = true;
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

        if(is_admin()){

            wp_enqueue_style( 'admin-style', plugins_url( '/assets/admin-style.css', __FILE__ ) );

            if ( current_user_can('edit_posts') && current_user_can('edit_pages') && get_user_option('rich_editing') == 'true')
            {
                if (array_key_exists('enable_button_builder', $this->options ) && $this->options['enable_button_builder'] == 'true') {
                    add_filter('tiny_mce_version', array(&$this, 'tiny_mce_version') );
                    add_filter("mce_external_plugins", array(&$this, "mce_external_plugins"));
                    add_filter('mce_buttons_2', array(&$this, 'mce_buttons'));
                }
            }
        }

    }

    function mce_buttons($buttons) {
        array_push($buttons, "separator", "securesubmit");
        return $buttons;
    }

    function mce_external_plugins($plugin_array) {
        $plugin_array['securesubmit']  =  plugins_url('js/securesubmit_plugin.js', __FILE__ );
        return $plugin_array;
    }

    function tiny_mce_version($version) {
        return ++$version;
    }

    function options_page() {
        if (!isset($this->options['email_subject']) || $this->options['email_subject'] === '' || $this->options['email_subject'] == null) {
            $emailsubject = 'Thank you for your payment of $%amount%!';
        }
        else {
            $emailsubject = $this->options['email_subject'];
        }

        if (!isset($this->options['email_body']) || $this->options['email_body'] === '' || $this->options['email_body'] == null) {
            $emailbody = '%firstname%,<br /><br />Thank you for your payment!<br /><br />';
            $emailbody .= '<h2>%productinfo%</h2>';
            $emailbody .= '<h3>Billing Information</h3>%billingaddress%';
            $emailbody .= '<h3>Shipping Information</h3>%shippingaddress%';
            $emailbody .= '<h3>Additional Information</h3>%additionalinformation%';
        } else {
            $emailbody = $this->options['email_body'];
        }
        ?>
        <script>
            (function ($) {
                $(function () {
                    $('#submit').click(function () {
                        $('#message').hide();

                        var content;
                        var editor = tinyMCE.get('customer_email_body');
                        if (editor) {
                            // Ok, the active tab is Visual
                            content = editor.getContent();
                        } else {
                            // The active tab is HTML, so just query the textarea
                            content = $('#' + 'customer_email_body').val();
                        }

                        var data = {
                            'action': 'ssd_save_options',
                            'secret_key': $('#ssd_secret_key').val(),
                            'public_key': $('#ssd_public_key').val(),
                            'payment_email': $('#ssd_payment_email').val(),
                            'from_email': $('#ssd_from_email').val(),
                            'from_name': $('#ssd_from_name').val(),
                            'email_body': content,
                            'email_subject': $('#customer_email_subject').val(),
                            'enable_button_builder': $('#enable_button_builder').is(':checked'),
                            'enable_fraud': $('#enable_fraud').is(':checked'),
                            'fraud_message': $('#fraud_message').val(),
                            'fraud_velocity_attempts': $('#fraud_velocity_attempts').val(),
                            'fraud_velocity_timeout': $('#fraud_velocity_timeout').val(),
                            'enable_recaptcha': $('#enable_recaptcha').is(':checked'),
                            'recaptcha_site_key': $('#recaptcha_site_key').val(),
                            'recaptcha_secret_key': $('#recaptcha_secret_key').val(),

                        };

                        // If block is check to make sure the keys match each other
                        if ((data['secret_key'].match(/cert/) && data['public_key'].match(/cert/)) ||
                            (data['secret_key'].match(/prod/) && data['public_key'].match(/prod/))) {
                            data['secret_key'] = $.trim(data['secret_key']);
                            data['public_key'] = $.trim(data['public_key']);
                            $.post(ajaxurl, data, function (response) {
                                $('#message').html(response);
                                $('#message').show();
                                window.location.href = '#';
                            });
                        } else {
                            alert("Your keys must be both cert or both production. ");
                        }
                        if ((data['enable_recaptcha']) && (!data['recaptcha_site_key'] || !data['recaptcha_secret_key'])) {
                            alert("Warning: have reCaptcha enabled but are missing one or more reCaptcha keys. ");
                        }

                    });
                });
            })(jQuery);
        </script>

        <!-- Start Page Wrapper -->
        <div class="wrap ss-wrap">
            <h1 class="ss-wp-heading-inline"><span class="hidden-small">SecureSubmit Donate / Pay Now&nbsp;</span><?php echo (isset($title) ? $title : esc_html(get_admin_page_title())) ?></h1>
            <div id="message" class="updated hidden">
                <p></p>
            </div>
            <div class="ss-top-container clearfix">
                <!-- Start API Credentials Panel -->
                <div class="ss-panel ss-api-credentials">
                    <h3>API Credentials</h3>
                    <p><a href="https://developer.heartlandpaymentsystems.com/Account/KeysAndCredentials" target="_blank">Click here</a> to get your SecureSubmit API keys!</p>
                    <label for="ssd_public_key">Public Key:</label>
                    <input type="text" id="ssd_public_key" class="regular-text" value="<?php if (isset($this->options['public_key'])) echo esc_attr($this->options['public_key']); ?>" />
                    <label for="ssd_secret_key">Secret Key:</label>
                        <input type="text" id="ssd_secret_key" class="regular-text" value="<?php if (isset($this->options['secret_key'])) echo esc_attr($this->options['secret_key']); ?>" />
                </div>
                <!-- End API Credentials Panel -->
                <!-- Start General Options Panel -->
                <div class="ss-panel ss-general-options">
                    <h3>General Options</h3>
                    <?php
                        $ischecked = '';
                        if (isset($this->options['enable_button_builder']) && $this->options['enable_button_builder'] == 'true')
                            $ischecked = "checked='checked'";
                        ?>
                        <div class="ss-checkbox">
                            <input type="checkbox" id="enable_button_builder" <?php echo $ischecked; ?> />
                            <label for="enable_button_builder" class="ss-checkbox-label">Enable Button Builder</label>
                        </div>
                        <div class="ss-checkbox clearfix">
                            <?php
                        $ischecked = '';
                        if (isset($this->options['enable_recaptcha']) && $this->options['enable_recaptcha'] == 'true')
                            $ischecked = "checked='checked'";
                        ?>
                                <input type="checkbox" id="enable_recaptcha" <?php echo $ischecked; ?> />
                                <label for="enable_recaptcha" class="ss-checkbox-label">Enable Google Recaptcha</label>
                                <br /><span class="ss-subtext">Non-modal only. What is <a target="_blank" href="https://www.google.com/recaptcha/intro/index.html">Google ReCaptcha</a>?</span>
                        </div>
                        <label for="recaptcha_site_key">Recaptcha Site Key:</label>
                        <input type="text" id="recaptcha_site_key" class="regular-text" value="<?php if (isset($this->options['recaptcha_site_key'])) echo esc_attr($this->options['recaptcha_site_key']); ?>" />
                        <label for="recaptcha_secret_key">Recaptcha Secret Key:</label>
                        <input type="text" id="recaptcha_secret_key" class="regular-text" value="<?php if (isset($this->options['recaptcha_secret_key'])) echo esc_attr($this->options['recaptcha_secret_key']); ?>" />
                </div>
                <!-- End General Options Panel -->
                <!-- Start Fraud Options Panel -->
                <div class="ss-panel ss-fraud-options">
                    <h3>Fraud Options</h3>
                    <div class="ss-checkbox">
                        <?php
                        $ischecked = '';
                        if (isset($this->options['enable_fraud']) && $this->options['enable_fraud'] == 'true')
                            $ischecked = "checked='checked'";
                        ?>
                            <input type="checkbox" id="enable_fraud" <?php echo $ischecked; ?> />
                            <label for="enable_fraud" class="ss-checkbox-label">Enable Fraud Options</label>
                    </div>
                    <?php
                        $fraud_message = '';
                        if (isset($this->options['fraud_message']) )
                            $fraud_message = $this->options['fraud_message'];
                        ?>
                        <label for="fraud_message">Displayed Message:</label>
                            <textarea id="fraud_message"><?php echo wp_sprintf('%s',$fraud_message); ?></textarea>
                            <?php
                        $fraud_velocity_attempts = 0;
                        if (isset($this->options['fraud_velocity_attempts']) )
                            $fraud_velocity_attempts = (string)"value='" . ((int)$this->options['fraud_velocity_attempts']) . "'";
                        ?>
                                <label for="fraud_velocity_attempts">How many failed attempts before blocking?</label>
                                    <input type="text" id="fraud_velocity_attempts" <?php echo $fraud_velocity_attempts; ?> />
                                    <?php
                        $fraud_velocity_timeout = 0;
                        if (isset($this->options['fraud_velocity_timeout']) )
                            $fraud_velocity_timeout = "value='" . ((int)$this->options['fraud_velocity_timeout']) . "'";
                        ?>
                                        <label for="fraud_velocity_timeout">How long (in minutes) should we keep a tally of recent failures?</label>
                                            <input type="text" id="fraud_velocity_timeout" <?php echo $fraud_velocity_timeout; ?> />
                </div>
                <!-- End Fraud Options Panel -->
            </div>
            <div class="ss-lower-container clearfix">
                <!-- Start Email Options Panel -->
                <div class="ss-panel">
                    <h3>Email Options</h3>
                    <label for="ssd_from_name">From Name:</label>
                    <input type="text" id="ssd_from_name" class="regular-text" value="<?php echo isset($this->options['from_name']) ? esc_attr($this->options['from_name']) : ''; ?>" />
                    <label for="ssd_from_email">From Email Address:</label>
                    <input type="text" id="ssd_from_email" class="regular-text" value="<?php echo isset($this->options['from_email']) ? esc_attr($this->options['from_email']) : ''; ?>" />
                    <label for="ssd_payment_email">Payment Email Address:</label>
                    <input type="text" id="ssd_payment_email" class="regular-text" value="<?php echo isset($this->options['payment_email']) ? esc_attr($this->options['payment_email']) : ''; ?>" />
                    <label for="customer_email_subject">Customer Email Subject:</label>
                    <input type="text" id="customer_email_subject" class="regular-text" value="<?php echo esc_attr($emailsubject); ?>" />
                    <label>Customer Email Template:</label>
                    <?php
                    wp_editor($emailbody, 'customer_email_body', array('textarea_name'=>'customer_email_template','textarea_rows'=>10,'wpautop'=>false));
                    ?>
                    <p>
                        The following variables can be used in the above email templates.
                    </p>
                    <table class="widefat fixed" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Variable</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>%firstname%</td>
                                <td>Customer's First Name</td>
                            </tr>
                            <tr>
                                <td>%lastname%</td>
                                <td>Customer's Last Name</td>
                            </tr>
                            <tr>
                                <td>%amount%</td>
                                <td>The total dollar amount of the purchase formatted with a dollar sign</td>
                            </tr>
                            <tr>
                                <td>%productinfo%</td>
                                <td>The product information formatted as follows (when sku is available): product name (sku)</td>
                            </tr>
                            <tr>
                                <td>%billingaddress%</td>
                                <td>Customer's Billing Address</td>
                            </tr>
                            <tr>
                                <td>%shippingaddress%</td>
                                <td>Customer's Shipping Address</td>
                            </tr>
                            <tr>
                                <td>%additionalinformation%</td>
                                <td>Any additional information collected from the customer</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary ss-button" value="Save Changes">
            </p>
        </div>
        <?php
    }
    function save_options() {
        $data = array(
            'secret_key' => $_POST['secret_key'],
            'public_key' => $_POST['public_key'],
            'payment_email' => $_POST['payment_email'],
            'from_email' => $_POST['from_email'],
            'from_name' => $_POST['from_name'],
            'email_body' => $_POST['email_body'],
            'email_subject' => $_POST['email_subject'],
            'enable_button_builder' => $_POST['enable_button_builder'],
            'enable_fraud' => $_POST['enable_fraud'],
            'fraud_message' => (string)$_POST['fraud_message'],
            'fraud_velocity_attempts' => (int)$_POST['fraud_velocity_attempts'],
            'fraud_velocity_timeout' => (int)$_POST['fraud_velocity_timeout'],
            'enable_recaptcha' => $_POST['enable_recaptcha'],
            'recaptcha_site_key' => $_POST['recaptcha_site_key'],
            'recaptcha_secret_key' => $_POST['recaptcha_secret_key'],

        );

        update_option('securesubmit_options', $data);

        echo 'Settings saved.';
        exit;
    }
    function report_page(){
        global $wpdb;
        global $table_name;
        $shipping = false;
        $additional = false;
        if(isset($_POST['ship']) && $_POST['ship'] == 'on'){ $shipping = true;}
        if(isset($_POST['additional']) && $_POST['additional'] == 'on'){ $additional = true;}
        ?>
            <style>
                .even {
                    background-color: #bbb;
                }
            </style>
                <div class="wrap ss-wrap">
                    <h1 class="ss-wp-heading-inline"><span class="hidden-small">SecureSubmit Donate / Pay Now&nbsp;</span><?php echo (isset($title) ? $title : esc_html(get_admin_page_title())) ?></h1>
                    <form name="report_data" method="post" action="admin.php?page=sub-reporting">
                        <div id="message" class="updated hidden">
                            <p></p>
                        </div>
                        <div class="ss-panel ss-report-otions-panel">
                            <h3>Report Options</h3>
                            <div class="ss-checkbox">
                                <input type="checkbox" name="ship" id="ship" <?php if($shipping){echo 'checked="checked"'; } ?> >
                                <label for="ship" class="ss-checkbox-label">Include Shipping Information</label>
                            </div>
                            <div class="ss-checkbox">
                                <input type="checkbox" name="additional" id="additional" <?php if($additional){echo 'checked="checked"'; } ?> >
                                <label for="additional" class="ss-checkbox-label">Include Additional Information</label>
                            </div>
                            <input type="submit" class="button-primary ss-report-button" value="View Transactions">
                        </div>
                    </form>
                    <form name="export_data" method="post" action="">
                        <input type="hidden" name="export_transaction" value="true">
                        <input type="submit" class="button-primary" value="Export Transactions">
                    </form>
                    <?php if($_SERVER['REQUEST_METHOD'] =='POST'){
                    $transactions = $wpdb->get_results('select * from '.$table_name.' order by id desc limit 10000;' , 'ARRAY_A');
                    $count = 0;
                    ?>
                    <br>
                    <br>
                    <br>
                    <br>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Amount</th>
                                <th>Product ID</th>
                                <th>Time</th>
                                <th>Billing Name</th>
                                <th>Billing Address</th>
                                <th>Billing City</th>
                                <th>Billing State</th>
                                <th>Billing Zip</th>
                                <th>Billing Email</th>
                                <?php if($shipping){ ?>
                                    <th>Shipping Name</th>
                                    <th>Shipping Address</th>
                                    <th>Shipping City</th>
                                    <th>Shipping State</th>
                                    <th>Shipping Zip</th>
                                <?php }
                                if($additional){
                                ?>
                                    <th>Additional Info 1</th>
                                    <th>Additional Info 2</th>
                                    <th>Additional Info 3</th>
                                    <th>Additional Info 4</th>
                                    <th>Additional Info 5</th>
                                    <th>Additional Info 6</th>
                                    <th>Additional Info 7</th>
                                    <th>Additional Info 8</th>
                                    <th>Additional Info 9</th>
                                    <th>Additional Info 10</th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <?php foreach($transactions as $key=>$row){
                        if ($count % 2 == 0){
                            echo '<tr class="even">';
                        }else{
                            echo '<tr class="odd">';
                        } ?>
                            <td>
                                <?php echo $row['transaction_id']; ?>
                            </td>
                            <td>
                                <?php echo $row['amount']; ?>
                            </td>
                            <td>
                                <?php echo $row['product_id']; ?>
                            </td>
                            <td>
                                <?php echo $row['time']; ?>
                            </td>
                            <td>
                                <?php echo $row['billing_name']; ?>
                            </td>
                            <td>
                                <?php echo $row['billing_address']; ?>
                            </td>
                            <td>
                                <?php echo $row['billing_city']; ?>
                            </td>
                            <td>
                                <?php echo $row['billing_state']; ?>
                            </td>
                            <td>
                                <?php echo $row['billing_zip']; ?>
                            </td>
                            <td>
                                <?php echo $row['billing_email']; ?>
                            </td>
                            <?php if($shipping){ ?>
                                <td>
                                    <?php echo $row['shipping_name']; ?>
                                </td>
                                <td>
                                    <?php echo $row['shipping_address']; ?>
                                </td>
                                <td>
                                    <?php echo $row['shipping_city']; ?>
                                </td>
                                <td>
                                    <?php echo $row['shipping_state']; ?>
                                </td>
                                <td>
                                    <?php echo $row['shipping_zip']; ?>
                                </td>
                            <?php }
                            if($additional){ ?>
                                <td>
                                    <?php echo $row['additional_info1']; ?>
                                </td>
                                <td>
                                    <?php echo $row['additional_info2']; ?>
                                </td>
                                <td>
                                    <?php echo $row['additional_info3']; ?>
                                </td>
                                <td>
                                    <?php echo $row['additional_info4']; ?>
                                </td>
                                <td>
                                    <?php echo $row['additional_info5']; ?>
                                </td>
                                <td>
                                    <?php echo $row['additional_info6']; ?>
                                </td>
                                <td>
                                    <?php echo $row['additional_info7']; ?>
                                </td>
                                <td>
                                    <?php echo $row['additional_info8']; ?>
                                </td>
                                <td>
                                    <?php echo $row['additional_info9']; ?>
                                </td>
                                <td>
                                    <?php echo $row['additional_info10']; ?>
                                </td>
                            <?php } ?>
                        </tr>
                        <?php
                        $count++;
                        } ?>
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
        <div class="wrap ss-wrap">
            <h1 class="ss-wp-heading-inline"><span class="hidden-small">SecureSubmit Donate / Pay Now&nbsp;</span><?php echo (isset($title) ? $title : esc_html(get_admin_page_title())) ?></h1>
            <div id="message" class="updated hidden">
                <p></p>
            </div>
            <div class="ss-panel ss-faq-panel">
                <h3>How do I get started?</h3>
                <p>The default usage for SecureSubmit is as easy as putting the following in any page or post.</p>
                <pre>[securesubmit modal='true']</pre>
                <p> This will create a "Make Donation" button on the page which, when clicked, will open a modal window where the user can input their info and process the payment.</p>
            </div>
            <div class="ss-panel ss-faq-panel">
                <h3>I don't want to do a donation. How do I change the button text?</h3>
                <p>To change the button text you just add the field 'buttontext' to your setup and give it a value as follows.</p>
                <pre>[securesubmit modal='true' buttontext='Pay Now']</pre>
            </div>
            <div class="ss-panel ss-faq-panel">
                <h3>I need to collect extra information. How do I do that?</h3>
                <p>The plugin allows you to collect up to 10 additional fields of information. The field names are additional_info1 additional_info2 and so on.
                    <br> For the value just set the name of the field. The information collected will be included in the email you receive and will be stored on your server for later retrieval.</p>
                <pre>[securesubmit modal='true' additional_info1='Invoice Number' additional_info2='messagebox']</pre>
            </div>
            <div class="ss-panel ss-faq-panel">
                <h3>Can I set a default value other than $100?</h3>
                <p>Yes you can. Just add the attribute "amountdefault" and set the value equal to the amount you would like to see.</p>
                <pre>[securesubmit modal='true' additional_info1='Invoice Number' additional_info2='messagebox' buttontext='Pay Now' amountdefault='25.00']</pre>
            </div>
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
        $buttonText = isset($atts['buttontext']) ? $atts['buttontext'] : 'Make Donation';

        if (isset($atts['requirebilling']) && $atts['requirebilling'] === 'false')
            $requireBilling = false;
        else
            $requireBilling = true;

        if (isset($atts['requireshipping']) && $atts['requireshipping'] === 'true')
            $requireShipping = true;
        else
            $requireShipping = false;

        if (isset($atts['requirestate']) && $atts['requirestate'] === 'false')
            $requireState = false;
        else
            $requireState = true;

        if (isset($atts['additionalinformationtext']))
            $additionalInformationText = $atts['additionalinformationtext'];
        else
            $additionalInformationText = "Additional Information";

        if (isset($atts['modal']) && $atts['modal'] === 'true')
            $modal = true;
        else
            $modal = false;

        if (isset($atts['amountdefault']) && $atts['amountdefault'] != '')
            $amountdefault = $atts['amountdefault'];
        else
            $amountdefault = '100.00';

        $additionalFields = array();
        $additionalFieldTypes = array();

        if ($atts !== '') {
            //Check for additional_info fields
            $pattern = '/(^additional_info[1-9]$|additional_info$)/';
            $attsKeys = array_keys($atts);
            $additionalFields = preg_grep($pattern,$attsKeys);

            //Check for additional Field types
            $typePattern = '/_type$/';
            $additionalFieldTypes = preg_grep($typePattern,$attsKeys);
        }

        $productid = isset($atts['productid']) ? $atts['productid'] : '';
        $productname = isset($atts['productname']) ? $atts['productname'] : '';

        if (empty($productid)) {
            $prefix = substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
        } else {
            $prefix = 'secure_submit_' .$productid;
        }

        $billingRequired = '';
        update_option('secure_submit_'.$productid, $atts);

        if (isset($atts['ignorelinebreaks']) && $atts['ignorelinebreaks'] === 'true') {
            ?>
            [raw]
            <?php
        }
        $billingRequired = $requireBilling ? ' required' : '';
        $shippingRequired = $requireShipping ? ' required' : '';
        if ($modal) { ?>
            <div id="<?php echo $prefix; ?>_donation">
            </div>
            <script language="javascript" type="text/javascript">
                <?php if ($requireShipping) { ?>
                    var <?php echo $prefix; ?>_requireShipping = true;
                <?php } else { ?>
                    var <?php echo $prefix; ?>_requireShipping = false;
                <?php } ?>

                <?php if ($requireBilling) { ?>
                    var <?php echo $prefix; ?>_requireBilling = true;
                <?php } else { ?>
                    var <?php echo $prefix; ?>_requireBilling = false;
                <?php } ?>

                <?php
                if(count($additionalFields)>0){
                    echo "var " . $prefix . "_requireAdditionalInfo = true;";
                } else {
                    echo "var " . $prefix . "_requireAdditionalInfo = false;";
                }
                ?>

                jQuery.ajaxSetup({
                    cache: true
                });
                if (jQuery('#sss').length == 0)
                    jQuery('head').append(jQuery('<link rel="stylesheet" type="text/css" />').attr('href', '<?php echo plugins_url('assets/paybutton.css', __FILE__ ); ?>').attr('id', 'sss'));

                var trigger_button = jQuery("<div class='pay-button button-main'><a href='#Purchase' id='<?php echo $prefix; ?>_pay_now'><?php echo $buttonText; ?></a><div class='pay-button-border'>&nbsp;</div></div>");
                jQuery('#<?php echo $prefix; ?>_donation').append(trigger_button);

                jQuery('#<?php echo $prefix; ?>_pay_now').unbind().bind('click', function () {
                    <?php echo $prefix; ?>_trigger_payment();
                });

                // BUILD CONTROLS
                var <?php echo $prefix; ?>_modal_html = "<a class='boxclose modal-close' id='boxclose'>&times;</a>";

                // HEADER
                <?php echo $prefix; ?>_modal_html += "<div id='modal-header'>";

                <?php echo $prefix; ?>_modal_html += "<div style='float: left;'>";
                <?php if (!isset($atts["productimage"])) { ?>
                    <?php echo $prefix; ?>_modal_html += "<img src='<?php echo plugins_url('assets/donation.png', __FILE__); ?>' class='checkout-product-image' />";
                <?php } else if ($atts["productimage"] == 'none') { ?>
                    <?php echo $prefix; ?>_modal_html += "<img src='<?php echo plugins_url('assets/transparent.png', __FILE__); ?>' class='checkout-product-image' />";
                <?php } else { ?>
                    <?php echo $prefix; ?>_modal_html += "<img src='<?php echo isset($atts['productimage']) ? $atts["productimage"] : ''; ?>' class='checkout-product-image' />";
                <?php } ?>
                <?php echo $prefix; ?>_modal_html += "</div>";
                <?php echo $prefix; ?>_modal_html += "<input type='hidden' name='action' id='action' value='ssd_submit_payment'/>";
                <?php echo $prefix; ?>_modal_html += "<input type='hidden' name='product_sku' id='product_sku' value='<?php echo isset($atts['productid']) ? $atts['productid'] : get_the_title(); ?>'/>";
                <?php echo $prefix; ?>_modal_html += "<input type='hidden' name='product_id' id='product_id' value='<?php echo isset($atts['productid']) ? $atts['productid'] : get_the_ID(); ?>'/>";
                <?php echo $prefix; ?>_modal_html += "<div class='checkout-product-name'><?php echo isset($atts['productname']) ? $atts['productname'] : ''; ?></div>";

                if ('<?php echo isset($atts['amount']) ? $atts['amount'] : ''; ?>' != '') {
                    <?php echo $prefix; ?>_modal_html += "<div class='checkout-price'>$<?php echo isset($atts['amount']) ? $atts['amount'] : ''; ?></div>";
                    <?php echo $prefix; ?>_modal_html += "<input type='hidden' name='donation_amount' value='<?php echo isset($atts['amount']) ? $atts['amount'] : ''; ?>' />";
                } else {
                    <?php echo $prefix; ?>_modal_html += "<div class='donation-price'>Dollar Amount<br />$&nbsp;<input type='text' name='donation_amount' id='donation_amount' class='checkout-input donation-field' placeholder='<?php echo $amountdefault; ?>'></div>";
                }

                <?php echo $prefix; ?>_modal_html += "</div>";

                <?php echo $prefix; ?>_modal_html += "<div id='modal-body'>";

                // BILLING BODY
                var <?php echo $prefix; ?>_billing_html = "<div id='<?php echo $prefix; ?>_billing_panel'>";
                <?php echo $prefix; ?>_billing_html += "<div class='checkout-card-information'>Billing Information</div>";
                <?php echo $prefix; ?>_billing_html += "<div class='card-number'><input type='text' name='cardholder_name' id='cardholder_name' class='checkout-input checkout-card <?php echo $billingRequired; ?> ' placeholder='Name on Credit Card'></div>";
                <?php echo $prefix; ?>_billing_html += "<div class='card-number'><input type='text' name='cardholder_address' id='cardholder_address' class='checkout-input checkout-card <?php echo $billingRequired; ?>' placeholder='Credit Card Billing Address'></div>";
                <?php echo $prefix; ?>_billing_html += "<div class='card-number'>";
                <?php echo $prefix; ?>_billing_html += "<input type='text' name='cardholder_city' id='cardholder_city' class='checkout-input city-field<?php echo $billingRequired; ?>' placeholder='City'>";
                <?php if ($requireState) { ?>
                    <?php echo $prefix; ?>_billing_html += "<select name='cardholder_state' id='cardholder_state' class='checkout-input state-field<?php echo $billingRequired; ?>'><option value='AL'>AL</option><option value='AK'>AK</option><option value='AZ'>AZ</option><option value='AR'>AR</option><option value='CA'>CA</option><option value='CO'>CO</option><option value='CT'>CT</option><option value='DC'>DC</option><option value='DE'>DE</option><option value='FL'>FL</option><option value='GA'>GA</option><option value='HI'>HI</option><option value='ID'>ID</option><option value='IL'>IL</option><option value='IN'>IN</option><option value='IA'>IA</option><option value='KS'>KS</option><option value='KY'>KY</option><option value='LA'>LA</option><option value='ME'>ME</option><option value='MD'>MD</option><option value='MA'>MA</option><option value='MI'>MI</option><option value='MN'>MN</option><option value='MS'>MS</option><option value='MO'>MO</option><option value='MT'>MT</option><option value='NE'>NE</option><option value='NV'>NV</option><option value='NH'>NH</option><option value='NJ'>NJ</option><option value='NM'>NM</option><option value='NY'>NY</option><option value='NC'>NC</option><option value='ND'>ND</option><option value='OH'>OH</option><option value='OK'>OK</option><option value='OR'>OR</option><option value='PA'>PA</option><option value='RI'>RI</option><option value='SC'>SC</option><option value='SD'>SD</option><option value='TN'>TN</option><option value='TX'>TX</option><option value='UT'>UT</option><option value='VT'>VT</option><option value='VA'>VA</option><option value='WA'>WA</option><option value='WV'>WV</option><option value='WI'>WI</option><option value='WY'>WY</option></select>";
                <?php } ?>
                <?php echo $prefix; ?>_billing_html += "<input type='text' name='cardholder_zip' id='cardholder_zip' class='checkout-input zip-field<?php echo $billingRequired; ?>' placeholder='Zip'>";
                <?php echo $prefix; ?>_billing_html += "</div>";

                // Additional Info BODY
                var <?php echo $prefix; ?>_additional_html = "<div id='<?php echo $prefix; ?>_additional_panel'>";
                <?php echo $prefix; ?>_additional_html += "<div class='back-button'><a href='#billing' id='<?php echo $prefix; ?>_additional_back'>back</a></div>";
                <?php echo $prefix; ?>_additional_html += "<div class='checkout-card-information'><?php echo $additionalInformationText; ?></div>";
                <?php echo $prefix; ?>_additional_html += "<div style='overflow-y: auto; height: 200px;'>";
                <?php
                if(count($additionalFields)>0){
                    foreach($additionalFields as $key=>$value){
                        $required = '';
                        $field_type = "text";

                        foreach ($additionalFieldTypes as $key2=>$value2) {
                            if ($value . "_type" == $value2) {
                                $field_type = $atts[$value2];
                            }
                        }

                        if (strpos($field_type, '*') > -1) {
                            $required = ' required';
                            $field_type = str_replace('*', '', $field_type);
                        }

                        if ($field_type == "textarea") {
                            echo $prefix . '_additional_html += "<div class=\'card-number\'><textarea name=\''.$value.'\' id=\''.$value.'\' class=\'donation-textarea'.$required.'\' placeholder=\''.$atts[$value].'\'></textarea></div>";';
                        }
                        else if ($field_type == "dropdown") {
                            echo $prefix . '_additional_html += "<div class=\'card-number\'><select name=\''.$value.'\' id=\''.$value.'\' class=\'donation-textarea'.$required.'\'><option>Select an option below</option>";';
                            $options = explode("|", $atts[$value]);
                            foreach($options as $option) {
                                echo $prefix . '_additional_html += "<option>' . $option . '</option>";';
                            }
                            echo $prefix . '_additional_html += "</select></div>";';
                        } else if ($field_type == "radio") {
                            echo $prefix . '_additional_html += "<div class=\'card-number\'>";';
                            $options = explode("|", $atts[$value]);
                            foreach($options as $option) {
                                echo $prefix . '_additional_html += "<input type=\'radio\' name=\''.$value.'\' value=\'' . $option . '\'>' . $option . '</input><br />";';
                            }
                            echo $prefix . '_additional_html += "</div>";';
                        } else if ($field_type == "checkbox") {
                            echo $prefix . '_additional_html += "<input name=\'' . $value . '\' id=\'' . $value . '\' type=\'checkbox\'>&nbsp;<label style=\'display: inline\' for=\'' . $value . '\'>' . $atts[$value] . '</label>";';
                        } else if ($field_type == "label") {
                            $html_links = preg_replace('@((https?://)?([-\w]+\.[-\w\.]+)+\w(:\d+)?(/([-\w/_\.]*(\?\S+)?)?)*)@', "<a href=\'$1\' target=\'blank\'>$1</a>", $atts[$value]);
                            echo $prefix . '_additional_html += "<div class=\'card-number\'>' . $html_links . '</div>";';
                        }
                        else
                        {
                            echo $prefix . '_additional_html += "<div class=\'card-number\'><input name=\''.$value.'\' type=\''.$field_type.'\' id=\''.$value.'\' class=\'checkout-input checkout-card'.$required.'\' placeholder=\''.$atts[$value].'\'></div>";';
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
                <?php echo $prefix; ?>_shipping_html += "<div class='card-number'><input name='shipping_name' type='text' id='shipping_name' class='checkout-input checkout-card<?php echo $shippingRequired; ?>' placeholder='Shipping Name'></div>";
                <?php echo $prefix; ?>_shipping_html += "<div class='card-number'><input name='shipping_address' type='text' id='shipping_address' class='checkout-input checkout-card<?php echo $shippingRequired; ?>' placeholder='Address'></div>";
                <?php echo $prefix; ?>_shipping_html += "<div class='card-number'>";
                <?php echo $prefix; ?>_shipping_html += "<input type='text' name='shipping_city' id='shipping_city' class='checkout-input city-field<?php echo $shippingRequired; ?>' placeholder='City'>";
                <?php if ($requireState) { ?>
                    <?php echo $prefix; ?>_shipping_html += "<select id='shipping_state' name='shipping_state' class='checkout-input state-field<?php echo $shippingRequired; ?>'><option value='AL'>AL</option><option value='AK'>AK</option><option value='AZ'>AZ</option><option value='AR'>AR</option><option value='CA'>CA</option><option value='CO'>CO</option><option value='CT'>CT</option><option value='DC'>DC</option><option value='DE'>DE</option><option value='FL'>FL</option><option value='GA'>GA</option><option value='HI'>HI</option><option value='ID'>ID</option><option value='IL'>IL</option><option value='IN'>IN</option><option value='IA'>IA</option><option value='KS'>KS</option><option value='KY'>KY</option><option value='LA'>LA</option><option value='ME'>ME</option><option value='MD'>MD</option><option value='MA'>MA</option><option value='MI'>MI</option><option value='MN'>MN</option><option value='MS'>MS</option><option value='MO'>MO</option><option value='MT'>MT</option><option value='NE'>NE</option><option value='NV'>NV</option><option value='NH'>NH</option><option value='NJ'>NJ</option><option value='NM'>NM</option><option value='NY'>NY</option><option value='NC'>NC</option><option value='ND'>ND</option><option value='OH'>OH</option><option value='OK'>OK</option><option value='OR'>OR</option><option value='PA'>PA</option><option value='RI'>RI</option><option value='SC'>SC</option><option value='SD'>SD</option><option value='TN'>TN</option><option value='TX'>TX</option><option value='UT'>UT</option><option value='VT'>VT</option><option value='VA'>VA</option><option value='WA'>WA</option><option value='WV'>WV</option><option value='WI'>WI</option><option value='WY'>WY</option></select>";
                <?php } ?>
                <?php echo $prefix; ?>_shipping_html += "<input type='text' name='shipping_zip' id='shipping_zip' class='checkout-input zip-field<?php echo $shippingRequired; ?>' placeholder='Zip'>";
                <?php echo $prefix; ?>_shipping_html += "</div>";
                <?php echo $prefix; ?>_shipping_html += "<div class='pay-button button-next'><a href='#Purchase' id='<?php echo $prefix; ?>_shipping_next_button'>Next</a><div class='pay-button-border'>&nbsp;</div></div>";
                <?php echo $prefix; ?>_shipping_html += "<div class='powered_by'><img src='<?php echo plugins_url('assets/heart.png', __FILE__); ?>' /></div>";
                <?php echo $prefix; ?>_shipping_html += "</div>";

                // CARD BODY
                var <?php echo $prefix; ?>_card_html = "<div id='<?php echo $prefix; ?>_card_panel'>";
                <?php echo $prefix; ?>_card_html += "<div class='back-button'><a href='#shipping' id='<?php echo $prefix; ?>_card_back'>back</a></div>";
                <?php echo $prefix; ?>_card_html += "<div class='checkout-card-information'>Card Information</div>";
                <?php echo $prefix; ?>_card_html += "<div class='card-number'><input type='text' id='card_number' class='checkout-input checkout-card required' placeholder='Credit Card'></div>";
                <?php echo $prefix; ?>_card_html += "<div class='card-exp'><input type='text' id='card_exp' class='checkout-exp required' placeholder='MM/YY'></div>";
                <?php echo $prefix; ?>_card_html += "<div class='card-cvc'><input type='text' id='card_cvc' class='checkout-exp' placeholder='CVC'></div>";
                <?php echo $prefix; ?>_card_html += "<div class='clearfixcheckout'>&nbsp;</div>";
                <?php echo $prefix; ?>_card_html += "<div class='email-reciept'><input name='email_reciept' type='checkbox' id='email_reciept' checked='true'>&nbsp;<label for='email_reciept'>Email Receipt</label></div>";
                <?php echo $prefix; ?>_card_html += "<div class='email-address'><input name='email_address' type='text' id='email_address' class='checkout-email' placeholder='Customer Email Address'></div>";
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
                <?php echo $prefix; ?>_modal_html += <?php echo $prefix; ?>_failure_html;
                <?php echo $prefix; ?>_modal_html += <?php echo $prefix; ?>_success_html;

                <?php echo $prefix; ?>_modal_html += "</div>"; // BODY

                // ACTIONS
                function <?php echo $prefix; ?>_trigger_payment() {
                    var prefix = '<?php echo $prefix; ?>';
                    var $ = jQuery;

                    $('#modal-content').remove(); // a little clean-up
                    $('#modal-background').remove(); // a little clean-up

                    var frame = $('<iframe />', {
                        id: prefix + '_frame',
                        frameborder: 0,
                        allowTransparency: true
                    });
                    var background = $('<div />', {
                        id: 'modal-background'
                    });
                    var content = $('<div />', {
                        id: 'modal-content'
                    });
                    var donation = $('#' + prefix + '_donation');

                    donation.append(background);
                    donation.append(content);

                    frame.load(function () {
                        background.toggleClass("active");
                        content.fadeIn(400);

                        frame.find('html').html('<html><title></title><head></head></html>');

                        var stylesheet = $('<link rel="stylesheet" type="text/css" />');
                        stylesheet.attr('href', '<?php echo plugins_url( 'assets/checkout.css', __FILE__ ); ?>').attr('id', 'sss');

                        frame.contents().find('head').append(stylesheet);

                        var frameBody = frame.contents().find('body');
                        var form = $('<form />', {
                            id: prefix + '_form'
                        });

                        frameBody.append(form);
                        form.append(<?php echo $prefix; ?>_modal_html);

                        function configureCleanUp() {
                            $("#modal-launcher, #modal-background").click(function cleanUp() {
                                content.fadeOut(400);
                                background.toggleClass("active");
                            });

                            frameBody.find('.modal-close').click(function cleanUp() {
                                content.fadeOut(400);
                                background.toggleClass("active");
                            });
                        };

                        configureCleanUp();

                        $(function () {
                            if ($.browser && $.browser.msie && $.browser.version <= 9) {
                                frameBody.find("[placeholder]").focus(function () {
                                    if ($(this).val() == $(this).attr("placeholder"))
                                        $(this).val("");
                                }).blur(function () {
                                    if ($(this).val() == "") $(this).val($(this).attr("placeholder"));
                                }).blur();
                            }
                        });

                        function getPanel(panelName) {
                            return frameBody.find('#<?php echo $prefix; ?>_' + panelName + '_panel');
                        }

                        // Get Panels
                        var billingPanel = getPanel('billing');
                        var additionalPanel = getPanel('additional');
                        var shippingPanel = getPanel('shipping');
                        var processingPanel = getPanel('processing');
                        var cardPanel = getPanel('card');
                        var successPanel = getPanel('success');
                        var failurePanel = getPanel('failure');

                        // Bind Panels
                        // Billing Panel
                        var billingButton = billingPanel.find('#<?php echo $prefix; ?>_billing_next_button');

                        billingPanel.show();


                        billingButton.on('click', function (event) {
                            var continueProcessing = true;

                            billingPanel.find('.required').each(function (i, obj) {
                                if (continueProcessing) {
                                    if (jQuery(this).val() == '' || jQuery(this).val() == 'Select an option below') {
                                        var thisEle = jQuery(this);
                                        var elementText = thisEle.attr("placeholder");
                                        alert('Please complete all required fields before proceeding. \n[' + elementText + '] must be entered');
                                        continueProcessing = false;
                                        thisEle.focus();
                                        return;
                                    }
                                }
                            });

                            if (continueProcessing) {

                                billingPanel.hide();

                                if (<?php echo $prefix; ?>_requireAdditionalInfo) {
                                    additionalPanel.fadeIn();
                                } else if (<?php echo $prefix; ?>_requireShipping) {
                                    cardPanel.hide();
                                    if (frameBody.find("#shipping_same").attr("checked")) {
                                        cardPanel.fadeIn();
                                        frameBody.find("#shipping_name").val(frameBody.find("#cardholder_name").val());
                                        frameBody.find("#shipping_address").val(frameBody.find("#cardholder_address").val());
                                        frameBody.find("#shipping_city").val(frameBody.find("#cardholder_city").val());
                                        frameBody.find("#shipping_state").val(frameBody.find("#cardholder_state").val());
                                        frameBody.find("#shipping_zip").val(frameBody.find("#cardholder_zip").val());
                                    } else {
                                        shippingPanel.fadeIn();
                                    }
                                } else {
                                    shippingPanel.hide();
                                    cardPanel.fadeIn();
                                }


                            }





                            event.preventDefault();
                        });

                        // Additional Panel
                        var additionalNext = additionalPanel.find("#<?php echo $prefix; ?>_additional_next_button");
                        var additionalBack = additionalPanel.find("#<?php echo $prefix; ?>_additional_back");

                        additionalPanel.hide();


                        additionalNext.on("click", function (event) {
                            var continueProcessing = true;
                            additionalPanel.find('.required').each(function (i, obj) {
                                if (jQuery(this).val() == '' || jQuery(this).val() == 'Select an option below') {
                                    var thisEle = jQuery(this);
                                    alert('Please complete all required fields before proceeding.');
                                    continueProcessing = false;
                                    thisEle.focus();
                                    return;
                                }
                            });

                            if (continueProcessing) {
                                billingPanel.hide();
                                additionalPanel.hide();

                                if (<?php echo $prefix; ?>_requireShipping) {
                                    shippingPanel.fadeIn();
                                } else {
                                    cardPanel.fadeIn();
                                }
                            }

                            event.preventDefault();
                        });


                        additionalBack.on("click", function (event) {
                            additionalPanel.hide();
                            billingPanel.fadeIn();

                            event.preventDefault();
                        });

                        // Shipping Panel
                        var shippingNext = shippingPanel.find("#<?php echo $prefix; ?>_shipping_next_button");
                        var shippingBack = shippingPanel.find("#<?php echo $prefix; ?>_shipping_back");

                        shippingPanel.hide();

                        shippingNext.on("click", function (event) {
                            var continueProcessing = true;

                            shippingPanel.find('.required').each(function (i, obj) {
                                if (continueProcessing) {
                                    if (jQuery(this).val() == '' || jQuery(this).val() == 'Select an option below') {
                                        var thisEle = jQuery(this);
                                        var elementText = thisEle.attr("placeholder");
                                        alert('Please complete all required fields before proceeding. \n[' + elementText + '] must be entered');
                                        continueProcessing = false;
                                        thisEle.focus();
                                        return;
                                    }
                                }
                            });

                            if (continueProcessing) {
                                billingPanel.hide();
                                shippingPanel.hide();
                                cardPanel.fadeIn();
                            }
                            event.preventDefault();
                        });

                        if (<?php echo $prefix; ?>_requireShipping) {
                            shippingBack.on("click", function (event) {
                                billingPanel.fadeIn();
                                shippingPanel.hide();
                                cardPanel.hide();

                                event.preventDefault();
                            });
                        }

                        // Processing Panel
                        processingPanel.hide();

                        // Card Panel
                        var cardPay = cardPanel.find("#<?php echo $prefix; ?>_pay_button");
                        var cardBack = cardPanel.find("#<?php echo $prefix; ?>_card_back");

                        cardPanel.hide();

                        function clearPaymentFields() {
                            cardPanel.find('#card_number').val('');
                            cardPanel.find('#card_exp').val('');
                            cardPanel.find('#card_cvc').val('');
                        }



                        function <?php echo $prefix; ?>_secureSubmitResponseHandler(response) {
                            if (response.message) {
                                processingPanel.hide();
                                failurePanel.show();
                                failurePanel.find('.transaction-error').text(response.message);
                                configureCleanUp();
                                clearPaymentFields();
                            } else {
                                form.find('#securesubmit_token').remove();
                                var token_html = "<input type='hidden' id='securesubmit_token' name='securesubmit_token' value='" + response.token_value + "' />";
                                form.append(token_html);

                                <?php echo $prefix; ?>_do_post();
                            }
                        }

                        function <?php echo $prefix; ?>_do_post() {
                            var datastring = form.serialize();
                            var url = "<?php echo admin_url('admin-ajax.php'); ?>";

                            if ($.browser && $.browser.msie && $.browser.version <= 9) {
                                $(this).find('[placeholder]').each(function () {
                                    if ($(this).val() == $(this).attr("placeholder")) {
                                        $(this).val("");
                                    }
                                });
                            }

                            $.post(url, datastring, function (response) {
                                if (response.indexOf("successful") >= 0) {
                                    processingPanel.hide();
                                    successPanel.show();
                                } else {
                                    processingPanel.hide();
                                    failurePanel.show();
                                    failurePanel.find(".transaction-error").text(response);
                                }

                                configureCleanUp();
                                clearPaymentFields();
                            });
                        }


                        function <?php echo $prefix; ?>_tokenize() {
                            var expirationParts = cardPanel.find('#card_exp').val().split("/");
                            var month = expirationParts[0];
                            var year = expirationParts[1];
                            var cardNumber = cardPanel.find('#card_number').val().replace(/\D/g, ''); // strip out non-numeric

                            // we need the year as four-digits
                            if (year && year.length === 2) {
                                year = '20' + year;
                            }

                            <?php
                            $pkey = isset($atts['public_key']) ? $atts['public_key'] : $this->options['public_key'];
                            ?>

                            hps.tokenize({
                                data: {
                                    public_key: '<?php echo esc_attr($pkey); ?>',
                                    number: cardNumber,
                                    cvc: cardPanel.find('#card_cvc').val(),
                                    exp_month: month,
                                    exp_year: year
                                },
                                success: function (response) {
                                    <?php echo $prefix; ?>_secureSubmitResponseHandler(response);
                                },
                                error: function (response) {
                                    <?php echo $prefix; ?>_secureSubmitResponseHandler(response);
                                }
                            });
                        }

                        cardPay.on("click", function (event) {

                            var continueProcessing = true;

                            frameBody.find('.required').each(function (i, obj) {
                                if (continueProcessing) {
                                    if (jQuery(this).val() == '' || jQuery(this).val() == 'Select an option below') {
                                        var thisEle = jQuery(this);
                                        var elementText = thisEle.attr("placeholder");
                                        alert('Please complete all required fields before proceeding. \n[' + elementText + '] must be entered');
                                        continueProcessing = false;
                                        thisEle.focus();
                                        return;
                                    }
                                }
                            });
                            if (continueProcessing) {
                                if (!frameBody.find('#donation_amount').val())
                                    frameBody.find('#donation_amount').val(frameBody.find('#donation_amount').attr('placeholder'));

                                frameBody.find('.donation-price').hide();
                                frameBody.find('.checkout-price').hide();

                                cardPanel.hide();
                                processingPanel.show();
                                $('#modal-launcher, #modal-background').unbind('click');
                                frameBody.find('.modal-close').unbind('click');
                                <?php echo $prefix; ?>_tokenize();
                            }


                            event.preventDefault();
                        });

                        cardBack.on("click", function (event) {
                            billingPanel.hide();

                            if ((<?php echo $prefix; ?>_requireShipping) && (!frameBody.find("#shipping_same").attr("checked"))) {
                                shippingPanel.show();
                            } else {
                                <?php if(count($additionalFields)>0){ ?>
                                    additionalPanel.fadeIn();
                                <?php } else { ?>
                                    billingPanel.fadeIn();
                                <?php } ?>
                            }

                            cardPanel.hide();

                            event.preventDefault();
                        });

                        // Success Panel
                        successPanel.hide();

                        // Failure Panel
                        var failureRetry = failurePanel.find("#<?php echo $prefix; ?>_retry_button");
                        failurePanel.hide();

                        failureRetry.on("click", function (event) {
                            frameBody.find('.donation-price').show();
                            frameBody.find('.checkout-price').show();

                            failurePanel.hide();
                            cardPanel.fadeIn();

                            event.preventDefault();
                        });

                    });

                    content.append(frame);
                }
            </script>
        <?php } else {

            if($this->isRecaptchaEnabled) {
                wp_enqueue_script('ssd-recaptcha', self::RECAPTCHA_CLIENT_URL);
            }

            ?>
            <div id="<?php echo $prefix; ?>_formContainer">
                <form id="<?php echo $prefix; ?>_form">
                    <input type="hidden" name="securesubmit_token" id="<?php echo $prefix; ?>_securesubmit_token" />
                    <input type="hidden" name="<?php echo $prefix; ?>_product_id" value="<?php echo $productid; ?>" />
                    <input type="hidden" name="action" value="ssd_submit_payment" />
                    <input type="hidden" name="prefix" value="<?php echo $prefix; ?>">

                    <?php if ($requireBilling) { ?>
                        <h3>Billing Information</h3>
                        <table width="100%">
                            <tr>
                                <td width="200">First Name:</td>
                                <td>
                                    <input class="form-text<?php echo $billingRequired; ?>" name="billing_firstname" type="text" />
                                </td>
                            </tr>
                            <tr>
                                <td>Last Name:</td>
                                <td>
                                    <input class="form-text<?php echo $billingRequired; ?>" name="billing_lastname" type="text" />
                                </td>
                            </tr>
                            <tr>
                                <td>Email Address:</td>
                                <td>
                                    <input class="form-text" name="email_address" type="text" />
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <input name='email_reciept' type='checkbox' id='email_reciept' checked='true'>&nbsp;
                                    <label style='display: inline;' for='email_reciept'>&nbsp;Email copy of my receipt</label>
                                </td>
                            </tr>
                            <tr>
                                <td>Address:</td>
                                <td>
                                    <input class="form-text<?php echo $billingRequired; ?>" name="billing_address" type="text" />
                                </td>
                            </tr>
                            <tr>
                                <td>City:</td>
                                <td><input class="form-text<?php echo $billingRequired; ?>" name="billing_city" type="text" /></td>
                            </tr>
                            <?php if ($requireState) { ?>
                                <tr>
                                    <td>State:</td>
                                    <td>
                                        <select name="billing_state" class="<?php echo $billingRequired; ?>">
                                            <option value="AL">Alabama</option>
                                            <option value="AK">Alaska</option>
                                            <option value="AZ">Arizona</option>
                                            <option value="AR">Arkansas</option>
                                            <option value="CA">California</option>
                                            <option value="CO">Colorado</option>
                                            <option value="CT">Connecticut</option>
                                            <option value='DC'>DC</option>
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
                            <?php } ?>
                            <tr>
                                <td>Zip/Postal Code:</td>
                                <td>
                                    <input class="form-text<?php echo $billingRequired; ?>" name="billing_zip" type="text" />
                                </td>
                            </tr>
                        </table>
                    <?php } ?>

                    <?php if ($requireShipping) { ?>
                        <h3>Shipping Information</h3>
                        <input type="checkbox " name="same_as_billing " value="1 " checked="true " onclick="jQuery( '#shipping_table').toggle(); ">&nbsp;<label for="same_as_billing ">Same as Billing Address</label>
                        <table width="100% " style="display:none; " id="shipping_table ">
                            <tr>
                                <td width="200 ">First Name:</td>
                                <td><input class="form-text<?php echo $shippingRequired; ?>" name="shipping_firstname" type="text"  /></td>
                            </tr>
                            <tr>
                                <td>Last Name:</td>
                                <td>
                                    <input class="form-text<?php echo $shippingRequired; ?>" type="text" name="shipping_lastname"  />
                                </td>
                            </tr>
                            <tr>
                                <td>Address:</td>
                                <td><input class="form-text<?php echo $shippingRequired; ?>" type="text" name="shipping_address"  /></td>
                            </tr>
                            <tr>
                                <td>City:</td>
                                <td>
                                    <input class="form-text<?php echo $shippingRequired; ?>" type="text" name="shipping_city"  />
                                </td>
                            </tr>
                            <?php if ($requireState) { ?>
                                <tr>
                                    <td>State:</td>
                                    <td>
                                        <select name="shipping_state " class="<?php echo $shippingRequired; ?>" >
                                            <option value="AL">Alabama</option>
                                            <option value="AK">Alaska</option>
                                            <option value="AZ">Arizona</option>
                                            <option value="AR">Arkansas</option>
                                            <option value="CA">California</option>
                                            <option value="CO">Colorado</option>
                                            <option value="CT">Connecticut</option>
                                            <option value='DC'>DC</option>
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
                            <?php } ?>
                            <tr>
                                <td>Shipping Zip Code:</td>
                                <td>
                                    <input class="form-text<?php echo $shippingRequired; ?>" type="text" name="shipping_zip" />
                                </td>
                            </tr>
                        </table>
                    <?php } ?>

                    <?php
                    if(count($additionalFields) > 0){
                        $additionalHTML = '<h3>' . $additionalInformationText . '</h3><table width="100%" id="additional_table">';

                        foreach($additionalFields as $key=>$value){
                            $required = '';
                            $field_type = "text";
                            $requiredIndicator = '';

                            foreach ($additionalFieldTypes as $key2=>$value2) {
                                if ($value . "_type" == $value2) {
                                    $field_type = $atts[$value2];
                                }
                            }

                            if (strpos($field_type, '*') > -1) {
                                $required = ' required';
                                $requiredIndicator = '*';
                                $field_type = str_replace('*', '', $field_type);
                            }
                            $field_type = trim($field_type);

                            if ($field_type == "textarea") {
                                $additionalHTML .= '<tr><td width="200">' . $atts[$value] . $requiredIndicator . '</td><td><textarea name="'.$value.'" id="'.$value.'"" class="donation-textarea"'.$required.'"></textarea></td></tr>';
                            }
                            else if ($field_type == "dropdown") {
                                $additionalHTML .= '<tr><td width="200" colspan="2"><select name="'.$value.'" id="'.$value.'"" class="donation-textarea'.$required.'"><option>Select an option below</option>';
                                $options = explode("|", $atts[$value]);
                                foreach($options as $option) {
                                    $additionalHTML .= '<option>' . $option . '</option>'.$requiredIndicator;
                                }
                                $additionalHTML .= '</select></td></tr>';
                            } else if ($field_type == "radio") {
                                $additionalHTML .= '<tr><td>';
                                $options = explode("|", $atts[$value]);
                                foreach($options as $option) {
                                    $additionalHTML .= '<input class=\'securesubmitradio\' type=\'radio\' name=\''.$value.'\' value=\'' . $option . '\'>&nbsp;' . $option . '</input><br />';
                                }
                                $additionalHTML .= '</td></tr>';
                            } else if ($field_type == "checkbox") {
                                $additionalHTML .= '<tr><td><input name=\'' . $value . '\' id=\'' . $value . '\' type=\'checkbox\'>&nbsp;<label style=\'display: inline;\' for=\'' . $value . '\'>' . $atts[$value] . '</label></td></tr>';
                            } else if ($field_type == "label") {
                                $html_links = preg_replace('@((https?://)?([-\w]+\.[-\w\.]+)+\w(:\d+)?(/([-\w/_\.]*(\?\S+)?)?)*)@', "<a href='$1' target='blank'>$1</a>", $atts[$value]);
                                $additionalHTML .= '<tr><td colspan="2">' . $html_links . '</td></tr>';
                            }
                            else
                            {
                                $additionalHTML .= "<tr><td width='200'>" . $atts[$value] . $requiredIndicator . "</td><td><input name='" . $value . "' type=$field_type id='" . $value . "' class='checkout-input checkout-card" . $required . "'></td></tr>";
                            }
                        }

                        $additionalHTML .= "</table>";

                        echo $additionalHTML;
                    }
                    ?>

                    <?php if (empty($productid)) { ?>
                        <h3>Card Information</h3>
                    <?php } else { ?>
                        <h3>Payment Information</h3>
                    <?php } ?>

                    <table width="100%">
                        <tr>
                            <td width="200">Card Number:</td>
                            <td>
                                <input class="form-text required" type="text" id="<?php echo $prefix; ?>_card_number" />
                            </td>
                        </tr>
                        <tr>
                            <td>Expiration:</td>
                            <td colspan="2">
                                <select id="<?php echo $prefix; ?>_exp_month" class="required">
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
                                <select id="<?php echo $prefix; ?>_exp_year" class="required">
                                </select>
                                <script>
                                  (function () {
                                    var myselect = document.getElementById("<?php echo $prefix; ?>_exp_year"),
                                        year = (new Date).getFullYear(),
                                        gen = function (e) {
                                            do { myselect.add(new Option(year++)); } while (e-- > 0);
                                        }(10);
                                  }())
                                </script>
                            </td>
                        </tr>
                        <tr>
                            <td>Card CVC:</td>
                            <td>
                                <input class="form-text" type="text" id="<?php echo $prefix; ?>_card_cvc" style="width: 45px;" />
                            </td>
                        </tr>
                        <tr>
                            <td>Amount:</td>
                            <td nowrap>$
                                <input class="form-text" id="donation_amount_secure" style="display: inline;" type="text" value="<?php echo $amountdefault; ?>" name="donation_amount" <?php if (!empty($productid)):?>disabled="disabled"
                                <?php endif;?>/></td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <div id="<?php echo $prefix; ?>-donate-response"></div>
                            </td>
                        </tr>
                        <?php if($this->isRecaptchaEnabled) { ?>
                            <tr>
                                <td colspan="2" width='100%'>
                                <center>
                                    <span id='ssd-recaptcha' class="g-recaptcha "></span>
                                </center>
                                </td>
                            </tr>
                        <?php } ?>

                        <tr>
                            <td colspan="2 " style="text-align: center; ">
                                <button id="<?php echo $prefix; ?>-securesubmit-button" class="button-primary">
                                    <?php echo $buttonText; ?>
                                </button>

                                <?php if ($modal) { ?>
                                    <button id="a<?php echo $prefix; ?>-modal-launcher" class="button-secondary">cancel</button>
                                <?php } ?>
                            </td>
                        </tr>
                    </table>
                </form>
            </div>
            <div id="<?php echo $prefix; ?>_success" style="display: none;">
                <strong>Your Payment was Successful. Thank you!</strong>
            </div>
        <?php } ?>

        <script type="text/javascript">
            function ssdRenderCaptcha() {
                var domElement = document.getElementById('ssd-recaptcha');
                var widgetId = grecaptcha.render(domElement, {
                    'sitekey': '<?php echo $this->recaptchaSiteKey ?>'
                });
                jQuery(domElement).attr('data-widget-id', widgetId);
            }
        </script>
        <script type="text/javascript">
            (function ($) {
                $(function () {
                    $('.securesubmitradio').change(
                        function () {
                            if (this.value.indexOf("(") > 0 && this.value.indexOf(")") > 0) {
                                var currentVal = this.value.substring(this.value.indexOf("(") + 2, this.value.indexOf(")"));
                                $('#donation_amount_secure').val(currentVal);
                            }
                        }
                    );

                    $("#a<?php echo $prefix; ?>-modal-launcher, #a<?php echo $prefix; ?>-modal-background, #a<?php echo $prefix; ?>-modal-close").click(function () {
                        $("#a<?php echo $prefix; ?>-modal-content,#a<?php echo $prefix; ?>-modal-background").toggleClass("active");
                    });

                    <?php
                    $pkey = isset($atts['public_key']) ? $atts['public_key'] : $this->options['public_key'];
                    ?>
                    var pk = '<?php echo esc_attr($pkey); ?>';
                    var url = "<?php echo admin_url('admin-ajax.php'); ?>";

                    $('#<?php echo $prefix; ?>-securesubmit-button').bind('click', a<?php echo $prefix; ?>_handleSubmit);

                    function a<?php echo $prefix; ?>_handleSubmit() {

                        var sameAsBilling = jQuery('[name="same_as_billing"]') && jQuery('[name="same_as_billing"]').is(':checked');

                        var continueProcessing = true;
                        jQuery("#<?php echo $prefix; ?>_form").find('.required').each(function (i, obj) {
                            if (continueProcessing) {
                                // skip validation if "same as billing" checked and field is shipping info
                                if (sameAsBilling && this.name.indexOf('shipping_') !== -1) {
                                    return;
                                }
                                if (jQuery(this).val() == '' || jQuery(this).val() == 'Select an option below') {
                                    var thisEle = jQuery(this);
                                    var elementText = thisEle.closest('td').prev('td').text();
                                    alert('Please complete all required fields before proceeding. \n' + elementText + ' must be entered');
                                    continueProcessing = false;
                                    thisEle.focus();
                                    return;
                                }
                            }
                        });
                        if (continueProcessing) {

                            var cardNumber = $('#<?php echo $prefix; ?>_card_number').val().replace(/\D/g, ''); // strip out non-numeric

                            hps.tokenize({
                                data: {
                                    public_key: pk,
                                    number: cardNumber,
                                    cvc: $('#<?php echo $prefix; ?>_card_cvc').val(),
                                    exp_month: $('#<?php echo $prefix; ?>_exp_month').val(),
                                    exp_year: $('#<?php echo $prefix; ?>_exp_year').val()
                                },
                                success: function (response) {
                                    a<?php echo $prefix; ?>_secureSubmitResponseHandler(response);
                                },
                                error: function (response) {
                                    a<?php echo $prefix; ?>_secureSubmitResponseHandler(response);
                                }
                            });

                            $('#<?php echo $prefix; ?>-securesubmit-button').hide();
                        }



                        return false;
                    };

                    function a<?php echo $prefix; ?>_secureSubmitResponseHandler(response) {
                        if (response.message) {
                            alert(response.message);
                            $('#<?php echo $prefix; ?>-securesubmit-button').show();
                        } else {
                            $('#<?php echo $prefix; ?>_securesubmit_token').val(response.token_value);
                            a<?php echo $prefix; ?>_chargeToken();
                        }
                    }

                    function a<?php echo $prefix; ?>_chargeToken() {
                        var form = $('#<?php echo $prefix; ?>_form');
                        var sameAsBilling = jQuery('[name="same_as_billing"]') && jQuery('[name="same_as_billing"]').is(':checked');
                        var continueProcessing = true;

                        form.find('.required').each(function (i, obj) {
                            if (continueProcessing) {
                                if (sameAsBilling && this.name.indexOf('shipping_') !== -1) {
                                    return;
                                }
                                if (jQuery(this).val() == '' || jQuery(this).val() == 'Select an option below') {
                                    alert('Please complete all required fields before proceeding.');
                                    $('#<?php echo $prefix; ?>-securesubmit-button').show();
                                    continueProcessing = false;
                                    return;
                                }
                            }
                        });

                        if (continueProcessing) {
                            var datastring = form.serialize();
                            var url = "<?php echo admin_url('admin-ajax.php'); ?>";

                            //wat?!
                            if ($.browser && $.browser.msie && $.browser.version <= 9) {
                                $(this).find('[placeholder]').each(function () {
                                    if ($(this).val() == $(this).attr("placeholder")) {
                                        $(this).val("");
                                    }
                                });
                            }

                            $.post(url, datastring, function (response) {
                                if (response.indexOf("successful") >= 0) {
                                    $('#<?php echo $prefix; ?>_card_number').val('');
                                    $('#<?php echo $prefix; ?>_card_cvc').val('');
                                    $('#<?php echo $prefix; ?>_formContainer').hide();
                                    $('#<?php echo $prefix; ?>_success').show();

                                } else {
                                    alert(response);
                                    if (grecaptcha) {
                                        grecaptcha.reset($(".g-recaptcha").attr('data-widgit-id'));
                                    }
                                    $('#<?php echo $prefix; ?>-securesubmit-button').show();
                                }
                            });
                        }
                    }
                });
            })(jQuery);
        </script>
        <?php if (isset($atts['ignorelinebreaks']) && $atts['ignorelinebreaks'] === 'true') { ?>
            [/raw]
        <?php } ?>
            <script src="<?php echo plugins_url( 'js/jquery.securesubmit.js', __FILE__ ); ?>"></script>
        <?php
        return ob_get_clean();
    }

    function isValidRecaptchaToken($token){

        $curl    = curl_init();
        $recaptchaInfo = array("secret" => $this->recaptchaSecretKey, "response" => $token);
        curl_setopt_array($curl, array(
            CURLOPT_URL            => self::RECAPTCHA_VERIFY_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $recaptchaInfo,
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        if(empty($response)) {
            return false;
        }
        $response = json_decode($response,true);
        return isset($response['success']) && $response['success'] == true;
    }

    function submit_payment() {
        global $wpdb;
        global $table_name;

        if(isset($_POST['g-recaptcha-response'])){
            if(!$this->isValidRecaptchaToken($_POST['g-recaptcha-response'])){
                die('Invalid recaptcha detected, please try to submit again.');
            }
        }

        $body = "";
        $enable_fraud = false;
        $fraud_message = "";
        $fraud_velocity_attempts = 3;
        $fraud_velocity_timeout = 10;

        $secureToken = isset($_POST['securesubmit_token']) ? $_POST['securesubmit_token'] : '';
        $amount = isset($_POST['donation_amount'])
            ? floatval(filter_var($_POST['donation_amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION))
            : 0;
        $atts = get_option('secure_submit_'. $_POST['product_id']);

        $skey = isset($atts['secret_key']) ? $atts['secret_key'] : $this->options['secret_key'];
        $requireState = isset($atts['requirestate']) ? $atts['requirestate'] : true;

        if ($this->options['enable_fraud'] != null && $this->options['enable_fraud'] != '') {
            $enable_fraud = (bool)($this->options['enable_fraud'] === 'true');
        }

        if ($this->options['fraud_message'] != null && $this->options['fraud_message'] != '') {
            $fraud_message = $this->options['fraud_message'];
        }

        if ($this->options['fraud_velocity_attempts'] != null && $this->options['fraud_velocity_attempts'] != '') {
            $fraud_velocity_attempts = (int)$this->options['fraud_velocity_attempts'];
        }

        if ($this->options['fraud_velocity_timeout'] != null && $this->options['fraud_velocity_timeout'] != '') {
            $fraud_velocity_timeout = (int)$this->options['fraud_velocity_timeout'];
        }

        if ($this->options['email_body'] != null && $this->options['email_body'] != '') {
            $body = $this->options['email_body'];
        }
        else {
            $body = '%firstname%,<br /><br />Thank you for your payment!<br /><br />';
            $body .= '<h2>%productinfo%</h2>';
            $body .= '<br/>%billingaddress%';
            $body .= '<br/>%shippingaddress%';
            $body .= '<br/>%additionalinformation%';
        }

        if ($amount === 0)
        {
            $amount = isset($atts['amount']) ? $atts['amount'] : 0;
            $memo = isset($atts['memo']) ? $atts['memo'] : 0;
            $productid = isset($atts['productid']) ? $atts['productid'] : 0;
            $productname = isset($atts['productname']) ? $atts['productname'] : 0;
        }
        //if productid is not already set in $attrs assign it from POST
        if(!isset($atts['productid'])){
            $productid = (!empty($_POST['product_id'])) ? $_POST['product_id'] : '';
        }
        //if productname is not already set in $attrs assign it from POST
        if(!isset($atts['productname'])){
            $productname = (!empty($_POST['product_name'])) ? $_POST['product_name'] : '';
        }

        //product info
        if (!empty($productname)) {
            $email_productinfo = '<h3>Product Information</h3>';
            $email_productinfo .= 'Product Id: ' . $productid . '<br/>';
            $email_productinfo .= 'Product Name: ' . $productname . '<br/>';
        }

        // modal
        if (isset($_POST['cardholder_name'])) {
            list($first, $middle, $last) = explode (" ", $_POST['cardholder_name']);

            if (isset($last)) {
                $billing_firstname = $first;
                $billing_lastname = $last;
            } else {
                $billing_firstname = $first;
                $billing_lastname = $middle;
            }

            list($shipfirst, $shipmiddle, $shiplast) = explode (" ", $_POST['shipping_name']);

            if (isset($shiplast)) {
                $shipping_firstname = $shipfirst;
                $shipping_lastname = $shiplast;
            } else {
                $shipping_firstname = $shipfirst;
                $shipping_lastname = $shipmiddle;
            }

            $billing_address = isset($_POST['cardholder_address']) ? $_POST['cardholder_address'] : '';
            $billing_city= isset($_POST['cardholder_city']) ? $_POST['cardholder_city'] : '';
            if ($requireState)
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
            if ($requireState)
                $billing_state = isset($_POST['billing_state']) ? $_POST['billing_state'] : '';
            $billing_zip = isset($_POST['billing_zip']) ? $_POST['billing_zip'] : '';
            $billing_email = isset($_POST['email_address']) ? $_POST['email_address'] : '';

            $shipping_firstname = isset($_POST['shipping_firstname']) ? $_POST['shipping_firstname'] : '';
            $shipping_lastname = isset($_POST['shipping_lastname']) ? $_POST['shipping_lastname'] : '';
            $shipping_address = isset($_POST['shipping_address']) ? $_POST['shipping_address'] : '';
            $shipping_city= isset($_POST['shipping_city']) ? $_POST['shipping_city'] : '';
            $shipping_state = isset($_POST['shipping_state']) ? $_POST['shipping_state'] : '';
            $shipping_zip = isset($_POST['shipping_zip']) ? $_POST['shipping_zip'] : '';
        }

        // billing info
        $email_billinginfo = '<h3>Billing Information</h3>';
        $email_billinginfo .= 'Name: ' . $billing_firstname . ' ' . $billing_lastname . '<br/>';
        $email_billinginfo .= 'Address: ' . $billing_address . '<br/>';
        $email_billinginfo .= 'City: ' . $billing_city . '<br/>';
        if ($requireState)
            $email_billinginfo .= 'State: ' . $billing_state . '<br/>';
        $email_billinginfo .= 'Zip: ' . $billing_zip . '<br/>';
        $email_billinginfo .= 'Email: ' . $billing_email . '<br/>';

        if (isset($_POST['same_as_billing']) || $shipping_address === '') {
            $shipping_firstname = $billing_firstname;
            $shipping_lastname = $billing_lastname;
            $shipping_address = $billing_address;
            $shipping_city = $billing_city;
            if ($requireState)
                $shipping_state = $billing_state;
            $shipping_zip = $billing_zip;
        }

        // shipping info
        $email_shippinginfo = '<h3>Shipping Information</h3>';
        $email_shippinginfo .= 'Name: ' . $shipping_firstname . ' ' . $shipping_lastname . '<br/>';
        $email_shippinginfo .= 'Address: ' . $shipping_address . '<br/>';
        $email_shippinginfo .= 'City: ' . $shipping_city . '<br/>';
        if ($requireState)
            $email_shippinginfo .= 'State: ' . $shipping_state . '<br/>';
        $email_shippinginfo .= 'Zip: ' . $shipping_zip . '<br/>';


        $email_additionalinfo = '<h3>Additional Information</h3>';
        for ($i=1; $i < 100; $i++) {
            if (isset($_POST['additional_info' . strval($i)]) && !empty($_POST['additional_info' . strval($i)])) {
                $email_additionalinfo .= 'additional_info' . strval($i) . ": " . $_POST['additional_info' . strval($i)] . '<br/>';
            }
        }


        try {
            // check if advanced fraud is enabled
            if ($enable_fraud) {
                if(array_key_exists("HTTP_X_FORWARDED_FOR",$_SERVER) && $_SERVER["HTTP_X_FORWARDED_FOR"] != ""){
                    $IParray = array_values(array_filter(explode(',',$_SERVER['HTTP_X_FORWARDED_FOR'])));
                    $IP = end($IParray);
                }else{
                    $IP = $_SERVER["REMOTE_ADDR"];
                }

                $HPS_VarName = "HeartlandHPS_FailCount" . md5($IP);
                $HeartlandHPS_FailCount = (int)get_transient( $HPS_VarName );
                $issuerResponse = get_transient( $HPS_VarName . 'IssuerResponse' );

                if ($HeartlandHPS_FailCount >= $fraud_velocity_attempts) {
                    sleep(5);
                    throw new HpsException(wp_sprintf('%s %s',$fraud_message, $issuerResponse));
                }
            }

            $config = new HpsServicesConfig();

            $config->secretApiKey = esc_attr($skey);
            $config->versionNumber = '1648';
            $config->developerId = '002914';

            $chargeService = new HpsCreditService($config);

            $address = new HpsAddress();
            $address->address = $billing_address;
            $address->city = $billing_city;
            if ($requireState)
                $address->state = $billing_state;
            $address->zip = $billing_zip;

            $cardHolder = new HpsCardHolder();
            $cardHolder->firstName = $billing_firstname;
            $cardHolder->lastName = $billing_lastname;
            $cardHolder->email = $billing_email;
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
                $details
            );

            add_filter('wp_mail_content_type', create_function('', 'return "text/html"; '));

            $body = str_replace('%firstname%', $billing_firstname, $body);
            $body = str_replace('%lastname%', $billing_lastname, $body);
            $body = str_replace('%amount%', $amount, $body);
            $body = str_replace('%productinfo%', $email_productinfo, $body);
            $body = str_replace('%billingaddress%', $email_billinginfo, $body);
            $body = str_replace('%shippingaddress%', $email_shippinginfo, $body);
            $body = str_replace('%additionalinformation%', $email_additionalinfo, $body);

            $email_subject = $this->options['email_subject'];

            $email_subject = str_replace('%firstname%', $billing_firstname, $email_subject);
            $email_subject = str_replace('%lastname%', $billing_lastname, $email_subject);
            $email_subject = str_replace('%amount%', $amount, $email_subject);
            $email_subject = str_replace('%productinfo%', $email_productinfo, $email_subject);
            $email_subject = str_replace('%billingaddress%', $email_billinginfo, $email_subject);
            $email_subject = str_replace('%shippingaddress%', $email_shippinginfo, $email_subject);
            $email_subject = str_replace('%additionalinformation%', $email_additionalinfo, $email_subject);

            $header = '';

            if($this->options['from_name']){
                $fromAddress = esc_attr($this->options['from_email']);
                $fromName = esc_attr($this->options['from_name']);
                $header = 'From: "' . $fromName . '" <'.$fromAddress.'>'."\r\n";
            }

            // send merchant email
            if ($header != '') {
                wp_mail(esc_attr($this->options['payment_email']), 'SecureSubmit $' . $amount . ' Payment Received', $body, $header );
            } else {
                wp_mail(esc_attr($this->options['payment_email']), 'SecureSubmit $' . $amount . ' Payment Received', $body );
            }

            // send customer email
            if (isset($_POST["email_reciept"]) && isset($_POST["email_address"])) {
                if($header != ''){
                    wp_mail(esc_attr($_POST["email_address"]), $email_subject, $body, $header);
                }else{
                    wp_mail(esc_attr($_POST["email_address"]), $email_subject, $body);
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
            if ($requireState)
                $insert_array['shipping_state'] = $shipping_state;
            $insert_array['shipping_zip']       = $shipping_zip;
            $insert_array['product_id']         = (isset($productid) && is_numeric($productid) ? $productid : '0');
            $insert_array['amount']             = $amount;
            $insert_array['transaction_id']     = $transaction_id;

            for ($i=1; $i < 100; $i++) {
                if (isset($_POST['additional_info' . strval($i)]) && !empty($_POST['additional_info' . strval($i)])) {
                    $insert_array['additional_info' . strval($i)] = $_POST['additional_info' . strval($i)];
                }
            }

            $rows_affected = $wpdb->insert($table_name, $insert_array);

        } catch (HpsException $e) {
            // if advanced fraud is enabled, increment the error count
            if ($enable_fraud) {
                set_transient($HPS_VarName, $HeartlandHPS_FailCount+1, MINUTE_IN_SECONDS * $fraud_velocity_timeout);
                if ($HeartlandHPS_FailCount < $fraud_velocity_attempts){
                    set_transient($HPS_VarName . 'IssuerResponse', $e->getMessage(), MINUTE_IN_SECONDS * $fraud_velocity_timeout);
                }
            }

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
            UNIQUE KEY id (id)
           );";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            update_site_option( "jal_db_version", $jal_db_version);
            // set some defaults values to enable velocity checks
            $data = array(
                'enable_fraud' => 'true',
                'fraud_message' => (string)'Please contact us to complete the transaction.',
                'fraud_velocity_attempts' => (int)3,
                'fraud_velocity_timeout' => (int)10,
            );

            update_option('securesubmit_options', $data);




        }
    }

    function jal_update_db_check(){
        global $jal_db_version;
        if (get_site_option( 'jal_db_version' ) != $jal_db_version){
            $this->jal_install();
        }
    }
}
