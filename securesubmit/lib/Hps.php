<?php
if ( ! defined('DS')) define('DS', DIRECTORY_SEPARATOR);
if ( ! defined('PS')) define('PS', PATH_SEPARATOR);

// Infrastructure
if (!class_exists('HpsConfiguration')) require_once(dirname(__FILE__).DS.'infrastructure/HpsConfiguration.php');
if (!class_exists('HpsException')) require_once(dirname(__FILE__).DS.'infrastructure/HpsException.php');
if (!class_exists('ApiConnectionException')) require_once(dirname(__FILE__).DS.'infrastructure/ApiConnectionException.php');
if (!class_exists('AuthenticationException')) require_once(dirname(__FILE__).DS.'infrastructure/AuthenticationException.php');
if (!class_exists('CardException')) require_once(dirname(__FILE__).DS.'infrastructure/CardException.php');
if (!class_exists('HpsExceptionMapper')) require_once(dirname(__FILE__).DS.'infrastructure/HpsExceptionMapper.php');
if (!class_exists('HpsSdkCodes')) require_once(dirname(__FILE__).DS.'infrastructure/HpsSdkCodes.php');
if (!class_exists('InvalidRequestException')) require_once(dirname(__FILE__).DS.'infrastructure/InvalidRequestException.php');
if (!class_exists('Validation')) require_once(dirname(__FILE__).DS.'infrastructure/Validation.php');
if (!class_exists('HpsCheckException')) require_once(dirname(__FILE__).DS.'infrastructure/HpsCheckException.php');

// Entities
if (!class_exists('HpsTransaction')) require_once(dirname(__FILE__).DS.'entities/HpsTransaction.php');
if (!class_exists('HpsAuthorization')) require_once(dirname(__FILE__).DS.'entities/HpsAuthorization.php');
if (!class_exists('HpsAccountVerify')) require_once(dirname(__FILE__).DS.'entities/HpsAccountVerify.php');
if (!class_exists('HpsAddress')) require_once(dirname(__FILE__).DS.'entities/HpsAddress.php');
if (!class_exists('HpsTransactionType')) require_once(dirname(__FILE__).DS.'entities/HpsTransactionType.php');
if (!class_exists('HpsBatch')) require_once(dirname(__FILE__).DS.'entities/HpsBatch.php');
if (!class_exists('HpsConsumer')) require_once(dirname(__FILE__).DS.'entities/HpsConsumer.php');
if (!class_exists('HpsCardHolder')) require_once(dirname(__FILE__).DS.'entities/HpsCardHolder.php');
if (!class_exists('HpsCharge')) require_once(dirname(__FILE__).DS.'entities/HpsCharge.php');
if (!class_exists('HpsChargeExceptions')) require_once(dirname(__FILE__).DS.'entities/HpsChargeExceptions.php');
if (!class_exists('HpsCreditCard')) require_once(dirname(__FILE__).DS.'entities/HpsCreditCard.php');
if (!class_exists('HpsItemChoiceTypePosResponseVer10Transaction')) require_once(dirname(__FILE__).DS.'entities/HpsItemChoiceTypePosResponseVer10Transaction.php');
if (!class_exists('HpsRefund')) require_once(dirname(__FILE__).DS.'entities/HpsRefund.php');
if (!class_exists('HpsReportTransactionDetails')) require_once(dirname(__FILE__).DS.'entities/HpsReportTransactionDetails.php');
if (!class_exists('HpsReportTransactionSummary')) require_once(dirname(__FILE__).DS.'entities/HpsReportTransactionSummary.php');
if (!class_exists('HpsReversal')) require_once(dirname(__FILE__).DS.'entities/HpsReversal.php');
if (!class_exists('HpsTokenData')) require_once(dirname(__FILE__).DS.'entities/HpsTokenData.php');
if (!class_exists('HpsTransactionDetails')) require_once(dirname(__FILE__).DS.'entities/HpsTransactionDetails.php');
if (!class_exists('HpsTransactionHeader')) require_once(dirname(__FILE__).DS.'entities/HpsTransactionHeader.php');
if (!class_exists('HpsVoid')) require_once(dirname(__FILE__).DS.'entities/HpsVoid.php');
if (!class_exists('HpsCheck')) require_once(dirname(__FILE__).DS.'entities/Check/HpsCheck.php');
if (!class_exists('HpsCheckHolder')) require_once(dirname(__FILE__).DS.'entities/Check/HpsCheckHolder.php');
if (!class_exists('HpsCheckResponse')) require_once(dirname(__FILE__).DS.'entities/Check/HpsCheckResponse.php');
if (!class_exists('HpsCheckResponseDetails')) require_once(dirname(__FILE__).DS.'entities/Check/HpsCheckResponseDetails.php');


// Services
if (!class_exists('HpsTokenService')) require_once(dirname(__FILE__).DS.'services/HpsTokenService.php');
if (!class_exists('HpsService')) require_once(dirname(__FILE__).DS.'services/HpsService.php');
if (!class_exists('HpsCreditService')) require_once(dirname(__FILE__).DS.'services/HpsCreditService.php');
if (!class_exists('HpsChargeService')) require_once(dirname(__FILE__).DS.'services/HpsChargeService.php');
if (!class_exists('HpsBatchService')) require_once(dirname(__FILE__).DS.'services/HpsBatchService.php');
if (!class_exists('HpsCheckService')) require_once(dirname(__FILE__).DS.'services/HpsCheckService.php');
