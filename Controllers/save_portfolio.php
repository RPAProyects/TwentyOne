<?php
require_once '../vendor/autoload.php'; 
require_once 'auth_and_db.php';
$user = checkAuth();
if (!$user) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'No autorizado']);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['portfolioData']) || !isset($data['categories'])) {
  echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
  exit;
}

// Aquí deberías validar y sanitizar $data antes de guardarlos en DB

// Ejemplo guardando en MongoDB (ajusta según tu configuración)
try {
  $collection = getMongoDBCollection('users');
  $collection->updateOne(
    ['_id' => new MongoDB\BSON\ObjectId($user['_id'])],
    ['$set' => [
      'userData.portfolioData' => $data['portfolioData'],
      'config.colors' => array_fill_keys($data['categories'], '#000000') // o mantener colores existentes
    ]]
  );

  echo json_encode(['success' => true]);
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
