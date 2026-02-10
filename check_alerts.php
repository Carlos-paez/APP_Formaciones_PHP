<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    require_once __DIR__ . '/database.php';

    $db = new Database();
    $events = $db->getEvents();

    if (!is_array($events)) {
        echo json_encode(['alert' => false]);
        exit();
    }

    $now = new DateTime();
    $currentHour = (int)$now->format('H');
    $currentMinute = (int)$now->format('i');

    foreach ($events as $event) {
        $hora_fin = $event['hora_fin'];
        list($endHour, $endMinute) = explode(':', $hora_fin);

        // Calcular 10 minutos antes
        $alertMinute = $endMinute - 10;
        $alertHour = $endHour;

        if ($alertMinute < 0) {
            $alertMinute += 60;
            $alertHour = ($alertHour - 1 + 24) % 24;
        }

        // Verificar si es tiempo de alerta (con margen de 1 minuto)
        $currentTotalMinutes = $currentHour * 60 + $currentMinute;
        $alertTotalMinutes = $alertHour * 60 + $alertMinute;

        $diff = $alertTotalMinutes - $currentTotalMinutes;

        if ($diff >= 0 && $diff <= 1) {
            echo json_encode([
                'alert' => true,
                'event' => $event,
                'message' => "El evento en {$event['ubicacion']} con el formador {$event['formador']} finaliza en 10 minutos."
            ]);
            exit();
        }
    }

    echo json_encode(['alert' => false]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'alert' => false,
        'error' => $e->getMessage()
    ]);
}
?>