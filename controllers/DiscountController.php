<?php

class DiscountController
{

    public static function create(): void
    {
        $body = json_decode(file_get_contents('php://input'), true);

        $code     = $body['code']      ?? '';
        $type     = $body['type']      ?? '';
        $value    = $body['value']     ?? 0;
        $startsAt = $body['starts_at'] ?? '';

        if (empty($code) || empty($type) || empty($value)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            return;
        }

        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id FROM discount_codes WHERE code = ?");
        $stmt->execute([$code]);

        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Discount code already exists']);
            return;
        }

        $shop = $pdo->query("SELECT shop_domain, access_token FROM shops LIMIT 1")->fetch();

        if (!$shop) {
            http_response_code(500);
            echo json_encode(['error' => 'No shop connected']);
            return;
        }

        $shopifyId = self::createInShopify($shop['shop_domain'], $shop['access_token'], $code, $type, $value, $startsAt);

        if (!$shopifyId) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create discount in Shopify']);
            return;
        }

        $stmt = $pdo->prepare("INSERT INTO discount_codes (code, shopify_id) VALUES (?, ?)");
        $stmt->execute([$code, $shopifyId]);

        http_response_code(201);
        echo json_encode(['success' => true, 'code' => $code, 'shopify_id' => $shopifyId]);
    }

    private static function createInShopify(string $shop, string $token, string $code, string $type, float $value, string $startsAt): ?string
    {
        $valueType = $type === 'percentage' ? 'percentage' : 'fixed_amount';

        $payload = [
            'price_rule' => [
                'title'              => $code,
                'target_type'        => 'line_item',
                'target_selection'   => 'all',
                'allocation_method'  => 'across',
                'value_type'         => $valueType,
                'value'              => -$value,
                'customer_selection' => 'all',
                'starts_at'          => $startsAt ?: date('c'),
            ],
        ];

        $ch = curl_init("https://{$shop}/admin/api/2026-04/price_rules.json");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "X-Shopify-Access-Token: {$token}",
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        file_put_contents(
            'C:/laragon/www/smi-tracking-app/debug.log',
            date('Y-m-d H:i:s') . " - PRICE RULE ($httpCode): " . $response . "\n",
            FILE_APPEND
        );

        $priceRule   = json_decode($response, true);
        $priceRuleId = $priceRule['price_rule']['id'] ?? null;

        if (!$priceRuleId) return null;

        $ch = curl_init("https://{$shop}/admin/api/2026-04/price_rules/{$priceRuleId}/discount_codes.json");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['discount_code' => ['code' => $code]]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "X-Shopify-Access-Token: {$token}",
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        file_put_contents(
            'C:/laragon/www/smi-tracking-app/debug.log',
            date('Y-m-d H:i:s') . " - DISCOUNT CODE ($httpCode): " . $response . "\n",
            FILE_APPEND
        );

        $discount = json_decode($response, true);
        return (string) ($discount['discount_code']['id'] ?? null);
    }
}
