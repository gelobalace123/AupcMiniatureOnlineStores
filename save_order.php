<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents("php://input"), true);

if ($data) {
    $order = [
        'name' => $data['name'],
        'email' => $data['email'],
        'address' => $data['address'],
        'payment' => $data['payment'],
        'cart' => $data['cart'],
        'total' => $data['total'],
        'timestamp' => $data['timestamp']
    ];

    $ordersFile = 'orders.json';
    $orders = [];
    if (file_exists($ordersFile)) {
        $existingData = file_get_contents($ordersFile);
        $orders = json_decode($existingData, true);
        if (!is_array($orders)) {
            $orders = [];
        }
    }

    $orders[] = $order;
    $success = file_put_contents($ordersFile, json_encode($orders, JSON_PRETTY_PRINT));
    
    if ($success !== false) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to write to file']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
}
?>