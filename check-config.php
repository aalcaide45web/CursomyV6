<?php
$configs = [
    'upload_max_filesize',
    'post_max_size', 
    'memory_limit',
    'max_execution_time',
    'max_input_time',
    'max_file_uploads'
];

echo "<!DOCTYPE html>";
echo "<html><head><title>Verificación de Configuración PHP</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #1a1a1a; color: white; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #444; padding: 12px; text-align: left; }
    th { background: #333; }
    .good { color: #4CAF50; font-weight: bold; }
    .bad { color: #f44336; font-weight: bold; }
    .info { background: #2196F3; color: white; padding: 10px; margin: 10px 0; border-radius: 5px; }
    .warning { background: #FF9800; color: white; padding: 10px; margin: 10px 0; border-radius: 5px; }
    .success { background: #4CAF50; color: white; padding: 10px; margin: 10px 0; border-radius: 5px; }
</style></head><body>";

echo "<h1>🔧 Verificación de Configuración PHP para XAMPP</h1>";

echo "<div class='info'>";
echo "<strong>📍 Archivo php.ini en uso:</strong><br>";
echo php_ini_loaded_file() ?: 'No se pudo determinar';
echo "</div>";

echo "<h2>📊 Configuración Actual vs Recomendada:</h2>";
echo "<table>";
echo "<tr><th>Configuración</th><th>Valor Actual</th><th>Recomendado</th><th>Estado</th></tr>";

$recommended = [
    'upload_max_filesize' => '1024M',
    'post_max_size' => '1024M',
    'memory_limit' => '512M', 
    'max_execution_time' => '300',
    'max_input_time' => '300',
    'max_file_uploads' => '20'
];

$allGood = true;

foreach ($configs as $config) {
    $current = ini_get($config);
    $rec = $recommended[$config];
    
    // Convertir a bytes para comparación
    $currentBytes = convertToBytes($current);
    $recBytes = convertToBytes($rec);
    
    $isGood = false;
    if (in_array($config, ['upload_max_filesize', 'post_max_size', 'memory_limit'])) {
        $isGood = $currentBytes >= $recBytes;
    } else {
        $isGood = intval($current) >= intval($rec);
    }
    
    if (!$isGood) $allGood = false;
    
    $statusClass = $isGood ? 'good' : 'bad';
    $statusText = $isGood ? '✅ OK' : '❌ NECESITA CAMBIO';
    
    echo "<tr>";
    echo "<td><strong>$config</strong></td>";
    echo "<td class='$statusClass'>$current</td>";
    echo "<td>$rec</td>";
    echo "<td class='$statusClass'>$statusText</td>";
    echo "</tr>";
}
echo "</table>";

if ($allGood) {
    echo "<div class='success'>";
    echo "<h3>🎉 ¡Configuración Correcta!</h3>";
    echo "Todos los valores están configurados correctamente. Si sigues teniendo problemas, verifica:";
    echo "<ul>";
    echo "<li>Que hayas reiniciado Apache en XAMPP</li>";
    echo "<li>Que no haya archivos .htaccess conflictivos</li>";
    echo "<li>Los logs de error de Apache</li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div class='warning'>";
    echo "<h3>⚠️ Configuración Necesita Cambios</h3>";
    echo "Algunos valores necesitan ser modificados en el archivo php.ini mostrado arriba.";
    echo "<br><strong>¡No olvides reiniciar Apache después de hacer cambios!</strong>";
    echo "</div>";
}

echo "<h2>🧪 Prueba de Subida de Archivos:</h2>";
echo "<div class='info'>";
echo "Límite teórico máximo de archivo: <strong>" . ini_get('upload_max_filesize') . "</strong><br>";
echo "Límite teórico de POST: <strong>" . ini_get('post_max_size') . "</strong><br>";
$maxBytes = min(convertToBytes(ini_get('upload_max_filesize')), convertToBytes(ini_get('post_max_size')));
echo "Límite real efectivo: <strong>" . formatBytes($maxBytes) . "</strong>";
echo "</div>";

echo "<h2>📋 Información del Sistema:</h2>";
echo "<table>";
echo "<tr><td><strong>Versión PHP</strong></td><td>" . phpversion() . "</td></tr>";
echo "<tr><td><strong>Sistema Operativo</strong></td><td>" . php_uname() . "</td></tr>";
echo "<tr><td><strong>Servidor Web</strong></td><td>" . $_SERVER['SERVER_SOFTWARE'] . "</td></tr>";
echo "<tr><td><strong>Directorio de trabajo</strong></td><td>" . getcwd() . "</td></tr>";
echo "</table>";

echo "<h2>🔍 Pasos Siguientes:</h2>";
echo "<div class='info'>";
if (!$allGood) {
    echo "<ol>";
    echo "<li><strong>Abrir XAMPP Control Panel</strong></li>";
    echo "<li><strong>Click en 'Config' junto a Apache → PHP (php.ini)</strong></li>";
    echo "<li><strong>Modificar los valores marcados en rojo arriba</strong></li>";
    echo "<li><strong>Guardar el archivo</strong></li>";
    echo "<li><strong>En XAMPP: Stop Apache → Start Apache</strong></li>";
    echo "<li><strong>Recargar esta página para verificar</strong></li>";
    echo "</ol>";
} else {
    echo "<p>✅ La configuración está correcta. Si sigues teniendo problemas:</p>";
    echo "<ol>";
    echo "<li>Verifica que Apache se haya reiniciado correctamente</li>";
    echo "<li>Revisa los logs de error: C:\\xampp\\apache\\logs\\error.log</li>";
    echo "<li>Prueba subir un archivo desde tu aplicación</li>";
    echo "</ol>";
}
echo "</div>";

echo "<div style='margin-top: 30px; text-align: center; color: #888;'>";
echo "Generado el " . date('Y-m-d H:i:s');
echo "</div>";

echo "</body></html>";

function convertToBytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int) $val;
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>

