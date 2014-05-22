<?php

class HpsCheckSale extends HpsTransaction{
    public  $authorizationCode  = null,
            $customerId         = null,
            $messageType        = null,
            $code               = null,
            $message            = null,
            $fieldNumber        = null,
            $fieldName          = null;

    public static function fromDict($rsp,$txnType){
        $response = $rsp->Transaction;

        $sale = parent::fromDict($rsp,$txnType);
        $sale->responseCode = (isset($response->RspCode) ? $response->RspCode : null);
        $sale->responseText = (isset($response->RspText) ? $response->RspText : null);
        $sale->authorizationCode = (isset($response->AuthCode) ? $response->AuthCode : null);

        if($response->CheckRspInfo){
            $checkInfo = $response->CheckRspInfo;
            $sale->messageType = (isset($checkInfo->Type) ? $checkInfo->Type : null);
            $sale->code = (isset($checkInfo->Code) ? $checkInfo->Code : null);
            $sale->message = (isset($checkInfo->Message) ? $checkInfo->Message : null);
            $sale->fieldNumber = (isset($checkInfo->FieldNumber) ? $checkInfo->FieldNumber : null);
            $sale->fieldName = (isset($checkInfo->FieldName) ? $checkInfo->FieldName : null);
        }

        return $sale;
    }
} 