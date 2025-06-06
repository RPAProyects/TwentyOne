<?php
require '../vendor/autoload.php';
require_once 'auth_and_db.php';

header('Content-Type: application/json');
$user = checkAuth();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['graph']) || !is_array($data['graph'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

$graphConfig = $data['graph'];

$collection = getUserCollection();
$userId = new MongoDB\BSON\ObjectId($user['_id']);

$updateResult = $collection->updateOne(
    ['_id' => $userId],
    ['$set' => ['config.graph' => $graphConfig]]
);

echo json_encode(['success' => true]);
