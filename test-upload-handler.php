<?php
// Handler para test de upload - verificar si el error 413 se ha solucionado
echo "<h1>🧪 Resultado del Test de Upload</h1>";

// Verificar si se recibió un archivo
if (!isset($_FILES['test_file']) || $_FILES['test_file']['error'] !== UPLOAD_ERR_OK) {
    $error = $_FILES['test_file']['error'] ?? 'No se recibió archivo';
    
    echo "<h2>❌ Error en el Upload:</h2>";
    echo "<p><strong>Código de error:</strong> $error</p>";
    
    switch ($error) {
        case UPLOAD_ERR_INI_SIZE:
            echo "<p style='color: red;'>El archivo excede upload_max_filesize en php.ini</p>";
            break;
        case UPLOAD_ERR_FORM_SIZE:
            echo "<p style='color: red;'>El archivo excede MAX_FILE_SIZE en el formulario HTML</p>";
            break;
        case UPLOAD_ERR_PARTIAL:
            echo "<p style='color: red;'>El archivo se subió parcialmente</p>";
            break;
        case UPLOAD_ERR_NO_FILE:
            echo "<p style='color: red;'>No se subió ningún archivo</p>";
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            echo "<p style='color: red;'>Falta la carpeta temporal</p>";
            break;
        case UPLOAD_ERR_CANT_WRITE:
            echo "<p style='color: red;'>Error al escribir el archivo en disco</p>";
            break;
        case UPLOAD_ERR_EXTENSION:
            echo "<p style='color: red;'>Una extensión de PHP detuvo la subida del archivo</p>";
            break;
        default:
            echo "<p style='color: red;'>Error desconocido</p>";
    }
} else {
    $file = $_FILES['test_file'];
    
    echo "<h2>✅ Upload Exitoso!</h2>";
    echo "<p><strong>Nombre del archivo:</strong> " . htmlspecialchars($file['name']) . "</p>";
    echo "<p><strong>Tamaño:</strong> " . formatBytes($file['size']) . "</p>";
    echo "<p><strong>Tipo MIME:</strong> " . htmlspecialchars($file['type']) . "</p>";
    echo "<p><strong>Archivo temporal:</strong> " . htmlspecialchars($file['tmp_name']) . "</p>";
    
    echo "<h2>🎉 ¡El Error 413 se ha solucionado!</h2>";
    echo "<p>Apache ya no está bloqueando archivos grandes.</p>";
    echo "<p>Los límites están configurados correctamente:</p>";
    echo "<ul>";
    echo "<li>upload_max_filesize: " . ini_get('upload_max_filesize') . "</li>";
    echo "<li>post_max_size: " . ini_get('post_max_size') . "</li>";
    echo "<li>max_execution_time: " . ini_get('max_execution_time') . "</li>";
    echo "<li>memory_limit: " . ini_get('memory_limit') . "</li>";
    echo "</ul>";
}

// Función para formatear bytes (si no está definida)
if (!function_exists('formatBytes')) {
    function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

echo "<br><br>";
echo "<a href='test-upload-413.php' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;'>🔄 Probar Otro Archivo</a>";
echo "<a href='index.php' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>🏠 Volver al Dashboard</a>";
?>
