<?php
// Script de prueba para verificar configuración de Apache
echo "<h1>🧪 Test de Configuración de Apache</h1>";
echo "<h2>Configuración PHP Actual:</h2>";
echo "<p><strong>upload_max_filesize:</strong> " . ini_get('upload_max_filesize') . "</p>";
echo "<p><strong>post_max_size:</strong> " . ini_get('post_max_size') . "</p>";
echo "<p><strong>max_execution_time:</strong> " . ini_get('max_execution_time') . "</p>";
echo "<p><strong>memory_limit:</strong> " . ini_get('memory_limit') . "</p>";

echo "<h2>Verificación de Configuración:</h2>";
if (ini_get('upload_max_filesize') == '512000M') {
    echo "<p style='color: green;'>✅ Apache está aplicando la configuración del httpd.conf</p>";
} else {
    echo "<p style='color: red;'>❌ Apache NO está aplicando la configuración del httpd.conf</p>";
    echo "<p>Valor actual: " . ini_get('upload_max_filesize') . "</p>";
    echo "<p>Valor esperado: 512000M</p>";
}

echo "<h2>Información del Servidor:</h2>";
echo "<p><strong>Server Software:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido') . "</p>";
echo "<p><strong>Document Root:</strong> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Desconocido') . "</p>";
echo "<p><strong>Script Path:</strong> " . ($_SERVER['SCRIPT_NAME'] ?? 'Desconocido') . "</p>";

echo "<h2>Próximos Pasos:</h2>";
echo "<p>Si Apache NO está aplicando la configuración:</p>";
echo "<ol>";
echo "<li>Verifica que Apache se haya reiniciado después de los cambios</li>";
echo "<li>Verifica que no haya errores en los logs de Apache</li>";
echo "<li>Intenta usar el panel de control de XAMPP para reiniciar Apache</li>";
echo "</ol>";
?>
