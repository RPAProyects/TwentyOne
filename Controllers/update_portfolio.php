<?php
require_once '../vendor/autoload.php'; 
require_once 'auth_and_db.php';

header('Content-Type: application/json');

$user = checkAuth();
if (!$user) {
  echo json_encode(['status' => 'error', 'error' => 'No autorizado']);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Validar estructura básica
if (!is_array($data)) {
  echo json_encode(['status' => 'error', 'error' => 'Datos inválidos']);
  exit;
}

try {
  $collection = getUserCollection();
  $collection->updateOne(
    ['_id' => $user['_id']],
    ['$set' => ['userData.portfolioData' => $data]]
  );

  echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
  echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
