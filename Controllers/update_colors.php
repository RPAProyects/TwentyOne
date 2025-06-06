<?php
require_once '../vendor/autoload.php'; 
require './auth_and_db.php';

$user = checkAuth();
if (!$user) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'No autenticado']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !is_array($input)) {
  echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
  exit;
}

// Sanitiza y valida colores HEX simples:
foreach ($input as $key => $color) {
  if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
    echo json_encode(['success' => false, 'error' => "Color inválido para $key"]);
    exit;
  }
}

$collection = getUserCollection();

$result = $collection->updateOne(
  ['_id' => new MongoDB\BSON\ObjectId($_SESSION['user_id'])],
  ['$set' => ['config.colors' => $input]]
);

echo json_encode(['success' => $result->getModifiedCount() > 0]);
