<?php
// Configurar cabeceras para permitir solicitudes desde el mismo origen
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar solicitudes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'database.php';

// Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

try {
    // Obtener datos del cuerpo de la solicitud
    $json = file_get_contents('php://input');

    // Si no hay datos, intentar obtener de POST normal
    if (empty($json)) {
        $data = $_POST;
    } else {
        $data = json_decode($json, true);

        // Verificar si el JSON es válido
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Datos JSON inválidos');
        }
    }

    // Validar campos requeridos
    if (!isset($data['ubicacion']) || !isset($data['formador']) ||
        !isset($data['hora_inicio']) || !isset($data['hora_fin'])) {
        throw new Exception('Todos los campos son requeridos');
    }

    // Limpiar datos
    $ubicacion = trim($data['ubicacion']);
    $formador = trim($data['formador']);
    $hora_inicio = trim($data['hora_inicio']);
    $hora_fin = trim($data['hora_fin']);

    // Validar que no estén vacíos
    if (empty($ubicacion) || empty($formador) || empty($hora_inicio) || empty($hora_fin)) {
        throw new Exception('Todos los campos son requeridos');
    }

    // Crear instancia de base de datos
    $db = new Database();

    // Guardar evento
    $result = $db->saveEvent($ubicacion, $formador, $hora_inicio, $hora_fin);

    // Devolver respuesta
    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos'
    ]);
}
?>