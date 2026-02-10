<?php
// Configurar cabeceras
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar solicitudes OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Permitir tanto GET como POST para mayor compatibilidad
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido',
        'debug' => 'Method: ' . $_SERVER['REQUEST_METHOD']
    ]);
    exit();
}

try {
    require_once __DIR__ . '/database.php';

    // Obtener ID del evento de diferentes fuentes
    $id = null;

    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
    } elseif (isset($_POST['id'])) {
        $id = intval($_POST['id']);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Para solicitudes DELETE, parsear el cuerpo
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (isset($data['id'])) {
            $id = intval($data['id']);
        }
    }

    // Si no se encontró el ID, intentar parsear la URL
    if ($id === null || $id <= 0) {
        // Intentar extraer ID de la URL (ej: delete_event.php/5)
        $pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
        if ($pathInfo) {
            $parts = explode('/', trim($pathInfo, '/'));
            if (isset($parts[0]) && is_numeric($parts[0])) {
                $id = intval($parts[0]);
            }
        }
    }

    if ($id === null || $id <= 0) {
        throw new Exception('ID de evento inválido o no proporcionado');
    }

    // Crear instancia de base de datos
    $db = new Database();

    // Eliminar evento directamente usando el método deleteEvent
    $result = $db->deleteEvent($id);

    echo json_encode($result);

} catch (Exception $e) {
    error_log("Error en delete_event.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => 'Exception'
    ]);
} catch (PDOException $e) {
    error_log("Error PDO en delete_event.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos',
        'error_type' => 'PDOException'
    ]);
} catch (Error $e) {
    error_log("Error PHP en delete_event.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor',
        'error_type' => 'Error'
    ]);
}
?>