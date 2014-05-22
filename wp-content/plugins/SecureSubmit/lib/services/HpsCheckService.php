<?php

class HpsCheckService extends HpsService {

    /**
     * A Sale transaction is used to process transactions using bank account information as the payment method.
     * The transaction service can be used to perform a Sale or Return transaction by indicating the Check Action.
     *
     * <b>NOTE:</b> The Portico Gateway supports both GETI and HPS Colonnade for processing check transactions. While
     * the available services are the same regardless of the check processor, the services may have different behaviors.
     * For example, GETI-processed Check Sale transactions support the ability to override a Check Sale transaction
     * already presented as well as the ability to verify a check.
     * @param string $action Type of Check Action (Sale, Return, Override)
     * @param string $check The Check information.
     * @param string $amount The amount of the sale.
     *
     * @returns HpsCheckSale
     */
    public function sale($action, HpsCheck $check, $amount){
        $amount = Validation::checkAmount($amount);

        $xml = new DOMDocument();
        $hpsTransaction = $xml->createElement('hps:Transaction');
            $hpsCheckSale = $xml->createElement('hps:CheckSale');
                $hpsBlock1 = $xml->createElement('hps:Block1');
                $hpsBlock1->appendChild($xml->createElement('hps:Amt',$amount));
                $hpsBlock1->appendChild($this->_hydrateCheckData($check,$xml));
                $hpsBlock1->appendChild($xml->createElement('hps:CheckAction',$action));
                $hpsBlock1->appendChild($xml->createElement('hps:SEECode',$check->secCode));
                if($check->checkType != null){
                    $hpsBlock1->appendChild($xml->createElement('hps:CheckType',$check->checkType));
                }
                if($check->dataEntryMode != null){
                    $hpsBlock1->appendChild($xml->createElement('hps:DataEntryMode',$check->dataEntryMode));
                }
                if($check->checkHolder != null){
                    $hpsBlock1->appendChild($this->_hydrateConsumerInfo($check,$xml));
                }
            $hpsCheckSale->appendChild($hpsBlock1);
        $hpsTransaction->appendChild($hpsCheckSale);
        $response = $this->doTransaction($hpsTransaction);
        Validation::checkTransactionResponse($response,'CheckSale');
        return HpsCheckSale::fromDict($response,'CheckSale');
    }

    private function _hydrateCheckData(HpsCheck $check,DOMDocument $xml){
        $checkData = $xml->createElement('hps:AmountInfo');
        $checkData->appendChild($xml->createElement('hps:AccountNumber',$check->accountNumber));
        $checkData->appendChild($xml->createElement('hps:CheckNumber',$check->checkNumber));
        $checkData->appendChild($xml->createElement('hps:MICRData',$check->micrNumber));
        $checkData->appendChild($xml->createElement('hps:RoutingNumber',$check->routingNumber));

        if ($check->accountType != null) {
            $checkData->appendChild($xml->createElement('hps:AccountType',$check->accountType));
        }

        return $checkData;
    }

    private function _hydrateConsumerInfo(HpsCheck $check, DOMDocument $xml){
        $consumerInfo = $xml->createElement('hps:ConsumerInfo');
        if($check->checkHolder->address != null){
            $consumerInfo->appendChild('hps:Address1',$check->checkHolder->address->address);
            $consumerInfo->appendChild('hps:City',$check->checkHolder->address->city);
            $consumerInfo->appendChild('hps:State',$check->checkHolder->address->state);
            $consumerInfo->appendChild('hps:Zip',$check->checkHolder->address->zip);
        }

        $consumerInfo->appendChild('hps:CheckName',$check->checkHolder->checkName);
        $consumerInfo->appendChild('hps:CourtesyCard',$check->checkHolder->courtesyCard);
        $consumerInfo->appendChild('hps:DLNumber',$check->checkHolder->dlNumber);
        $consumerInfo->appendChild('hps:DlState',$check->checkHolder->dlState);
        $consumerInfo->appendChild('hps:EmailAddress',$check->checkHolder->email);
        $consumerInfo->appendChild('hps:FirstName',$check->checkHolder->firstName);
        $consumerInfo->appendChild('hps:LastName',$check->checkHolder->lastName);
        $consumerInfo->appendChild('hps:PhoneNumber',$check->checkHolder->phone);

        return $consumerInfo;
    }
}