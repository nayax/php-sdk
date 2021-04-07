<?php

class Coriunder extends Payment {
   const LOG_FILE = '/var/log/payments/coriunder_';
   const API_URL = 'https://uiservices.pradexx.com/hosted';

   protected function __construct() {
   }

   public static function i() {
      static $i;
      if (empty($i)) {
         $i = new self();
      }
      return $i;
   }

   public function getForm(stdClass $userData, $site) {
      $response = new Api_Response();

      self::$isTest = empty($userData->is_test) ? 0 : (int)$userData->is_test;
      $this->userData = $userData;
      $this->site = $site;
      $aHidden = array();
      $aHidden['pm_method'] = $userData->pm_id;
      if ($userData->mobile) {
         $aHidden['mobile'] = $userData->mobile;
      }
      $aCCards = array('0' => $this->lng['SELECT_CCARD'], 'visa' => 'Visa', 'mastercard' => 'Mastercard');
      $aMonth = array(0 => $this->lng['FORM_EXMON']);
      for ($i = 1; $i < 13; $i++) {
         $aMonth[$i] = $i;
      }
      $cur_year = (int)gmdate("Y");
      $aYear = array(0 => $this->lng['FORM_EXYEAR']);
      for ($i = $cur_year; $i < $cur_year + 6; $i++) {
         $aYear[$i] = $i;
      }

      $form = '';
      $form .= Form::open(array('action' => '', 'method' => 'post', 'id' => 'deposit_form', 'class' => 'deposit_form'), $aHidden);

      if (isset($this->userData->amount_buttons)) {
         $form .= $this->userData->amount_buttons;
      }

      $classList = 'form-control required';

      $nameOnCardOptions = array('name' => 'nameOnCard', 'class' => $classList);
      $countryOptions = array('class' => $classList, 'name' => 'Country', 'id' => 'countryAlpha2');
      $emailOptions = array('name' => 'Email', 'class' => $classList);
      $addressOptions = array('name' => 'Address', 'class' => $classList);
      $zipCodeOptions = array('name' => 'ZipCode', 'class' => $classList);

      $fillInDetails = $this->lng['FILL_IN_DETAILS'];
      $form .= '<div class="row">';
      $form .= '<div class="col-md-12 center">';
      $form .= '<h4 class="popa-title">' . $fillInDetails . '</h4>';
      $form .= '</div>';
      $form .= '<div class="container section divider p_method_bg">';
      $form .= '<div class="tabHeaderTitle"><h4>' . $this->userData->methodName . ' <span style="font-size:12px;"></span></h4></div>';

      $form .= Form::hidden(array('name' => 'card_type'), $userData->code);

      //Personal data
      $form .= '<div class="row">';
      $form .= Form::colRow(Form::text($nameOnCardOptions, $this->userData->fname . ' ' . $this->userData->lname), $this->lng['FORM_CNAME'], 'nameOnCard');
      $form .= Form::colRow(Form::email($emailOptions, $this->userData->email), 'Email', 'Email');
      $form .= '</div>';

      $form .= '<div class="row">';
      $form .= Form::colRow(Form::text($addressOptions, $this->userData->address), $this->lng['USER_ADDRESS'], 'Address');
      $form .= Form::colRow(Form::text($zipCodeOptions, $this->userData->zip), $this->lng['FORM_ZIP'], 'ZipCode');
      $form .= '</div>';

      $form .= '<div class="row">';
      $form .= Form::colRow(Form::text(array('name' => 'City', 'class' => 'form-control required'), $this->userData->city), $this->lng['FORM_SUBURB'], 'city');
      $form .= Form::colRow(Form::select($this->userData->countries, $countryOptions, isset($this->userData->country_alpha2) ? $this->userData->country_alpha2 : ''), $this->lng['USER_COUNTRY'], 'countryAlpha2');
      $form .= '</div>';

      $form .= '<br>';
      $form .= $this->getFormElement(Form::submit(array('name' => 'btnDeposit', 'class' => 'btnSubmit '), $this->lng['ACC_DEPOSITE_2']));
      $form .= '</div>';

      $form .= Form::close();
      $response->response_data = $form;

      return $response;
   }

   /**
    * @param $transactionDetails
    */
   public function handleCheckoutResult($transactionDetails) {
      $log = 'handle' . PHP_EOL;
      $log .= json_encode($transactionDetails) . PHP_EOL;
      $response = new Api_Response();
      $response->result = true;

      $description = isset($transactionDetails['replyDesc']) ? $transactionDetails['replyDesc'] : $transactionDetails['ReplyDesc'];
      $code = isset($transactionDetails['replyCode']) ? $transactionDetails['replyCode'] : $transactionDetails['Reply'];
      $maskedTransactionId = isset($transactionDetails['trans_refNum']) ? $transactionDetails['trans_refNum'] : $transactionDetails['Order'];

      $originalTransactionId = Payment::extractOriginalTransactionId($maskedTransactionId);
      $sitePrefix = Payment::extractSitePrefix($maskedTransactionId);
      $siteUrl = Payment::extractOriginalUrlByPrefix($sitePrefix);

      $callback = $siteUrl . 'en/ajax/transaction_status.php';

      try {
         $response->is_test = 0;
         $response->method = 'coriunder';
         $response->response_data = $transactionDetails;
         $response->refer_transaction_id = $originalTransactionId;
         $response->pm_transaction_id = $originalTransactionId;

         $updateTransaction = $response;
         $updateTransaction->action = 'update_transaction';

         if ($code === '000' || $code === '000.000.000') {
            $updateTransaction->status = 'ok';
            $updateTransaction->message = 'transaction was successful';
            $siteResponse = $this->sendToSite($callback, (array)$updateTransaction);//update trans. data
            $log .= 'Update tr.:' . json_encode($siteResponse) . PHP_EOL;
         } else if ($this->codeInDeclinedStatus($code) || $this->codeInRiskStatus($code)) {
            $updateTransaction->status = 'canceled';
            $updateTransaction->message = 'transaction was canceled: ' . $description;
            $siteResponse = $this->sendToSite($callback, (array)$updateTransaction);//update trans. data
            $log .= 'Update tr.:' . json_encode($siteResponse) . PHP_EOL;
         }
      } catch (Exception $ex) {
         $response->result = false;
         echo "<br/><strong>Message: </strong>" . $ex->getMessage();
         echo "<br/><strong>Trace: </strong>" . $ex->getTraceAsString();
      }
      @file_put_contents(self::LOG_FILE . gmdate('Y-m') . '.log', gmdate('Y-m-d H:i:s') . ':' . PHP_EOL . $log . PHP_EOL, FILE_APPEND);
      echo 'OK';
      die();
   }

   /**
    * @param stdClass $transaction
    * @param string $site
    * @return mixed
    */
   public function deposit(stdClass $transaction, $site) {
      $log = 'deposit coriunder:' . json_encode($transaction) . PHP_EOL . $site;
      $this->site = $transaction->short_name;
      $this->lng = 'en';
      $this->data = $transaction;
      self::$isTest = $transaction->is_test;
      $this->transactionData = new \defines\Transaction();
      $response = new Api_Response();

      $transactionInfo = explode("_", $transaction->ref_id);
      $transactionId = $transactionInfo[0];
      $currency = $transaction->currency;
      $amount = $transaction->amount;

      $sitePrefix = Payment::getSitePrefix($site);
      $maskedTransactionId = $sitePrefix . $transactionId;

      $code = getRedirectCode($transaction->short_name);
      $url = Payment::getPaymentCenterUrl() . 'pending&br=' . $code;

      $method = $transaction->card_type;
      $methodCode = $method === 'sofort' ? 'SFOR' : 'CC';

      $transactionDetails = [
         'merchantID' => $this->getMerchantCredentials($sitePrefix, 'merchantId'),
         'trans_amount' => $amount,
         'trans_currency' => strtoupper($currency),
         'trans_type' => 0, // debit transaction
         'trans_installments' => 1,
         'trans_refNum' => $maskedTransactionId,
         'disp_paymentType' => 'CC',
         'disp_paymentType' => $methodCode,
         'client_fullName' => $transaction->nameOnCard,
         'client_email' => $transaction->Email,
         'client_billAddress1' => $transaction->Address,
         'client_billZipcode' => $transaction->ZipCode,
         'client_billCountry' => $transaction->Country,
         'client_billCity' => $transaction->City,
         'url_redirect' => $url,
         'url_notify' => Payment::getPaymentCenterUrl() . 'transaction/coriunder/' . $maskedTransactionId,
      ];

      $transactionDetails['signature'] = $this->createSignature($transactionDetails, $sitePrefix);
      $redirectUrl = $this->getRedirectUrl($transactionDetails, $sitePrefix);

      $response->result = -1;
      $response->response_data = $redirectUrl;
      $response->errors = [];
      $response->pm_transaction_id = $transactionId;

      $log .= ' transactionDetails: ' . print_r($transactionDetails, true) . PHP_EOL;
      $log .= ' redirectUrl: ' . $redirectUrl . PHP_EOL;

      @file_put_contents(self::LOG_FILE . gmdate('Y-m') . '.log', gmdate('Y-m-d H:i:s') . ':' . PHP_EOL . $log . PHP_EOL, FILE_APPEND);
      return $response;
   }

   /**
    * @param $transaction
    * @param $sitePrefix
    * @return string
    */
   private function createSignature($transaction, $sitePrefix) {
      $concatenatedString =
         $transaction['merchantID'] .
         $transaction['trans_refNum'] .
         $transaction['trans_installments'] .
         $transaction['trans_amount'] .
         $transaction['trans_currency'] .
         $transaction['trans_type'] .
         $transaction['disp_paymentType'] .
         $transaction['client_fullName'] .
         $transaction['client_email'] .
         $transaction['client_billAddress1'] .
         $transaction['client_billZipcode'] .
         $transaction['client_billCountry'] .
         $transaction['client_billCity'] .
         $transaction['url_notify'] .
         $transaction['url_redirect'] . $this->getMerchantCredentials($sitePrefix, 'hashCode');

      $signature =
         urlencode(
            base64_encode(
               hash("sha256", $concatenatedString, true)
            )
         );

      return $signature;
   }

   private function getRedirectUrl($transaction, $sitePrefix) {
      $apiUrl = self::API_URL;

      $redirectUrl = $apiUrl;
      $redirectUrl .= '?merchantID=' . $this->getMerchantCredentials($sitePrefix, 'merchantId');
      $redirectUrl .= '&trans_refNum=' . $transaction['trans_refNum'];
      $redirectUrl .= '&trans_installments=' . $transaction['trans_installments'];
      $redirectUrl .= '&trans_amount=' . $transaction['trans_amount'];
      $redirectUrl .= '&trans_currency=' . $transaction['trans_currency'];
      $redirectUrl .= '&trans_type=' . $transaction['trans_type'];
      $redirectUrl .= '&disp_paymentType=' . $transaction['disp_paymentType'];
      $redirectUrl .= '&client_fullName=' . urlencode($transaction['client_fullName']);
      $redirectUrl .= '&client_email=' . $transaction['client_email'];
      $redirectUrl .= '&client_billAddress1=' . urlencode($transaction['client_billAddress1']);
      $redirectUrl .= '&client_billZipcode=' . urlencode($transaction['client_billZipcode']);
      $redirectUrl .= '&client_billCountry=' . urlencode($transaction['client_billCountry']);
      $redirectUrl .= '&client_billCity=' . urlencode($transaction['client_billCity']);
      $redirectUrl .= '&url_notify=' . urlencode($transaction['url_notify']);
      $redirectUrl .= '&url_redirect=' . urlencode($transaction['url_redirect']);
      $redirectUrl .= '&signature=' . $transaction['signature'];

      return $redirectUrl;
   }

   private function getMerchantCredentials($sitePrefix, $data) {
      $credentials = [
         '1111' => [
            'merchantId' => '8354130',
            'hashCode' => 'QW90VKZWVE'
         ],
         '1113' => [
            'merchantId' => '9425234',
            'hashCode' => 'L7E27PH32N'
         ],
         '1114' => [
            'merchantId' => '1642101',
            'hashCode' => 'WS7TTBL2G2'
         ],
         '1115' => [
            'merchantId' => '2818248',
            'hashCode' => 'BLSG258B0P'
         ],
         '1116' => [
            'merchantId' => '8699443',
            'hashCode' => 'Y7Y2MLJEMU'
         ],
         '1117' => [
            'merchantId' => '2291847',
            'hashCode' => 'E9UTN3ISN3'
         ],
         '1120' => [
            'merchantId' => '7814007',
            'hashCode' => '74DSLYX9EF'
         ],
         '1121' => [
            'merchantId' => '9279575',
            'hashCode' => 'C2B2C8ARBU'
         ],
         '1123' => [
            'merchantId' => '9709033',
            'hashCode' => 'XQVZ2TT32A'
         ],
         '1124' => [
            'merchantId' => '6733492',
            'hashCode' => 'ZVJ2FJZPX4'
         ],
         '1122' => [
            'merchantId' => '7346273',
            'hashCode' => 'NVZKGIOBZE'
         ]
      ];

      return $credentials[$sitePrefix][$data];
   }

   private function codeInDeclinedStatus($code) {
      $errorRegularExpressions = [
         '/^(000\.400\.[1][0-9][1-9]|000\.400\.2)/',
         '/^(800\.[17]00|800\.800\.[123])/',
         '/^(900\.[1234]00|000\.400\.030)/',
         '/^(800\.[56]|999\.|600\.1|800\.800\.8)/',
         '/^(100\.39[765])/',
         '/^(100\.400|100\.38|100\.370\.100|100\.370\.11)/',
         '/^(800\.400\.1)/',
         '/^(800\.400\.2|100\.380\.4|100\.390)/',
         '/^(100\.100\.701|800\.[32])/',
         '/^(800\.1[123456]0)/',
         '/^(600\.[23]|500\.[12]|800\.121)/',
         '/^(100\.[13]50)/',
         '/^(100\.250|100\.360)/',
         '/^(700\.[1345][05]0)/',
         '/^(200\.[123]|100\.[53][07]|800\.900|100\.[69]00\.500)/',
         '/^(100\.800)/',
         '/^(100\.[97]00)/',
         '/^(100\.100|100.2[01])/',
         '/^(100\.55)/',
         '/^(100\.380\.[23]|100\.380\.101)/',
      ];

      foreach ($errorRegularExpressions as $errorRegex) {
         if (preg_match($errorRegex, $code) == true) {
            return true;
         }
      }

      return false;
   }

   private function codeInRiskStatus($code) {
      $riskStatuses = [
         585
      ];

      foreach ($riskStatuses as $status) {
         if(in_array($code, $status)) {
            return true;
         }
      }

      return false;
   }
}
