<?php
// data.php

// 1) Cargar autoload de Composer para MongoDB
require '../../vendor/autoload.php';
require_once '../../Controllers/auth_and_db.php'; // Incluye tu conexión a MongoDB

$user = checkAuth();
if (!$user) {
    header("Location: ../../Auth/login.php");
    exit;
}

// 3) Extraer colores y portfolioData del BSONDocument retornado en $user
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
    isset($user['userData']) &&
    isset($user['userData']['portfolioData']) &&
    $user['userData']['portfolioData'] instanceof MongoDB\Model\BSONArray
) {
    foreach ($user['userData']['portfolioData'] as $item) {
        $row = [];
        foreach ($item as $field => $value) {
            if ($field === 'month') {
                // Guardamos la cadena completa, p.e. "Agosto 2024"
                $row[$field] = (string)$value;
            } else {
                $row[$field] = (float)$value;
            }
        }
        $portfolioData[] = $row;
    }
}

// 4) Preparar datos para Chart.js: total de la cartera por mes
$labels = [];
$fullLabels = [];
$totals = [];

foreach ($portfolioData as $row) {
    $mesCompleto = $row['month']; // Ej: "Agosto 2024"
    $nombreMes = explode(' ', $mesCompleto)[0];
    $mesAbreviado = mb_substr($nombreMes, 0, 3, 'UTF-8');

    $labels[] = $mesAbreviado;
    $fullLabels[] = $mesCompleto;

    $suma = 0;
    foreach ($row as $field => $value) {
        if ($field === 'month') continue;
        $suma += (float)$value;
    }
    $totals[] = $suma;
}


$activosSeries = [];
$activos = [];

foreach ($portfolioData as $row) {
    foreach ($row as $activo => $valor) {
        if ($activo === 'month') continue;

        if (!isset($activosSeries[$activo])) {
            $activosSeries[$activo] = [];
        }
        $activosSeries[$activo][] = $valor;

        if (!in_array($activo, $activos, true)) {
            $activos[] = $activo;
        }
    }
}

// 5) Declarar las funciones de formateo ANTES de usarlas
function formatearMoneda($valor)
{
    return '$' . number_format((float)$valor, 2, ',', '.');
}

function formatearPorcentaje($valor)
{
    $signo = $valor > 0 ? "+" : ($valor < 0 ? "" : "");
    // $valor entra como proporción (p.e. 0.125 → 12.50%)
    return $signo . number_format($valor * 100, 2, ',', '.') . "%";
}

// 6) Calcular rendTotal para mostrar “Rendimiento total de la cartera”
$rendTotal = null;
if (count($totals) >= 2) {
    $primero = $totals[0];
    $ultimo  = end($totals);
    // Evitamos división por cero
    if ($primero != 0) {
        $rendTotal = ($ultimo - $primero) / $primero;
    }
}

$graphConfigJson = '{}';
if ($user && isset($user['config']['graph'])) {
    $graphConfigJson = json_encode($user['config']['graph']);
}

$data = false;
if (isset($portfolioData) && is_array($portfolioData)) {
    $data = (count($portfolioData) > 1);
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0" />
    <title>TwentyOne - Advanced</title>
    <link rel="icon" type="image/x-icon" href="data:image/x-icon;base64," />

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com/"
        crossorigin="" />
    <link
        rel="stylesheet"
        as="style"
        onload="this.rel='stylesheet'"
        href="https://fonts.googleapis.com/css2?display=swap&amp;family=Noto+Sans:wght@400;500;700;900&amp;family=Public+Sans:wght@400;500;700;900" />
    <link rel="stylesheet" href="../../src/css/Advanced.css" type="text/css">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script src="https://cdn.jsdelivr.net/npm/echarts/dist/echarts.min.js"></script>
    <script src="../../src/js/Advanced.js"></script>

</head>

<body>
    <div>

        <div class="layout-container flex h-full grow flex-col">

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
                    <a class="text-[#111418] text-sm font-medium" href="../">Inicio</a>
                    <a href="#" class="text-[#121416] text-sm font-medium">Métricas</a>
                    <a href="#" class="text-[#121416] text-sm font-medium">Objetivos</a>
                    <a href="#" class="text-[#121416] text-sm font-medium">Academia</a>
                    <a href="../../Auth/logout.php" class="text-[#121416] text-sm font-medium">Cerrar Sesión</a>
                    <a href="../Config/"><button
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
                <div id="toast-container"></div>
                <div class="px-40 flex flex-1 justify-center py-5">
                    <div class="layout-content-container flex flex-col max-w-[1280px] flex-1">
                        <div class="flex flex-wrap justify-between gap-3 p-4">
                            <div class="flex min-w-72 flex-col gap-3">
                                <p class="text-[#111418] tracking-light text-[32px] font-bold leading-tight">
                                    Mi cartera
                                </p>
                                <p class="text-[#637488] text-sm font-normal leading-normal">
                                    Rendimiento total de la cartera:
                                    <?php
                                    if ($rendTotal !== null) {
                                        echo formatearPorcentaje($rendTotal);
                                    } else {
                                        echo '–';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-4 px-4 py-6">

                            <div class="w-full flex flex-col gap-2 rounded-lg border border-[#dce0e5] p-6">
                                <p class="text-[#111418] text-base font-medium leading-normal">Rendimiento de la cartera</p>
                                <p class="text-[#111418] tracking-light text-[32px] font-bold leading-tight truncate">
                                    <?php echo $ultimo !== null ? ($ultimo . ' EUR') : '0'; ?>
                                </p>
                                <div class="flex gap-1">
                                    <p class="text-[#637488] text-base font-normal leading-normal">1Y</p>
                                    <p class="text-[#07883b] text-base font-medium leading-normal">
                                        <?php echo $rendTotal !== null ? formatearPorcentaje($rendTotal) : '0%'; ?>
                                    </p>
                                </div>
                                <div class="flex min-h-[180px] flex-1 flex-col gap-8 py-4">
                                    <div id="line-chart" class="w-full" style="height: 400px;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Segunda fila: Volatilidad y Regresión -->
                        <!-- Gráficas agrupadas de dos en dos -->
                        <div class="flex flex-wrap px-4 py-6 gap-4" id="charts-container">
                            <!-- Cada bloque gráfico -->
                            <div id="volatility-chart-block" class="chart-block transition-all duration-300 ease-in-out flex-1 min-w-[300px] md:basis-[calc(50%-0.5rem)] flex flex-col gap-2 rounded-lg border border-[#dce0e5] p-6">
                                <p class="text-[#111418] text-base font-medium leading-normal">Volatilidad y retorno</p>
                                <p class="text-[#637488] text-sm font-normal leading-normal">Comparación entre 3M y 6M</p>
                                <div class="flex min-h-[240px] flex-1 flex-col gap-8 py-4">
                                    <div id="volatility-chart" class="chart-toggle w-full" style="height: 300px;"></div>
                                </div>
                            </div>

                            <div id="regression-chart-block" class="chart-block transition-all duration-300 ease-in-out flex-1 min-w-[300px] md:basis-[calc(50%-0.5rem)] flex flex-col gap-2 rounded-lg border border-[#dce0e5] p-6">
                                <p class="text-[#111418] text-base font-medium leading-normal">Evolución de la cartera</p>
                                <p class="text-[#637488] text-sm font-normal leading-normal">Regresión Lineal</p>
                                <div class="flex min-h-[300px] flex-1 flex-col gap-8 py-4">
                                    <div id="regression-chart" class="chart-toggle w-full" style="height: 300px;"></div>
                                </div>
                            </div>

                            <div id="asset-chart-block" class="chart-block transition-all duration-300 ease-in-out flex-1 min-w-[300px] md:basis-[calc(50%-0.5rem)] flex flex-col gap-2 rounded-lg border border-[#dce0e5] p-6">
                                <p class="text-[#111418] text-base font-medium leading-normal">Evolución por activo</p>
                                <div class="flex min-h-[300px] flex-1 flex-col gap-8 py-4">
                                    <div id="asset-chart" class="chart-toggle w-full" style="height: 400px;"></div>
                                </div>
                            </div>

                            <div id="asset-diff-chart-block" class="chart-block transition-all duration-300 ease-in-out flex-1 min-w-[300px] md:basis-[calc(50%-0.5rem)] flex flex-col gap-2 rounded-lg border border-[#dce0e5] p-6">
                                <p class="text-[#111418] text-base font-medium leading-normal">Incremento por activo</p>
                                <div class="flex min-h-[300px] flex-1 flex-col gap-8 py-4">
                                    <div id="asset-diff-chart" class="chart-toggle w-full" style="height: 400px;"></div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
        </div>
    </div>
   <!-- Botón flotante fijo en la esquina inferior derecha -->
<button
  id="toggleChartsBtn"
  class="fixed bottom-4 right-4 w-12 h-12 rounded-full bg-black text-white flex items-center justify-center shadow-lg  z-50"
  title="Mostrar/Ocultar Opciones de Gráficas"
>
  <!-- Puedes reemplazar este círculo por un icono si prefieres -->
  <span class="text-xl font-bold"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings2-icon lucide-settings-2"><path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/></svg></span>
</button>

<!-- Contenedor del popup (inicialmente oculto) -->
<div
  id="chartsPopup"
  class="fixed bottom-20 right-4 w-64 bg-white rounded-lg shadow-xl border border-gray-200 p-4 hidden z-40"
>
  <p class="text-[#111418] text-sm font-medium mb-2">Mostrar gráficas:</p>
  <div class="flex flex-col gap-3 text-sm text-[#111418]">
    <label class="flex items-center gap-2">
      <input
        type="checkbox"
        class="chart-checkbox"
        data-target="volatility-chart-block"
        checked
      />
      Volatilidad y retorno
    </label>
    <label class="flex items-center gap-2">
      <input
        type="checkbox"
        class="chart-checkbox"
        data-target="regression-chart-block"
        checked
      />
      Regresión Lineal
    </label>
    <label class="flex items-center gap-2">
      <input
        type="checkbox"
        class="chart-checkbox"
        data-target="asset-chart-block"
        checked
      />
      Evolución por activo
    </label>
    <label class="flex items-center gap-2">
      <input
        type="checkbox"
        class="chart-checkbox"
        data-target="asset-diff-chart-block"
        checked
      />
      Incremento por activo
    </label>
  </div>
</div>

<script>
  // Mostrar/ocultar el popup al pulsar el botón
  const toggleBtn = document.getElementById('toggleChartsBtn');
  const popup = document.getElementById('chartsPopup');
  toggleBtn.addEventListener('click', () => {
    popup.classList.toggle('hidden');
  });

  // Cerramos el popup si el usuario hace clic fuera de él
  document.addEventListener('click', (e) => {
    if (
      !popup.contains(e.target) &&
      !toggleBtn.contains(e.target) &&
      !popup.classList.contains('hidden')
    ) {
      popup.classList.add('hidden');
    }
  });
</script>

    <script>
        const rawLabels = <?= json_encode($fullLabels,       JSON_UNESCAPED_UNICODE) ?>;
        const rawData = <?= json_encode($totals,           JSON_UNESCAPED_UNICODE) ?>;
        const assetSeries = <?= json_encode($activosSeries,    JSON_UNESCAPED_UNICODE) ?>;
        const assetKeys = <?= json_encode($activos,          JSON_UNESCAPED_UNICODE) ?>;
        const colorMap = <?= json_encode($colorMapping,     JSON_UNESCAPED_UNICODE) ?>;
        const userGraphConfig = <?php echo $graphConfigJson; ?>;
    </script>
<?php else: ?>
    <div class="px-40 flex flex-1 justify-center py-5">
        <div class="layout-content-container flex flex-col max-w-[1280px] flex-1">
            <div class="flex flex-wrap justify-between gap-3 p-4">
                <div class="flex min-w-72 flex-col gap-3">
                    <p class="text-[#111418] tracking-light text-[32px] font-bold leading-tight">
                        Mi cartera
                    </p>
                    <?php
                    $dataCount = is_array($portfolioData) ? count($portfolioData) : 0;
                    ?>

                    <?php if ($dataCount < 1): ?>
                        <p class="text-[#637488] text-sm font-normal leading-normal">
                            Empieza añadiendo un primer registro <a href="../Config/" style="text-decoration:underline;">desde aquí.</a>
                        </p>
                    <?php elseif ($dataCount < 2): ?>
                        <p class="text-[#637488] text-sm font-normal leading-normal">
                            Para acceder a estadísticas necesitas mínimo 2 registros. <a href="../Config/" style="text-decoration:underline;"> Añadir un nuevo registro.</a>
                        </p>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    <?php endif; ?>
</body>

</html>