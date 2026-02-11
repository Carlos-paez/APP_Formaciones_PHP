<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

try {
    require_once __DIR__ . '/database.php';
    
    $db = new Database();
    $events = $db->getEvents();
    
    if (!is_array($events)) {
        echo json_encode(['alerts' => []]);
        exit();
    }
    
    $now = new DateTime();
    $currentHour = (int)$now->format('H');
    $currentMinute = (int)$now->format('i');
    $currentTime = $currentHour * 60 + $currentMinute;
    
    $alerts = [];
    
    foreach ($events as $event) {
        $hora_fin = $event['hora_fin'];
        list($endHour, $endMinute) = explode(':', $hora_fin);
        $endTime = $endHour * 60 + $endMinute;
        
        // Verificar si el evento acaba de finalizar (en el último minuto)
        if ($currentTime >= $endTime && $currentTime <= $endTime + 1) {
            $alerts[] = [
                'type' => 'finished',
                'event' => $event,
                'message' => "🔔 ¡EVENTO FINALIZADO!\n\nEl evento en {$event['ubicacion']} con el formador {$event['formador']} ha concluido.\n\n⚠️ ES NECESARIO RECONFIGURAR LOS EQUIPOS PRESTADOS."
            ];
            continue; // Solo una alerta por evento
        }
        
        // Verificar alertas de advertencia (10 y 5 minutos antes)
        $warningPoints = [10, 5];
        foreach ($warningPoints as $minutesBefore) {
            $warningTime = $endTime - $minutesBefore;
            // Ajustar si el tiempo es negativo (cruza medianoche)
            if ($warningTime < 0) $warningTime += 24 * 60;
            
            // Verificar con margen de 1 minuto
            if ($currentTime >= $warningTime && $currentTime <= $warningTime + 1) {
                $alerts[] = [
                    'type' => 'warning',
                    'event' => $event,
                    'minutes_remaining' => $minutesBefore,
                    'message' => "⏰ ¡ATENCIÓN!\n\nEl evento en {$event['ubicacion']} con el formador {$event['formador']} finaliza en {$minutesBefore} minutos.\n\nHora de finalización: {$event['hora_fin']}"
                ];
                break; // Solo una advertencia por evento
            }
        }
    }
    
    echo json_encode(['alerts' => $alerts, 'current_time' => "$currentHour:$currentMinute"]);
    
} catch (Exception $e) {
    error_log("check_alerts.php Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'alerts' => [],
        'error' => $e->getMessage()
    ]);
}
?>