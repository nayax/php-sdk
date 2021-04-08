<?php

use Nayax\Adapter;

require __DIR__ . '/../vendor/autoload.php';

$merchantId = '{{ your merchant id }}';
$hashCode = '{{ your hash code }}';

$adapter = new Adapter($merchantId, $hashCode);

// to get redirect url $redirectUrl
$redirectUrl = $adapter->initiatePayment([
   'amount' => 1.23,
   'currency' => 'ACP', // for list of currencies view the website documentation
   'orderId' => uniqid(), // your system order id
   'methodCode' => 'visa', // what payment method to use
   'redirectUrl' => '{{ your redirect page}}',
   'notificationUrl' => '{{ your notification page}}',
]);

// to handle notification
// choose either one
$transaction = $adapter->handleNotification($_GET);
$transaction = $adapter->handleNotification($_POST);
