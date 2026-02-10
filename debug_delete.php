<?php
// Archivo de diagnóstico para probar la eliminación
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo "=== DIAGNÓSTICO DE ELIMINACIÓN DE EVENTOS ===\n\n";

// 1. Verificar archivo de base de datos
$dbFile = __DIR__ . '/events.db';
echo "1. Ruta de base de datos: $dbFile\n";
echo "   Existe: " . (file_exists($dbFile) ? 'SÍ' : 'NO') . "\n";
echo "   Es escribible: " . (is_writable($dbFile) ? 'SÍ' : 'NO') . "\n";
echo "   Permisos: " . substr(sprintf('%o', fileperms($dbFile)), -4) . "\n\n";

// 2. Conectar a la base de datos
try {
    require_once 'database.php';
    $db = new Database();
    echo "2. Conexión a base de datos: ÉXITO\n\n";

    // 3. Obtener eventos
    $events = $db->getEvents();
    echo "3. Eventos en la base de datos: " . count($events) . "\n";

    if (count($events) > 0) {
        echo "   Primer evento:\n";
        echo "   - ID: " . $events[0]['id'] . "\n";
        echo "   - Ubicación: " . $events[0]['ubicacion'] . "\n";
        echo "   - Formador: " . $events[0]['formador'] . "\n";
    }
    echo "\n";

    // 4. Probar eliminación (SOLO si hay eventos)
    if (count($events) > 0) {
        $testId = $events[0]['id'];
        echo "4. Probando eliminación del evento ID: $testId\n";

        $result = $db->deleteEvent($testId);
        echo "   Resultado: " . ($result['success'] ? 'ÉXITO' : 'FALLO') . "\n";
        echo "   Mensaje: " . $result['message'] . "\n";

        // Verificar si realmente se eliminó
        $eventsAfter = $db->getEvents();
        $stillExists = false;
        foreach ($eventsAfter as $event) {
            if ($event['id'] == $testId) {
                $stillExists = true;
                break;
            }
        }

        echo "   Verificación: " . ($stillExists ? 'FALLO - Evento aún existe' : 'ÉXITO - Evento eliminado') . "\n";

        // Volver a insertar el evento para no perder datos
        if (!$stillExists && count($events) > 0) {
            $db->saveEvent(
                $events[0]['ubicacion'],
                $events[0]['formador'],
                $events[0]['hora_inicio'],
                $events[0]['hora_fin']
            );
            echo "   Evento restaurado para pruebas\n";
        }
    } else {
        echo "4. No hay eventos para probar eliminación\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DEL DIAGNÓSTICO ===\n";
?>