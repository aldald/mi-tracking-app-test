<?php

class WebhookController {

    public static function handleOrder(): void {
        $rawBody = file_get_contents('php://input');
        $hmac    = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';
        $shop    = $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'] ?? '';

        if (!self::verifyHmac($rawBody, $hmac)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid HMAC']);
            return;
        }

        $order = json_decode($rawBody, true);

        $orderId       = (string) ($order['id'] ?? '');
        $orderNumber   = $order['name'] ?? '';
        $amount        = $order['total_price'] ?? 0;
        $currency      = $order['currency'] ?? '';
        $status        = $order['financial_status'] ?? 'pending';
        $createdAt     = date('Y-m-d H:i:s', strtotime($order['created_at'] ?? 'now'));
        $discountCodes = array_map(fn($d) => $d['code'], $order['discount_codes'] ?? []);

        try {
            $pdo  = getDB();
            $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_id = ?");
            $stmt->execute([$orderId]);

            if ($stmt->fetch()) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'already processed']);
                return;
            }

            $stmt = $pdo->prepare("INSERT INTO orders (shop, order_id, order_number, amount, currency, discount_codes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$shop, $orderId, $orderNumber, $amount, $currency, json_encode($discountCodes), $status, $createdAt]);

        } catch (\Exception $e) {
            error_log("[WebhookController] DB Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database error']);
            return;
        }

        $payload = [
            'shop'           => $shop,
            'order_id'       => $orderId,
            'order_number'   => $orderNumber,
            'amount'         => (float) $amount,
            'currency'       => $currency,
            'discount_codes' => $discountCodes,
            'status'         => $status,
            'created_at'     => $createdAt,
        ];

        self::forwardToExternalApi($payload);

        http_response_code(200);
        echo json_encode(['success' => true]);
    }

    private static function verifyHmac(string $body, string $hmac): bool {
        $secret   = $_ENV['SHOPIFY_WEBHOOK_SECRET'];
        $computed = base64_encode(hash_hmac('sha256', $body, $secret, true));
        return hash_equals($computed, $hmac);
    }

    private static function forwardToExternalApi(array $payload): void {
        $url    = $_ENV['EXTERNAL_API_URL'];
        $body   = json_encode($payload);
        $maxTry = 3;

        for ($i = 0; $i < $maxTry; $i++) {
            $response = @file_get_contents($url, false, stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => 'Content-Type: application/json',
                    'content' => $body,
                ],
            ]));

            if ($response !== false) return;

            error_log("[WebhookController] Retry " . ($i + 1) . " failed for order payload");
            sleep(1);
        }

        error_log("[WebhookController] All retries failed for payload: " . $body);
    }
}