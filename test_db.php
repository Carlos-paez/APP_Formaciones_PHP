<?php
// Archivo de prueba para verificar la conexión a la base de datos
require_once 'database.php';

echo "<h1>🧪 Prueba de Conexión a Base de Datos</h1>";

try {
    $db = new Database();
    $dbPath = $db->getDbPath();

    echo "<p>✅ Base de datos creada en: " . htmlspecialchars($dbPath) . "</p>";

    // Verificar permisos
    if (file_exists($dbPath)) {
        echo "<p>✅ Archivo de base de datos existe</p>";
        echo "<p>📝 Permisos: " . substr(sprintf('%o', fileperms($dbPath)), -4) . "</p>";
    } else {
        echo "<p>❌ Archivo de base de datos NO existe</p>";
    }

    // Probar guardar un evento
    echo "<h2>📝 Probando guardar evento...</h2>";
    $result = $db->saveEvent('Sala de Prueba', 'Test Formador', '10:00', '11:00');
    echo "<p>" . ($result['success'] ? '✅' : '❌') . " " . htmlspecialchars($result['message']) . "</p>";

    // Probar obtener eventos
    echo "<h2>📋 Probando obtener eventos...</h2>";
    $events = $db->getEvents();
    echo "<p>✅ Eventos encontrados: " . count($events) . "</p>";

    if (count($events) > 0) {
        echo "<pre>";
        print_r($events);
        echo "</pre>";
    }

    echo "<h2 style='color: green;'>🎉 ¡Todo funciona correctamente!</h2>";
    echo "<p><a href='index.html'>← Volver a la aplicación</a></p>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</h2>";
    echo "<p><a href='index.html'>← Volver a la aplicación</a></p>";
}
?>