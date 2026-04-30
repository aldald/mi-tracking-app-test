<?php

require_once BASE_PATH . '/controllers/AuthController.php';
require_once BASE_PATH . '/controllers/WebhookController.php';
require_once BASE_PATH . '/controllers/OrderController.php';
require_once BASE_PATH . '/controllers/DiscountController.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/smi-tracking-app', '', $uri);
$method = $_SERVER['REQUEST_METHOD'];

match(true) {
    $uri === '/auth/install' && $method === 'GET'     => AuthController::install(),
    $uri === '/auth/callback' && $method === 'GET'    => AuthController::callback(),
    $uri === '/webhooks/orders' && $method === 'POST' => WebhookController::handleOrder(),
    $uri === '/orders/status' && $method === 'POST'   => OrderController::updateStatus(),
    $uri === '/discount-codes' && $method === 'POST'  => DiscountController::create(),
    default => (function() {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    })()
};