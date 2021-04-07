<?php

use Nayax\Adapter;

require __DIR__ . '/../vendor/autoload.php';

$adapter = new Adapter();

$redirectUrl = $adapter->initiatePayment([
   'amount' => 1.23,
   'currency' => 'ACP',
   'orderId' => uniqid(),
   'methodCode' => 'visa',
   'fullName' => 'webdev',
   'redirectUrl' => '{{ your redirect page}}',
   'notificationUrl' => '{{ your notification page}}',
]);

var_dump($redirectUrl);
