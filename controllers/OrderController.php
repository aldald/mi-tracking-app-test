<?php

class OrderController {

    public static function updateStatus(): void {
        $body = json_decode(file_get_contents('php://input'), true);

        $orderId = $body['order_id'] ?? '';
        $status  = $body['status']   ?? '';

        if (empty($orderId) || empty($status)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing order_id or status']);
            return;
        }

        if (!in_array($status, ['accepted', 'rejected'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid status']);
            return;
        }

        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_id = ?");
        $stmt->execute([$orderId]);

        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Order not found']);
            return;
        }

        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $stmt->execute([$status, $orderId]);

        http_response_code(200);
        echo json_encode(['success' => true, 'order_id' => $orderId, 'status' => $status]);
    }
}