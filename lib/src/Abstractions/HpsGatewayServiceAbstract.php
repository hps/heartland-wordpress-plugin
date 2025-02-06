<?php

abstract class HpsGatewayServiceAbstract
{
    protected $_config     = null;
    protected $_baseConfig = null;
    protected $_url        = null;
    protected $_amount   = null;
    protected $_currency = null;
    protected $_filterBy = null;
    const MIN_OPENSSL_VER = 268439615; //OPENSSL_VERSION_NUMBER openSSL 1.0.1c

    public function __construct(HpsConfigInterface $config = null)
    {
        if ($config != null) {
            $this->_config = $config;
        }
    }

    public function servicesConfig()
    {
        return $this->_config;
    }

    public function setServicesConfig($value)
    {
        $this->_config = $value;
    }

    protected function submitRequest($url, $headers, $data = null, $httpVerb = 'POST', $keyType = HpsServicesConfig::KEY_TYPE_SECRET, $options = null)
    {
        if ($this->_isConfigInvalid()) {
            throw new HpsAuthenticationException(
                esc_attr(HpsExceptionCodes::INVALID_CONFIGURATION),
                "The HPS SDK has not been properly configured. "
                ."Please make sure to initialize the config "
                ."in a service constructor."
            );
        }

        if (!$this->_config->validate($keyType) && ($this->_config->username == null && $this->_config->password == null)) {
            $type = $this->_config->getKeyType($keyType);
            $message = "The HPS SDK requires a valid {$keyType} API key to be used";
            if ($type == $keyType) {
                $message .= ".";
            } else {
                $message .= ", but a(n) {$type} key is currently configured.";
            }
            throw new HpsAuthenticationException(
                esc_attr(HpsExceptionCodes::INVALID_CONFIGURATION),
                esc_attr($message)
            );
        }

        $logger = HpsLogger::getInstance();

        try {

            $args = array();
            $args['headers'] = $headers;
            $args['sslverify'] = false;
            $args['method'] = $httpVerb;
            $args['timeout'] = 100;
            $args['body'] = (string)$data;
            $args['httpversion'] = '1.0';
            $args['blocking'] = true;

            error_log(print_r($args, true));
            $response = wp_remote_post($url, $args);
            $body =  wp_remote_retrieve_body( $response );

            if ( is_wp_error( $response ) ) {
                $error_message = $response->get_error_message();
                echo "Something went wrong: ". esc_attr($error_message);
            } else {
                error_log('remote response start');
                error_log(print_r($response,true));
                error_log('remote response end');
            }

            $curlResponse = $body;
            $curlInfo['http_code'] = wp_remote_retrieve_response_code($response);
            $curlError = wp_remote_retrieve_response_code($response);

            $logger->log('Response data:', $curlResponse);
            $logger->log('Curl info', $curlInfo);
            $logger->log('Curl error', $curlError);

            if ($curlError == 28) { //CURLE_OPERATION_TIMEOUTED
                throw new HpsException("gateway_time-out");
            }
            if ($data != null) {
                $logger->log('Request data', $data);
            }
            $logger->log('Request headers', $headers);

            if ($curlError == 35) { //CURLE_SSL_CONNECT_ERROR
                $err_msg = 'PHP-SDK cURL TLS 1.2 handshake failed. If you have any questions, please contact Specialty Products Team at 866.802.9753.';
                if ( extension_loaded('openssl') && OPENSSL_VERSION_NUMBER <  self::MIN_OPENSSL_VER ) { // then you don't have openSSL 1.0.1c or greater
                    $err_msg .= 'Your current version of OpenSSL is ' . OPENSSL_VERSION_TEXT . 'You do not have the minimum version of OpenSSL 1.0.1c which is required for curl to use TLS 1.2 handshake.';
                }
                throw new HpsGatewayException($curlError,$err_msg);
            }
            return $this->processResponse($curlResponse, $curlInfo, $curlError);
        } catch (Exception $e) {
            throw new HpsGatewayException(
                $e->getCode() != null ? esc_attr($e->getCode()) : esc_attr(HpsExceptionCodes::UNKNOWN_GATEWAY_ERROR),
                $e->getMessage() != null ? esc_attr($e->getMessage()) : 'Unable to process transaction',
                null,
                null,
                esc_attr($e)
            );
        }
    }

    protected function _isConfigInvalid()
    {
        if ($this->_config == null && (
                $this->_config->secretApiKey == null ||
                $this->_config->userName == null ||
                $this->_config->password == null ||
                $this->_config->licenseId == -1 ||
                $this->_config->deviceId == -1 ||
                $this->_config->siteId == -1)
        ) {
            return true;
        }
        return false;
    }
}
