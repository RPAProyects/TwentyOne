<?php
$client = new MongoDB\Client("mongodb+srv://rperezaledo:mSVLDt9YaBvS28ru@cluster0.d6kdgst.mongodb.net/");
$collection = $client->finanzas->users;

function getUserCollection() {
  global $collection;
  return $collection;
}

// auth.php
session_start();

function login($username, $password) {
  $user = getUserCollection()->findOne(['username' => $username]);
  if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = (string)$user['_id'];
    return true;
  }
  return false;
}

function register($username, $password) {
  $existing = getUserCollection()->findOne(['username' => $username]);
  if ($existing) return false;

  $data = [
    'username' => $username,
    'password' => password_hash($password, PASSWORD_BCRYPT),
    'config' => [
      'colors' => [
        'Cartera' => '#8bb0a4',
      ],
      'preferences' => []
    ],
    'userData' => [
      'portfolioData' => [ ],
      'goals' => []
    ]
  ];

  $result = getUserCollection()->insertOne($data);
  $_SESSION['user_id'] = (string)$result->getInsertedId();
  return true;
}

function checkAuth() {
  if (!isset($_SESSION['user_id'])) return null;
  $id = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
  return getUserCollection()->findOne(['_id' => $id]);
}

function logout() {
  session_destroy();
  header("Location: ../Auth/login.php");
  exit;
}
