<?php
// Controllers/sortportfolio.php
// Ordena el array "portfolioData" del usuario autenticado en MongoDB según el campo "month".
// Utiliza la conexión definida en auth_and_db.php.

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/auth_and_db.php'; // Define getUserCollection() y checkAuth()

header('Content-Type: application/json; charset=utf-8');

$user = checkAuth();
if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'No autenticado']);
    exit;
}

// ------------------------------------------------------
// 1) Extraer userData.portfolioData y convertir cada elemento a array PHP
// ------------------------------------------------------
$rawPortfolio = $user['userData']['portfolioData'] ?? [];

$portfolio = [];
if (is_object($rawPortfolio) && method_exists($rawPortfolio, 'getArrayCopy')) {
    $docs = $rawPortfolio->getArrayCopy();
    foreach ($docs as $doc) {
        if (is_object($doc)) {
            $portfolio[] = (array)$doc;
        } elseif (is_array($doc)) {
            $portfolio[] = $doc;
        }
    }
} elseif (is_array($rawPortfolio)) {
    foreach ($rawPortfolio as $item) {
        if (is_object($item)) {
            $portfolio[] = (array)$item;
        } elseif (is_array($item)) {
            $portfolio[] = $item;
        }
    }
}

// ------------------------------------------------------
// 2) Función auxiliar: "Mes Año" → YYYYMM
//    Reemplaza NBSP (\u00A0) por espacio normal antes de split.
// ------------------------------------------------------
function monthStringToKey(string $monthStr): int {
    $meses = [
        'Enero'      => 1,   'Febrero'   => 2,  'Marzo'     => 3,
        'Abril'      => 4,   'Mayo'      => 5,  'Junio'     => 6,
        'Julio'      => 7,   'Agosto'    => 8,  'Septiembre'=> 9,
        'Octubre'    => 10,  'Noviembre' => 11, 'Diciembre' => 12
    ];
    $normalized = trim(str_replace("\u{00A0}", " ", $monthStr));
    $parts = explode(' ', $normalized, 2);
    if (count($parts) !== 2) {
        return 0;
    }
    [$mesNombre, $anioStr] = $parts;
    $anio   = intval($anioStr);
    $mesNum = $meses[$mesNombre] ?? 0;
    return $anio * 100 + $mesNum;
}

// ------------------------------------------------------
// 3) Ordenar el array PHP según la clave de month
// ------------------------------------------------------
uasort($portfolio, function($a, $b) {
    $keyA = monthStringToKey($a['month'] ?? '');
    $keyB = monthStringToKey($b['month'] ?? '');
    return $keyA <=> $keyB;
});

// Reindexar para tener índices consecutivos (0,1,2...)
$portfolio = array_values($portfolio);

// ------------------------------------------------------
// 4) Recuperar la colección de usuarios desde auth_and_db
// ------------------------------------------------------
$usersColl = getUserCollection();

// ------------------------------------------------------
// 5) Actualizar userData.portfolioData en MongoDB
// ------------------------------------------------------
$userId = $user['_id']; // ObjectId

try {
    $result = $usersColl->updateOne(
        ['_id' => $userId],
        ['$set' => ['userData.portfolioData' => $portfolio]]
    );

    if ($result->getModifiedCount() > 0) {
        echo json_encode(['status' => 'ok', 'message' => 'Portfolio ordenado correctamente']);
    } else {
        echo json_encode([
            'status'  => 'ok',
            'message' => 'No hubo cambios (ya estaba ordenado o no se modificó)'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error al actualizar MongoDB: ' . $e->getMessage()
    ]);
    exit;
}
