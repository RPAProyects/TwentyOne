<?php
require '../../vendor/autoload.php';
require_once '../../Controllers/auth_and_db.php'; // Conexión a MongoDB y sesión

// Verifica si el usuario está autenticado
$user = checkAuth();
if (!$user) {
    header("Location: ../../Auth/login.php");
    exit;
}

// ==================================================================
// 1) Extraer portfolioData y determinar la lista de activos actuales
// ==================================================================
$rawPortfolio = $user['userData']['portfolioData'] ?? [];

// Convertir BSONArray a PHP array
$portfolioData = [];
if ($rawPortfolio instanceof MongoDB\Model\BSONArray) {
    foreach ($rawPortfolio as $item) {
        if ($item instanceof MongoDB\Model\BSONDocument) {
            $portfolioData[] = (array)$item;
        } elseif (is_array($item)) {
            $portfolioData[] = $item;
        }
    }
} elseif (is_array($rawPortfolio)) {
    $portfolioData = $rawPortfolio;
}

// Crear la lista de activos (todas las claves distintas de 'month')
$activos = [];
foreach ($portfolioData as $row) {
    foreach ($row as $field => $val) {
        if ($field === 'month') continue;
        if (!in_array($field, $activos, true)) {
            $activos[] = $field;
        }
    }
}

// ==================================================================
// 2) Tomar los colores existentes del usuario (si los hay)
// ==================================================================
$colorMapping = [];
if (
    isset($user['config']['colors'])
    && $user['config']['colors'] instanceof MongoDB\Model\BSONDocument
) {
    foreach ($user['config']['colors'] as $assetName => $hexColor) {
        $colorMapping[$assetName] = (string)$hexColor;
    }
}

// ==================================================================
// 3) Eliminar colores que ya no correspondan a ningún activo actual
// ==================================================================
foreach (array_keys($colorMapping) as $assetName) {
    if (!in_array($assetName, $activos, true)) {
        unset($colorMapping[$assetName]);
    }
}

// ==================================================================
// 4) Detectar activos que NO tienen color asignado y generar uno aleatorio
//    para cada uno. Luego actualizar en MongoDB.
// ==================================================================
function generateRandomHexColor() {
    return '#' . str_pad(dechex(rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
}

$missing = [];
foreach ($activos as $activo) {
    if (!array_key_exists($activo, $colorMapping)) {
        $nuevoColor = generateRandomHexColor();
        $colorMapping[$activo] = $nuevoColor;
        $missing[$activo] = $nuevoColor;
    }
}

// ==================================================================
// 5) Si hubo cambios (se borraron colores o se agregaron nuevos), actualizar MongoDB
// ==================================================================
if (!empty($missing) || !empty(array_diff_key($user['config']['colors']->getArrayCopy() ?? [], array_flip($activos)))) {
    $usersColl = getUserCollection();
    $userId    = new MongoDB\BSON\ObjectId($user['_id']);

    $updateResult = $usersColl->updateOne(
        ['_id' => $userId],
        ['$set' => ['config.colors' => $colorMapping]]
    );
    // Opcional: verificar $updateResult->getModifiedCount()
}

// Ahora $colorMapping contiene exactamente los colores válidos para todos los activos
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <title>TwentyOne – Config</title>
    <link rel="icon" type="image/x-icon" href="data:image/x-icon;base64," />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="" />
    <link rel="stylesheet" as="style" onload="this.rel='stylesheet'"
          href="https://fonts.googleapis.com/css2?display=swap&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900&amp;family=Public+Sans%3Awght%40400%3B500%3B700%3B900" />
</head>
<body class="bg-white font-sans min-h-screen flex flex-col">
    <div class="flex flex-1 flex-col">
        <!-- Header (no cambia) -->
        <header class="flex items-center justify-between border-b px-10 py-3">
            <div class="flex items-center gap-4 text-[#121416]">
                <div class="w-8 h-8">
                    <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M42.4379 44C42.4379 44 36.0744 33.9038 41.1692 24C46.8624 12.9336 42.2078 4 42.2078 4L7.01134 4C7.01134 4 11.6577 12.932 5.96912 23.9969C0.876273 33.9029 7.27094 44 7.27094 44L42.4379 44Z" fill="currentColor"></path>
                    </svg>
                </div>
                <h2 class="text-lg font-bold">TwentyOne</h2>
            </div>
            <nav class="flex items-center gap-6">
                <a href="../index.php" class="text-[#121416] text-sm font-medium">Inicio</a>
                <a href="../Advanced/" class="text-[#121416] text-sm font-medium">Métricas</a>
                <a href="#" class="text-[#121416] text-sm font-medium">Objetivos</a>
                <a href="#" class="text-[#121416] text-sm font-medium">Academia</a>
                <a href="../../Auth/logout.php" class="text-[#121416] text-sm font-medium">Cerrar Sesión</a>
                <a href="#">
                    <button class="flex items-center gap-2 bg-[#f1f2f4] px-3 py-2 rounded-xl text-[#121416] font-bold">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             class="lucide lucide-plus">
                            <path d="M12 5v14"></path>
                            <path d="M5 12h14"></path>
                        </svg>
                    </button>
                </a>
                <div class="w-10 h-10 rounded-full bg-center bg-cover"
                     style="background-image: url('https://via.placeholder.com/40');"></div>
            </nav>
        </header>

        <!-- Contenido principal -->
        <main class="flex flex-1 flex-col items-center py-6 px-8">
            <div class="w-full max-w-4xl">
                <!-- Título y controles -->
                 <br>
                <div class="flex items-center justify-between mb-4">
                    <h1 class="text-2xl font-bold text-[#111418]">Editar Portfolio</h1>
                    <div class="flex items-center gap-2">
                        <button id="addMonthBtn"
                                class="flex min-w-[84px] cursor-pointer items-center justify-center rounded-xl h-8 px-4 bg-[#f0f2f4] text-[#111418] text-sm font-medium leading-normal">
                            New Transaction
                        </button>
                        <button id="saveBtn"
                                class="flex min-w-[84px] cursor-pointer items-center justify-center rounded-xl h-8 px-4 bg-[#f0f2f4] text-[#111418] text-sm font-medium leading-normal">
                            Guardar Cambios
                        </button>
                    </div>
                </div>

                <!-- Tabla editable -->
                <div class="overflow-x-auto rounded-xl border border-[#dbe0e6] bg-white">
                    <table id="dataTable" class="min-w-full">
                        <thead>
                            <tr id="headerRow"></tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <!-- JSON de depuración (oculto) -->
                <textarea id="jsonOutput" style="display: none;"
                          class="mt-4 w-full h-40 rounded-xl border border-[#dbe0e6] bg-[#f9fafb] p-4 text-sm font-mono text-[#111418]"
                          readonly placeholder="El JSON se actualiza aquí..."></textarea>
            </div>

            <div class="flex items-center gap-2 mt-4">
                <input type="text" id="newCategoryName"
                       class="rounded-xl border border-[#dbe0e6] bg-white px-4 py-2 text-[#111418] text-base"
                       placeholder="Nueva categoría" />
                <button id="addCategoryBtn"
                        class="flex min-w-[84px] cursor-pointer items-center justify-center rounded-xl h-8 px-4 bg-[#f0f2f4] text-[#111418] text-sm font-medium leading-normal">
                    Add Category
                </button>
            </div>

            <!-- Sección para editar colores -->
            <div class="w-full max-w-4xl mt-8">
                <h2 class="text-2xl font-bold text-[#111418]">Editar Colores</h2>
                <div class="mt-4">
                    <div id="colorEditorContainer"></div>
                </div>
            </div>
        </main>
    </div>
<script>const colorMap = <?php echo json_encode($colorMapping); ?>;</script>
    <script src="../../src/js/Config.js"></script>
</body>
</html>
