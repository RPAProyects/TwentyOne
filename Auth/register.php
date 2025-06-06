<?php
require '../vendor/autoload.php';
require_once '../Controllers/auth_and_db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (register($_POST['username'], $_POST['password'])) {
        header("Location: ../App/index.php");
        exit;
    } else {
        $error = 'El usuario ya existe';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Registro</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
  <h2>Registro</h2>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="mb-3">
      <label>Usuario</label>
      <input type="text" name="username" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Contraseña</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <button class="btn btn-success">Registrarse</button>
    <a href="login.php" class="btn btn-link">Volver al login</a>
  </form>
</div>
</body>
</html>
