<?php
// Archivo de diagnóstico para problemas de base de datos
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Base de Datos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c5282;
            margin-bottom: 30px;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            border-radius: 8px;
            border-left: 5px solid #2c5282;
        }
        .success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        .info {
            background: #d1ecf1;
            border-left-color: #17a2b8;
            color: #0c5460;
        }
        .warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #2c5282;
            color: white;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            background: #2c5282;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .btn:hover {
            background: #1a365d;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        pre {
            background: #1e1e1e;
            color: #00ff00;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .test-result {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            font-weight: bold;
        }
        .test-pass {
            background: #d4edda;
            color: #155724;
        }
        .test-fail {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔬 Diagnóstico Completo de Base de Datos</h1>
        
        <?php
        require_once __DIR__ . '/database.php';
        
        echo "<div class='section info'>";
        echo "<h2>📊 Información del Sistema</h2>";
        echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
        echo "<p><strong>SQLite Version:</strong> " . sqlite_libversion() . "</p>";
        echo "<p><strong>Sistema Operativo:</strong> " . PHP_OS . "</p>";
        echo "<p><strong>Directorio actual:</strong> " . __DIR__ . "</p>";
        echo "</div>";
        
        try {
            $db = new Database();
            $dbPath = $db->getDbPath();
            
            echo "<div class='section success'>";
            echo "<h2>✅ Conexión a Base de Datos</h2>";
            echo "<p><strong>Ruta de la base de datos:</strong> " . htmlspecialchars($dbPath) . "</p>";
            echo "<p><strong>Archivo existe:</strong> " . (file_exists($dbPath) ? 'SÍ ✅' : 'NO ❌') . "</p>";
            echo "<p><strong>Es escribible:</strong> " . (is_writable($dbPath) ? 'SÍ ✅' : 'NO ❌') . "</p>";
            echo "<p><strong>Permisos:</strong> " . substr(sprintf('%o', fileperms($dbPath)), -4) . "</p>";
            echo "<p><strong>Tamaño:</strong> " . number_format(filesize($dbPath)) . " bytes</p>";
            echo "</div>";
            
            // Contar eventos
            $totalEvents = $db->countEvents();
            echo "<div class='section info'>";
            echo "<h2>📈 Estadísticas de Eventos</h2>";
            echo "<p><strong>Total de eventos:</strong> $totalEvents</p>";
            echo "</div>";
            
            // Obtener todos los eventos
            $events = $db->getEvents();
            
            if (empty($events)) {
                echo "<div class='section warning'>";
                echo "<h2>⚠️ No hay eventos en la base de datos</h2>";
                echo "<p>La base de datos está vacía. Registra un evento para comenzar.</p>";
                echo "</div>";
            } else {
                echo "<div class='section info'>";
                echo "<h2>📋 Eventos Registrados</h2>";
                echo "<table>";
                echo "<tr><th>ID</th><th>Ubicación</th><th>Formador</th><th>Hora Inicio</th><th>Hora Fin</th><th>Creado</th><th>Acciones</th></tr>";
                
                foreach ($events as $event) {
                    echo "<tr>";
                    echo "<td><strong>{$event['id']}</strong></td>";
                    echo "<td>" . htmlspecialchars($event['ubicacion']) . "</td>";
                    echo "<td>" . htmlspecialchars($event['formador']) . "</td>";
                    echo "<td>" . htmlspecialchars($event['hora_inicio']) . "</td>";
                    echo "<td>" . htmlspecialchars($event['hora_fin']) . "</td>";
                    echo "<td>" . htmlspecialchars($event['created_at']) . "</td>";
                    echo "<td>
                            <a href='delete_event.php?id={$event['id']}' class='btn btn-danger' onclick='return confirm(\"¿Eliminar evento ID {$event['id']}?\")'>Eliminar</a>
                          </td>";
                    echo "</tr>";
                }
                
                echo "</table>";
                echo "</div>";
                
                // Prueba de eliminación
                echo "<div class='section'>";
                echo "<h2>🧪 Prueba de Eliminación</h2>";
                echo "<p>Selecciona un evento para probar la eliminación:</p>";
                echo "<form method='POST' action=''>";
                echo "<select name='test_id'>";
                foreach ($events as $event) {
                    echo "<option value='{$event['id']}'>ID {$event['id']}: {$event['ubicacion']} - {$event['formador']}</option>";
                }
                echo "</select>";
                echo "<button type='submit' class='btn btn-danger' onclick='return confirm(\"¿Realmente quieres probar eliminar este evento?\")'>🧪 Probar Eliminación</button>";
                echo "</form>";
                echo "</div>";
                
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_id'])) {
                    $testId = intval($_POST['test_id']);
                    echo "<div class='section info'>";
                    echo "<h3>Resultado de la prueba de eliminación (ID: $testId)</h3>";
                    
                    $result = $db->deleteEvent($testId);
                    
                    if ($result['success']) {
                        echo "<div class='test-result test-pass'>✅ " . htmlspecialchars($result['message']) . "</div>";
                        echo "<p><strong>ID eliminado:</strong> {$result['deleted_id']}</p>";
                        echo "<p><strong>Filas afectadas:</strong> {$result['rows_affected']}</p>";
                    } else {
                        echo "<div class='test-result test-fail'>❌ " . htmlspecialchars($result['message']) . "</div>";
                        if (isset($result['available_ids'])) {
                            echo "<p><strong>IDs disponibles:</strong> " . implode(', ', $result['available_ids']) . "</p>";
                        }
                    }
                    
                    echo "</div>";
                    
                    // Recargar página para actualizar la lista
                    echo "<meta http-equiv='refresh' content='3;url=debug_database.php'>";
                }
            }
            
            // Información de la base de datos
            echo "<div class='section info'>";
            echo "<h2>💾 Información Técnica de la Base de Datos</h2>";
            
            $dbInfo = $db->getDb()->query("PRAGMA database_list")->fetchAll();
            echo "<h3>Ubicaciones de la base de datos:</h3>";
            echo "<pre>";
            print_r($dbInfo);
            echo "</pre>";
            
            $tables = $db->getDb()->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll();
            echo "<h3>Tablas en la base de datos:</h3>";
            echo "<pre>";
            print_r($tables);
            echo "</pre>";
            
            $indexes = $db->getDb()->query("SELECT name, tbl_name FROM sqlite_master WHERE type='index'")->fetchAll();
            echo "<h3>Índices en la base de datos:</h3>";
            echo "<pre>";
            print_r($indexes);
            echo "</pre>";
            
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div class='section error'>";
            echo "<h2>❌ Error</h2>";
            echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>Archivo:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
            echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
            echo "<h3>Stack Trace:</h3>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            echo "</div>";
        }
        ?>
        
        <div class="section">
            <h2>🔧 Acciones</h2>
            <a href="index.html" class="btn">🏠 Volver al Inicio</a>
            <a href="register.html" class="btn">📝 Registrar Evento</a>
            <a href="events.html" class="btn">📋 Ver Eventos</a>
            <a href="debug_database.php" class="btn">🔄 Recargar Diagnóstico</a>
        </div>
    </div>
</body>
</html>