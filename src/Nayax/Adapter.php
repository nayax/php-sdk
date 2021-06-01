<?php

namespace Nayax;

class Adapter {

   const API_URL = 'https://uiservices.ecom.nayax.com/hosted/';

   const ONE_TIME_PAYMENT = 'one_time';
   const RECURRING_PAYMENT = 'recurring';

   const STATUS_SUCCESS = 'success';
   const STATUS_ERROR = 'error';

   private $merchantId;
   private $hashCode;

   public function __construct($merchantId, $hashCode) {
      $this->merchantId = $merchantId;
      $this->hashCode = $hashCode;
   }

   public function initiatePayment($transaction) {
      $paymentType = $transaction['paymentType'];

      $transactionDetails = [
         'merchantID' => $this->merchantId,
         'trans_amount' => $transaction['amount'],
         'trans_currency' => $transaction['currency'],
         'trans_type' => 0,
         'trans_installments' => $transaction['installments'],
         'trans_refNum' => $transaction['orderId'],
         'disp_paymentType' => 'CC',
         'url_redirect' => $transaction['redirectUrl'],
         'notification_url' => $transaction['notificationUrl'],
         'client_fullName' => $transaction['name'],
         'disp_payFor' => $transaction['description'],
         'trans_comment' => $transaction['description'],
      ];

      if ($paymentType === self::RECURRING_PAYMENT) {
         $transactionDetails['disp_recurring'] = 1;
         $transactionDetails['trans_recurringType'] = 0;
         $transactionDetails['trans_recurring1'] = '99M1';
         $transactionDetails['trans_installments'] = 1;
      }

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
         $transaction['notification_url'] .
         $transaction['url_redirect'] .
         $transaction['client_fullName'] .
         $transaction['disp_payFor'] .
         $transaction['trans_comment'] .
         (isset($transaction['disp_recurring']) ? $transaction['disp_recurring'] : '') .
         (isset($transaction['trans_recurringType']) ? $transaction['trans_recurringType'] : '') .
         (isset($transaction['trans_recurring1']) ? $transaction['trans_recurring1'] : '') .
         $this->hashCode;

      return urlencode(base64_encode(hash("sha256", $concatenatedString, true)));
   }

   private function getRedirectUrl($transaction) {
      $apiUrl = self::API_URL;

      $redirectUrl = $apiUrl;
      $redirectUrl .= '?merchantID=' . $this->merchantId;
      $redirectUrl .= '&trans_refNum=' . $transaction['trans_refNum'];
      $redirectUrl .= '&trans_installments=' . $transaction['trans_installments'];
      $redirectUrl .= '&trans_amount=' . $transaction['trans_amount'];
      $redirectUrl .= '&trans_currency=' . $transaction['trans_currency'];
      $redirectUrl .= '&trans_type=' . $transaction['trans_type'];
      $redirectUrl .= '&disp_paymentType=' . $transaction['disp_paymentType'];
      $redirectUrl .= '&notification_url=' . urlencode($transaction['notification_url']);
      $redirectUrl .= '&url_redirect=' . urlencode($transaction['url_redirect']);
      $redirectUrl .= '&client_fullName=' . $transaction['client_fullName'];
      $redirectUrl .= '&disp_payFor=' . $transaction['disp_payFor'];
      $redirectUrl .= '&trans_comment=' . $transaction['trans_comment'];

      if (isset($transaction['trans_recurringType'])) {
         $redirectUrl .= '&disp_recurring=' . $transaction['disp_recurring'];
         $redirectUrl .= '&trans_recurringType=' . $transaction['trans_recurringType'];
         $redirectUrl .= '&trans_recurring1=' . $transaction['trans_recurring1'];

      }

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
