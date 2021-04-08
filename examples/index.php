<?php

use Nayax\Adapter;

require __DIR__ . '/../vendor/autoload.php';

$merchantId = '';
$hashCode = '';

$adapter = new Adapter($merchantId, $hashCode);

// to get redirect url $redirectUrl
$redirectUrl = $adapter->initiatePayment([
   'amount' => 1.23,
   'currency' => 'ACP',
   'orderId' => uniqid(),
   'methodCode' => 'visa',
   'redirectUrl' => '{{ your redirect page}}',
   'notificationUrl' => '{{ your notification page}}',
]);

// to handle notification
// choose either one
$transaction = $adapter->handleNotification($_GET);
$transaction = $adapter->handleNotification($_POST);
