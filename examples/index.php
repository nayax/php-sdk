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
   'email' => 'webdev@gmail.com',
   'redirectUrl' => 'https://ae3132ca8a3a.ngrok.io/thank-you',
   'notificationUrl' => 'https://ae3132ca8a3a.ngrok.io/server.php',
]);

var_dump($redirectUrl);
