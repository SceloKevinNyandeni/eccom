<?php
// file: get_stage.php
include 'db.php';

header('Content-Type: application/json');

try {
    // Get order ID from the query string
    $order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

    if ($order_id <= 0) {
        echo json_encode(['error' => 'Invalid order ID']);
        exit;
    }

    // Fetch current status from orders table
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $status = $stmt->fetchColumn();

    if (!$status) {
        echo json_encode(['error' => 'Order not found']);
        exit;
    }

    // Map statuses to numeric stages (used by delivery.php UI)
    $stageMap = [
        'Pending'   => 1,
        'Shipped'   => 2,
        'Order On The Way'  => 3,
        'Delivered' => 4,
        'Cancelled' => 0,
    ];

    $currentStage = $stageMap[$status] ?? 1;

    echo json_encode(['stage' => $currentStage, 'status' => $status]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
