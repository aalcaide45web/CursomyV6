<?php
/**
 * Script para verificar y solucionar límites de upload en CursosMy
 * Ejecuta este archivo desde tu navegador para diagnosticar el problema
 */

// Activar modo debug
define('DEBUG_MODE', true);

// Incluir configuración de límites
require_once 'config/upload-limits.php';

echo "<!DOCTYPE html>";
echo "<html lang='es'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Diagnóstico de Límites de Upload - CursosMy</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }";
echo ".container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }";
echo ".success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }";
echo ".error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }";
echo ".warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }";
echo ".info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 4px; margin: 10px 0; }";
echo "table { width: 100%; border-collapse: collapse; margin: 20px 0; }";
echo "th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }";
echo "th { background-color: #f2f2f2; }";
echo ".btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }";
echo ".btn:hover { background: #0056b3; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<div class='container'>";
echo "<h1>🔧 Diagnóstico de Límites de Upload - CursosMy</h1>";

// Verificar configuración actual
echo "<h2>📊 Configuración Actual de PHP</h2>";
$currentLimits = getCurrentLimits();

echo "<table>";
echo "<tr><th>Configuración</th><th>Valor Actual</th><th>Estado</th></tr>";

$issues = [];

foreach ($currentLimits as $setting => $value) {
    $status = "✅ OK";
    $statusClass = "success";
    
    switch ($setting) {
        case 'upload_max_filesize':
            if ($value != '512000M') {
                $status = "❌ PROBLEMA";
                $statusClass = "error";
                $issues[] = "upload_max_filesize no está configurado correctamente";
            }
            break;
        case 'post_max_size':
            if ($value != '512000M') {
                $status = "❌ PROBLEMA";
                $statusClass = "error";
                $issues[] = "post_max_size no está configurado correctamente";
            }
            break;
        case 'max_execution_time':
            if ($value < 86400) {
                $status = "⚠️ BAJO";
                $statusClass = "warning";
                $issues[] = "max_execution_time es muy bajo para archivos grandes";
            }
            break;
        case 'memory_limit':
            if (return_bytes($value) < return_bytes('2048M')) {
                $status = "⚠️ BAJO";
                $statusClass = "warning";
                $issues[] = "memory_limit es muy bajo para archivos grandes";
            }
            break;
    }
    
    echo "<tr>";
    echo "<td><strong>$setting</strong></td>";
    echo "<td>$value</td>";
    echo "<td class='$statusClass'>$status</td>";
    echo "</tr>";
}

echo "</table>";

// Verificar archivo .htaccess
echo "<h2>📁 Verificación de .htaccess</h2>";
if (file_exists('.htaccess')) {
    echo "<div class='success'>✅ Archivo .htaccess encontrado</div>";
    
    $htaccessContent = file_get_contents('.htaccess');
    if (strpos($htaccessContent, 'php_value upload_max_filesize 512000M') !== false) {
        echo "<div class='success'>✅ Configuración de upload_max_filesize encontrada en .htaccess</div>";
    } else {
        echo "<div class='error'>❌ Configuración de upload_max_filesize NO encontrada en .htaccess</div>";
        $issues[] = ".htaccess no contiene la configuración correcta";
    }
} else {
    echo "<div class='error'>❌ Archivo .htaccess NO encontrado</div>";
    $issues[] = "No existe archivo .htaccess";
}

// Verificar información del servidor
echo "<h2>🖥️ Información del Servidor</h2>";
echo "<table>";
echo "<tr><th>Configuración</th><th>Valor</th></tr>";
echo "<tr><td>Server Software</td><td>" . ($_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido') . "</td></tr>";
echo "<tr><td>PHP Version</td><td>" . PHP_VERSION . "</td></tr>";
echo "<tr><td>Document Root</td><td>" . ($_SERVER['DOCUMENT_ROOT'] ?? 'Desconocido') . "</td></tr>";
echo "<tr><td>Script Path</td><td>" . __FILE__ . "</td></tr>";
echo "</table>";

// Mostrar problemas encontrados
if (!empty($issues)) {
    echo "<h2>🚨 Problemas Detectados</h2>";
    foreach ($issues as $issue) {
        echo "<div class='error'>❌ $issue</div>";
    }
    
    echo "<h2>🔧 Soluciones Recomendadas</h2>";
    echo "<div class='info'>";
    echo "<h3>Opción 1: Verificar .htaccess</h3>";
    echo "<p>1. Asegúrate de que el archivo .htaccess esté en el directorio raíz</p>";
    echo "<p>2. Verifica que Apache tenga AllowOverride All</p>";
    echo "<p>3. Reinicia Apache después de cambios</p>";
    
    echo "<h3>Opción 2: Configuración en httpd.conf</h3>";
    echo "<p>1. Abre C:\\xampp\\apache\\conf\\httpd.conf</p>";
    echo "<p>2. Busca la sección de tu directorio</p>";
    echo "<p>3. Agrega: AllowOverride All</p>";
    echo "<p>4. Reinicia Apache</p>";
    
    echo "<h3>Opción 3: Configuración PHP directa</h3>";
    echo "<p>1. Abre C:\\xampp\\php\\php.ini</p>";
    echo "<p>2. Busca y modifica:</p>";
    echo "<ul>";
    echo "<li>upload_max_filesize = 512000M</li>";
    echo "<li>post_max_size = 512000M</li>";
    echo "<li>max_execution_time = 86400</li>";
    echo "<li>memory_limit = 2048M</li>";
    echo "</ul>";
    echo "<p>3. Reinicia Apache</p>";
    echo "</div>";
    
    echo "<div class='warning'>";
    echo "<h3>⚠️ Importante</h3>";
    echo "<p>El archivo config/upload-limits.php ya está configurado para aplicar límites desde PHP.</p>";
    echo "<p>Si el .htaccess no funciona, este archivo debería solucionar el problema.</p>";
    echo "</div>";
} else {
    echo "<div class='success'>";
    echo "<h2>🎉 ¡Todo está configurado correctamente!</h2>";
    echo "<p>Los límites de upload están configurados correctamente y deberías poder subir archivos de hasta 500GB.</p>";
    echo "</div>";
}

// Botones de acción
echo "<h2>🚀 Acciones</h2>";
echo "<a href='test-htaccess.php' class='btn'>🧪 Probar .htaccess</a>";
echo "<a href='debug-console.php' class='btn'>🔍 Debug Console</a>";
echo "<a href='index.php' class='btn'>🏠 Volver al Dashboard</a>";

echo "</div>";
echo "</body>";
echo "</html>";
?>
