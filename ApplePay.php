<?php

namespace Library;

use Exception;
use Models\WalletRecharge;
use Models\WalletRechargeLog;
use Phalcon\DI;

class ApplePay
{
// verifies receipt from iOS in-app purchase

const STATUS_TEST_RECEIPT = 21007;      // Returns this status code when using a test receipt in the production environment

protected $sandbox_url      = '';
protected $buy_url          = '';
protected $product_id       = '';
protected $bundleID         = '';
protected $is_test          = false;

public function  __construct(){
    $config = DI::getDefault()->getShared('config');
    $this->sandbox_url       = $config->applePay->sandbox_url;
    $this->buy_url           = $config->applePay->buy_url;
    $this->bundleID          = $config->applePay->bundleID;

}

/**
 * Verify a receipt and return receipt data
 *
 * @param   string  $receipt    Base-64 encoded data
 * @param   bool    $isSandbox  Optional. True if verifying a test receipt
 * @throws  Exception   If the receipt is invalid or cannot be verified
 * @return  array       Receipt info (including product ID and quantity)
 */
public function getReceiptData($receipt, $isSandbox = false)
{
    // determine which endpoint to use for verifying the receipt
    if ($isSandbox) {
        $endpoint = $this->sandbox_url;
        $this->is_test = true;
    }
    else {
        $endpoint = $this->buy_url;
    }

    // build the post data
    $postData = json_encode(
        array('receipt-data' => $receipt)
    );

    // create the cURL request
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // execute the cURL request and fetch response data
    $response = curl_exec($ch);
    $errno    = curl_errno($ch);
    $errmsg   = curl_error($ch);
    curl_close($ch);

    // ensure the request succeeded
    if ($errno != 0) {
        throw new Exception($errmsg, $errno);
    }

    // parse the response data
    $data = json_decode($response, true);

    // ensure response data was a valid JSON string
    if (!is_array($data)) {
        throw new Exception('Invalid response data');
    }

    // is test receipt
    if ($data['status'] == ApplePay::STATUS_TEST_RECEIPT) {
        $data = $this->getReceiptData($receipt, true);
    }

    // ensure the expected data is present
    if (!isset($data['status']) || $data['status'] != 0) {
        throw new Exception('Invalid receipt');
    }
    return $data;
}


/**
 * Check if transactionID has been used before
 * @param $transactionID
 * @return bool
 */
public static function checkTransactionID($transactionID){
    $walletRechargeLogList = WalletRechargeLog::findFirst([
        'receipt = :receipt:',
        'bind' => ['receipt' => $transactionID]
    ]);
    return $walletRechargeLogList ? false : true;
}

/**
 * Check receipt for valid in-appProductID, appBundleID, and unique transactionID
 *
 * @param   array   $infoArray               Array returned from getReceiptData()
 * @param   integer $wallet_recharge_id      WalletRecharge check
 * @return  bool                             true if all OK, false if not
 */
public function checkInfo($infoArray, $wallet_recharge_id)
{

    if($infoArray['receipt']['bundle_id'] != $this->bundleID){
        return false;
    }

    if((int)$infoArray['status'] !== 0){
        return false;
    }

    if(count($infoArray['receipt']['in_app']) !== 1){
        return false;
    }

    $receipt = $infoArray['receipt']['in_app'][0];

    // Check if it's the same product
    $this->product_id = str_replace($this->bundleID . '.', '', $receipt['product_id']);
    $product_id = WalletRecharge::findFirst($wallet_recharge_id)->toArray()['product_id'];
    if($this->product_id == '' || $product_id != $this->product_id){
        return false;
    }

    if(self::checkTransactionID($receipt['transaction_id']) == false){
        return false;
    }

    return true;
}

public function isSandBox(){
    return $this->is_test;
}

}
