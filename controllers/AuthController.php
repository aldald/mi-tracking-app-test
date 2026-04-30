<?php

class AuthController {

    public static function install(): void {
        $shop = $_GET['shop'] ?? '';

        if (empty($shop)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing shop parameter']);
            return;
        }

        $apiKey      = $_ENV['SHOPIFY_API_KEY'];
        $scopes      = $_ENV['SHOPIFY_SCOPES'];
        $redirectUri = $_ENV['SHOPIFY_REDIRECT_URI'];
        $nonce       = bin2hex(random_bytes(16));

        setcookie('shopify_nonce', $nonce, time() + 300, '/');

        $url = "https://{$shop}/admin/oauth/authorize"
             . "?client_id={$apiKey}"
             . "&scope={$scopes}"
             . "&redirect_uri={$redirectUri}"
             . "&state={$nonce}";

        header("Location: {$url}");
        exit;
    }

    public static function callback(): void {
        $shop  = $_GET['shop']  ?? '';
        $code  = $_GET['code']  ?? '';
        $state = $_GET['state'] ?? '';
        $nonce = $_COOKIE['shopify_nonce'] ?? '';

        if (empty($shop) || empty($code) || $state !== $nonce) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request']);
            return;
        }

        $apiKey    = $_ENV['SHOPIFY_API_KEY'];
        $apiSecret = $_ENV['SHOPIFY_API_SECRET'];

        $response = file_get_contents("https://{$shop}/admin/oauth/access_token", false, stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-Type: application/json',
                'content' => json_encode([
                    'client_id'     => $apiKey,
                    'client_secret' => $apiSecret,
                    'code'          => $code,
                ]),
            ],
        ]));

        $data        = json_decode($response, true);
        $accessToken = $data['access_token'] ?? '';

        if (empty($accessToken)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to get access token']);
            return;
        }

        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO shops (shop_domain, access_token) VALUES (?, ?) ON DUPLICATE KEY UPDATE access_token = VALUES(access_token)");
        $stmt->execute([$shop, $accessToken]);

        echo json_encode(['success' => true, 'shop' => $shop]);
    }
}