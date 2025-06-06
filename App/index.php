<?php
// data.php

// 1) Cargar autoload de Composer para MongoDB
require_once '../vendor/autoload.php';

// 2) Incluye tu fichero de autenticación
require_once '../Controllers/auth_and_db.php';
$user = checkAuth();
if (!$user) {
  header("Location: ../Auth/login.php");
  exit;
}

// 3) Extraer datos directamente del BSONDocument retornado en $user
$colorMapping = [];
if (
  isset($user['config'])
  && isset($user['config']['colors'])
  && $user['config']['colors'] instanceof MongoDB\Model\BSONDocument
) {
  foreach ($user['config']['colors'] as $assetName => $hexColor) {
    $colorMapping[$assetName] = (string)$hexColor;
  }
}

$portfolioData = [];
if (
  isset($user['userData'])
  && isset($user['userData']['portfolioData'])
  && $user['userData']['portfolioData'] instanceof MongoDB\Model\BSONArray
) {
  foreach ($user['userData']['portfolioData'] as $item) {
    $row = [];
    foreach ($item as $field => $value) {
      if ($field === 'month') {
        $row[$field] = (string)$value;
      } else {
        $row[$field] = (float)$value;
      }
    }
    $portfolioData[] = $row;
  }
}
$data = !empty($portfolioData);

// 4) Funciones auxiliares para formatear
function formatearMoneda($valor)
{
  return '$' . number_format((float)$valor, 2, ',', '.');
}
function formatearPorcentaje($valor)
{
  $signo = $valor > 0 ? "+" : ($valor < 0 ? "" : "");
  return $signo . number_format($valor * 100, 2, ',', '.') . "%";
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta
    name="viewport"
    content="width=device-width, initial-scale=1.0" />
  <title>TwentyOne – Portfolio</title>
  <link rel="icon" type="image/x-icon" href="data:image/x-icon;base64," />

  <!-- TailwindCSS -->
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <script src="../src/js/AppIndex.js"></script>
</head>

<body class="font-sans bg-white min-h-screen flex flex-col">
  <header class="flex items-center justify-between border-b px-10 py-3">
    <div class="flex items-center gap-4 text-[#121416]">
      <div class="w-8 h-8">
        <svg
          viewBox="0 0 48 48"
          fill="none"
          xmlns="http://www.w3.org/2000/svg">
          <path
            d="M42.4379 44C42.4379 44 36.0744 33.9038 41.1692 24C46.8624 12.9336 42.2078 4 42.2078 4L7.01134 4C7.01134 4 11.6577 12.932 5.96912 23.9969C0.876273 33.9029 7.27094 44 7.27094 44L42.4379 44Z"
            fill="currentColor"></path>
        </svg>
      </div>
      <h2 class="text-lg font-bold">TwentyOne</h2>
    </div>
    <nav class="flex items-center gap-6">
      <a href="#" class="text-[#121416] text-sm font-medium">Inicio</a>
      <a href="Advanced/" class="text-[#121416] text-sm font-medium">Métricas</a>
      <a href="#" class="text-[#121416] text-sm font-medium">Objetivos</a>
      <a href="#" class="text-[#121416] text-sm font-medium">Academia</a>
      <a href="../Auth/logout.php" class="text-[#121416] text-sm font-medium">Cerrar Sesión</a>
      <a href="./Config/"><button
          class="flex items-center gap-2 bg-[#f1f2f4] px-3 py-2 rounded-xl text-[#121416] font-bold">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus-icon lucide-plus">
            <path d="M5 12h14" />
            <path d="M12 5v14" />
          </svg>
        </button></a>
      <div
        class="w-10 h-10 rounded-full bg-center bg-cover"
        style="background-image: url('https://via.placeholder.com/40');"></div>
    </nav>
  </header>
  <?php if ($data): ?>
    <main class="flex-1 px-10 py-6 flex flex-col items-center">
      <div class="w-full max-w-4xl">
        <h1 class="text-3xl font-bold mb-6">Portafolio</h1>

        <!-- BLOQUE: Distribución del Portafolio -->
        <section class="mb-8">
          <p class="text-lg font-medium mb-2">Distribución del Portafolio</p>
          <div class="flex items-center gap-2 mb-4">
            <p class="text-3xl font-bold" id="annualized-return">0%</p>
            <p class="text-[#6a7581] text-sm">-- Rentabilidad anualizada</p>
          </div>

          <!-- Aquí va el contenedor de barras (sin Chart.js) -->
          <div
            id="bar-chart"
            class="grid min-h-[180px] grid-flow-col gap-6 grid-rows-[1fr_auto] items-end justify-items-center px-3">
            <!-- Se llenará dinámicamente con JS -->
          </div>
        </section>

        <!-- BLOQUE: Resumen -->
        <section class="mb-8">
          <h2 class="text-xl font-bold mb-4">Resumen</h2>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-[#f1f2f4] p-6 rounded-xl flex flex-col gap-2">
              <p class="font-medium">Balance total</p>
              <p class="text-2xl font-bold" id="balance-total">$0.00</p>
            </div>
            <div class="bg-[#f1f2f4] p-6 rounded-xl flex flex-col gap-2">
              <p class="font-medium">Cambio mensual</p>
              <p class="text-2xl font-bold" id="change-today">+$0.00</p>
              <p class="text-green-600 font-medium" id="pct-change-total">-0%</p>
            </div>
            <div class="bg-[#f1f2f4] p-6 rounded-xl flex flex-col gap-2">
              <p class="font-medium">Volatilidad</p>
              <p class="text-2xl font-bold" id="volatility-total">0%</p>
            </div>
          </div>
        </section>

        <!-- BLOQUE: Tabla de Carteras -->
        <section>
          <h2 class="text-xl font-bold mb-4">Carteras</h2>
          <div class="overflow-x-auto border rounded-xl">
            <table class="w-full min-w-[600px]">
              <thead class="bg-white">
                <tr>
                  <th class="px-4 py-3 text-left text-sm font-medium text-[#121416]">Nombre</th>
                  <th class="px-4 py-3 text-left text-sm font-medium text-[#121416]">Balance</th>
                  <th class="px-4 py-3 text-left text-sm font-medium text-[#121416]">Cambio vs mes anterior</th>
                  <th class="px-4 py-3 text-left text-sm font-medium text-[#121416]">Volatilidad</th>
                </tr>
              </thead>
              <tbody id="portfolio-table-body">
                <!-- Se llenará dinámicamente con JS -->
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </main>

    <script>
      // Inyectamos los datos recibidos desde PHP en variables JS
      const serverPortfolioData = <?php echo json_encode($portfolioData, JSON_UNESCAPED_UNICODE); ?>;
      const serverColorMapping = <?php echo json_encode($colorMapping, JSON_UNESCAPED_UNICODE); ?>;
    </script>
  <?php else: ?>
    <main class="flex-1 px-10 py-6 flex flex-col items-center">
      <div class="w-full max-w-4xl">
        <h1 class="text-3xl font-bold mb-6">Portafolio</h1>

        <!-- BLOQUE: Distribución del Portafolio -->
        <section class="mb-8">
          <p class="text-lg font-medium mb-2">Distribución del Portafolio</p>
          <div class="flex items-center gap-2 mb-4">
            <p class="text-[#6a7581] text-sm">Empieza añadiendo un primer registro <a href="Config/" style="text-decoration:underline;">desde aquí.</a></p>
          </div>
        </section>
      </div>
    </main>
  <?php endif; ?>
</body>

</html>