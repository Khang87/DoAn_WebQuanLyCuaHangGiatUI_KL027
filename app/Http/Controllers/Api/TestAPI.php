<?php

header('Content-Type: application/json; charset=UTF-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Dữ liệu mẫu; thực tế có thể lấy từ cơ sở dữ liệu.
    $products = [
        ['id' => 1, 'name' => 'Bàn phím', 'price' => 250000],
        ['id' => 2, 'name' => 'Chuột', 'price' => 150000],
    ];

    echo json_encode($products, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (
        ! is_array($data) ||
        ! isset($data['name'], $data['price']) ||
        ! is_string($data['name']) ||
        trim($data['name']) === '' ||
        ! is_numeric($data['price']) ||
        $data['price'] < 0
    ) {
        http_response_code(400);
        echo json_encode([
            'message' => 'Tên sản phẩm và giá không hợp lệ.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Chỉ minh họa phản hồi; chưa lưu vào cơ sở dữ liệu.
    echo json_encode([
        'message' => 'Đã nhận dữ liệu sản phẩm.',
        'product' => [
            'name' => trim($data['name']),
            'price' => (float) $data['price'],
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
header('Allow: GET, POST');
echo json_encode([
    'message' => 'Chỉ hỗ trợ GET và POST.',
], JSON_UNESCAPED_UNICODE);
