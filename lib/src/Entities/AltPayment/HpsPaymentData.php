<?php
/**
 * Order Data
 *
 * PHP Version 5.2+
 *
 * @category PHP
 * @package  HPS
 * @author   Heartland Payment Systems <EntApp_DevPortal@e-hps.com>
 * @license  https://github.com/hps/heartland-php/blob/master/LICENSE.txt Custom
 * @link     https://github.com/hps/heartland-php
 */

/**
 * Order Data
 *
 * @category PHP
 * @package  HPS
 * @author   Heartland Payment Systems <EntApp_DevPortal@e-hps.com>
 * @license  https://github.com/hps/heartland-php/blob/master/LICENSE.txt Custom
 * @link     https://github.com/hps/heartland-php
 */
class HpsPaymentData
{
    /** @var double|null */
    public $subtotal       = null;

    /** @var double|null */
    public $shippingAmount = null;

    /** @var double|null */
    public $taxAmount      = null;

    /** @var string|null */
    public $paymentType    = null;

    /** @var string|null */
    public $invoiceNumber  = null;
}
