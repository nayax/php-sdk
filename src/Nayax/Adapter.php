<?php

namespace Nayax;

class Adapter {

   const API_URL = 'https://uiservices.ecom.nayax.com/hosted/';

   const MERCHANT_ID = '{{ merchantId}}';
   const HASH_CODE = '{{ your hash code}}';

   const STATUS_SUCCESS = 'success';
   const STATUS_ERROR = 'error';

   public function initiatePayment($transaction) {
      $transactionDetails = [
         'merchantID' => self::MERCHANT_ID,
         'trans_amount' => $transaction['amount'],
         'trans_currency' => $transaction['currency'],
         'trans_type' => 0, // debit transaction
         'trans_installments' => 1,
         'trans_refNum' => $transaction['orderId'],
         'disp_paymentType' => 'CC',
         'url_redirect' => $transaction['redirectUrl'],
         'url_notify' => $transaction['notificationUrl'],
      ];

      $transactionDetails['signature'] = $this->createSignature($transactionDetails);
      return $this->getRedirectUrl($transactionDetails);
   }

   private function createSignature($transaction) {
      $concatenatedString =
         $transaction['merchantID'] .
         $transaction['trans_refNum'] .
         $transaction['trans_installments'] .
         $transaction['trans_amount'] .
         $transaction['trans_currency'] .
         $transaction['trans_type'] .
         $transaction['disp_paymentType'] .
         $transaction['url_notify'] .
         $transaction['url_redirect'] . self::HASH_CODE;

      return urlencode(base64_encode(hash("sha256", $concatenatedString, true)));
   }

   private function getRedirectUrl($transaction) {
      $apiUrl = self::API_URL;

      $redirectUrl = $apiUrl;
      $redirectUrl .= '?merchantID=' . self::MERCHANT_ID;
      $redirectUrl .= '&trans_refNum=' . $transaction['trans_refNum'];
      $redirectUrl .= '&trans_installments=' . $transaction['trans_installments'];
      $redirectUrl .= '&trans_amount=' . $transaction['trans_amount'];
      $redirectUrl .= '&trans_currency=' . $transaction['trans_currency'];
      $redirectUrl .= '&trans_type=' . $transaction['trans_type'];
      $redirectUrl .= '&disp_paymentType=' . $transaction['disp_paymentType'];
//      $redirectUrl .= '&client_fullName=' . urlencode($transaction['client_fullName']);
//      $redirectUrl .= '&client_email=' . $transaction['client_email'];
//      $redirectUrl .= '&client_billAddress1=' . urlencode($transaction['client_billAddress1']);
//      $redirectUrl .= '&client_billZipcode=' . urlencode($transaction['client_billZipcode']);
//      $redirectUrl .= '&client_billCountry=' . urlencode($transaction['client_billCountry']);
//      $redirectUrl .= '&client_billCity=' . urlencode($transaction['client_billCity']);
      $redirectUrl .= '&url_notify=' . urlencode($transaction['url_notify']);
      $redirectUrl .= '&url_redirect=' . urlencode($transaction['url_redirect']);
      $redirectUrl .= '&signature=' . $transaction['signature'];

      return $redirectUrl;
   }

   public function handleNotification($notification) {
      $description = isset($notification['replyDesc']) ? $notification['replyDesc'] : $notification['ReplyDesc']; // legacy api
      $code = isset($notification['replyCode']) ? $notification['replyCode'] : $notification['Reply']; // legacy api
      $originalTransactionId = isset($notification['trans_refNum']) ? $notification['trans_refNum'] : $notification['Order']; // legacy api
      $innerTransactionId = $notification['trans_id'];
      $transactionAmount = $notification['trans_amount'];
      $transactionCurrency = $notification['trans_currency'];

      $notificationDetails = [
         'description' => $description,
         'originalOrderId' => $originalTransactionId,
         'internalTransactionId' => $innerTransactionId,
         'amount' => $transactionAmount,
         'currency' => $transactionCurrency,
         'notification' => $notification
      ];

      if ($code === '000' || $code === '000.000.000') {
         $notificationDetails['status'] = self::STATUS_SUCCESS;
      } else {
         $notificationDetails['status'] = self::STATUS_ERROR;
      }

      return $notificationDetails;
   }
}
