<?php
// Archivo de test para verificar la funcionalidad de upload
require_once 'config/database.php';
require_once 'config/config.php';

$db = getDatabase();

echo "<h1>Test de Upload - Diagnóstico</h1>";

// Verificar configuración PHP
echo "<h2>Configuración PHP:</h2>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";
echo "file_uploads: " . (ini_get('file_uploads') ? 'Habilitado' : 'Deshabilitado') . "<br>";

// Verificar directorios
echo "<h2>Directorios:</h2>";
echo "UPLOAD_DIR: " . UPLOAD_DIR . " - " . (is_dir(UPLOAD_DIR) ? 'Existe' : 'No existe') . " - " . (is_writable(UPLOAD_DIR) ? 'Escribible' : 'No escribible') . "<br>";
echo "VIDEOS_DIR: " . VIDEOS_DIR . " - " . (is_dir(VIDEOS_DIR) ? 'Existe' : 'No existe') . " - " . (is_writable(VIDEOS_DIR) ? 'Escribible' : 'No escribible') . "<br>";
echo "IMAGES_DIR: " . IMAGES_DIR . " - " . (is_dir(IMAGES_DIR) ? 'Existe' : 'No existe') . " - " . (is_writable(IMAGES_DIR) ? 'Escribible' : 'No escribible') . "<br>";

// Verificar base de datos
echo "<h2>Base de Datos:</h2>";
try {
    $cursos = $db->query("SELECT COUNT(*) as count FROM cursos")->fetch();
    echo "Cursos en BD: " . $cursos['count'] . "<br>";
    
    $secciones = $db->query("SELECT COUNT(*) as count FROM secciones")->fetch();
    echo "Secciones en BD: " . $secciones['count'] . "<br>";
    
    $clases = $db->query("SELECT COUNT(*) as count FROM clases")->fetch();
    echo "Clases en BD: " . $clases['count'] . "<br>";
    
    // Mostrar algunos cursos y secciones para test
    echo "<h3>Cursos disponibles:</h3>";
    $cursos = $db->query("SELECT * FROM cursos LIMIT 3")->fetchAll();
    foreach ($cursos as $curso) {
        echo "ID: " . $curso['id'] . " - " . $curso['titulo'] . "<br>";
        
        $secciones = $db->query("SELECT * FROM secciones WHERE curso_id = " . $curso['id'])->fetchAll();
        foreach ($secciones as $seccion) {
            echo "&nbsp;&nbsp;&nbsp;Sección ID: " . $seccion['id'] . " - " . $seccion['nombre'] . "<br>";
        }
    }
    
} catch (Exception $e) {
    echo "Error en BD: " . $e->getMessage() . "<br>";
}

// Verificar constantes
echo "<h2>Constantes:</h2>";
echo "MAX_VIDEO_SIZE: " . formatBytes(MAX_VIDEO_SIZE) . "<br>";
echo "ALLOWED_VIDEO_TYPES: " . implode(', ', ALLOWED_VIDEO_TYPES) . "<br>";

echo "<h2>Información del Servidor:</h2>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "<br>";

// Test simple de FormData
echo "<h2>Test de Upload Simple:</h2>";
?>
<form action="test-upload-handler.php" method="POST" enctype="multipart/form-data">
    <p>Curso ID: <input type="number" name="curso_id" value="1" required></p>
    <p>Sección ID: <input type="number" name="seccion_id" value="1" required></p>
    <p>Videos: <input type="file" name="videos[]" multiple accept=".mp4"></p>
    <p><button type="submit">Test Upload</button></p>
</form>

<h2>Enlaces útiles:</h2>
<a href="index.php">Volver al Dashboard</a><br>
<a href="api/upload-videos.php" target="_blank">API Upload Direct (debería dar error 405)</a><br>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1, h2, h3 { color: #333; }
form { background: #f5f5f5; padding: 15px; border-radius: 5px; }
</style>
