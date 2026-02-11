<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-cache, no-store, must-revalidate');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once __DIR__ . '/database.php';

    error_log("\n========================================");
    error_log("=== INICIANDO PROCESO DE ELIMINACIÓN ===");
    error_log("========================================");
    error_log("Método HTTP: " . $_SERVER['REQUEST_METHOD']);
    error_log("URL solicitada: " . $_SERVER['REQUEST_URI']);
    error_log("Query String: " . $_SERVER['QUERY_STRING']);

    // Obtener ID de múltiples fuentes
    $id = null;
    $source = 'unknown';

    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $source = 'GET';
        error_log("ID recibido por GET: " . var_export($id, true));
    } elseif (isset($_POST['id'])) {
        $id = $_POST['id'];
        $source = 'POST';
        error_log("ID recibido por POST: " . var_export($id, true));
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $rawData = file_get_contents('php://input');
        error_log("Datos crudos DELETE: " . $rawData);
        $data = json_decode($rawData, true);
        if (isset($data['id'])) {
            $id = $data['id'];
            $source = 'DELETE body';
            error_log("ID recibido por DELETE body: " . var_export($id, true));
        }
    }

    // Extraer ID de la URL si está en formato /delete_event.php/5
    if ($id === null) {
        $pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
        error_log("PATH_INFO: " . var_export($pathInfo, true));

        if ($pathInfo) {
            $parts = explode('/', trim($pathInfo, '/'));
            if (isset($parts[0]) && is_numeric($parts[0])) {
                $id = $parts[0];
                $source = 'PATH_INFO';
                error_log("ID extraído de PATH_INFO: " . var_export($id, true));
            }
        }
    }

    if ($id === null) {
        throw new Exception('No se proporcionó ID de evento. Fuente: ' . $source);
    }

    // Validar y limpiar ID
    if (!is_numeric($id)) {
        throw new Exception('ID de evento no es numérico: ' . var_export($id, true));
    }

    $id = intval($id);

    if ($id <= 0) {
        throw new Exception('ID de evento inválido (<= 0): ' . $id);
    }

    error_log("✅ ID validado correctamente: $id (fuente: $source)");

    // Crear instancia de base de datos
    $db = new Database();

    // Diagnóstico: Contar eventos totales
    $totalEvents = $db->countEvents();
    error_log("📊 Total de eventos en la base de datos: $totalEvents");

    // Diagnóstico: Obtener todos los IDs
    $allStmt = $db->getDb()->query("SELECT id, ubicacion, formador, hora_fin FROM events ORDER BY id");
    $allEvents = $allStmt->fetchAll();

    error_log("📋 Eventos en la base de datos:");
    foreach ($allEvents as $evt) {
        error_log("   ID {$evt['id']}: {$evt['ubicacion']} - {$evt['formador']} (Fin: {$evt['hora_fin']})");
    }

    // Verificar si el evento existe ANTES de intentar eliminar
    error_log("\n🔍 Verificando existencia del evento con ID: $id");
    $eventExists = $db->getEventById($id);

    if (!$eventExists) {
        error_log("❌❌ EVENTO NO ENCONTRADO con ID: $id");

        $availableIds = array_column($allEvents, 'id');
        error_log("IDs disponibles: " . implode(', ', $availableIds));

        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => "Evento no encontrado con ID: $id",
            'error_type' => 'not_found',
            'requested_id' => $id,
            'available_ids' => $availableIds,
            'total_events' => $totalEvents
        ]);
        exit();
    }

    error_log("✅✅ EVENTO ENCONTRADO:");
    error_log("   ID: {$eventExists['id']}");
    error_log("   Ubicación: {$eventExists['ubicacion']}");
    error_log("   Formador: {$eventExists['formador']}");
    error_log("   Hora inicio: {$eventExists['hora_inicio']}");
    error_log("   Hora fin: {$eventExists['hora_fin']}");
    error_log("   Creado: {$eventExists['created_at']}");

    // Proceder con la eliminación
    error_log("\n🗑️ PROCEDIENDO A ELIMINAR EL EVENTO...");
    $result = $db->deleteEvent($id);

    error_log("\n========================================");
    error_log("=== RESULTADO DE LA ELIMINACIÓN ===");
    error_log("========================================");
    error_log("Resultado: " . json_encode($result));

    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);

} catch (Exception $e) {
    error_log("\n❌❌❌ EXCEPCIÓN CAPTURADA ❌❌❌");
    error_log("Mensaje: " . $e->getMessage());
    error_log("Tipo: " . get_class($e));
    error_log("Archivo: " . $e->getFile());
    error_log("Línea: " . $e->getLine());
    error_log("Trace: " . $e->getTraceAsString());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => 'Exception',
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (PDOException $e) {
    error_log("\n❌❌❌ ERROR PDO ❌❌❌");
    error_log("Mensaje: " . $e->getMessage());
    error_log("Código: " . $e->getCode());
    error_log("Info: " . print_r($e->errorInfo, true));

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos',
        'error_type' => 'PDOException',
        'error_code' => $e->getCode(),
        'error_info' => $e->errorInfo
    ]);
} catch (Error $e) {
    error_log("\n❌❌❌ ERROR PHP ❌❌❌");
    error_log("Mensaje: " . $e->getMessage());
    error_log("Tipo: " . get_class($e));

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor',
        'error_type' => 'Error',
        'details' => $e->getMessage()
    ]);
}

error_log("\n=== FIN DEL PROCESO DE ELIMINACIÓN ===\n");
?>