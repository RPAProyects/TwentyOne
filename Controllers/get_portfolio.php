<?php
header('Content-Type: application/json');

require_once '../vendor/autoload.php'; 

require 'auth_and_db.php';



$user = checkAuth();
if (!$user) {
  echo json_encode([]);
  exit;
}

echo json_encode($user['userData']['portfolioData'] ?? []);
