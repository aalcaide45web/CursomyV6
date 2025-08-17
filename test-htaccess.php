<?php
// Test para verificar si .htaccess está funcionando
echo "<h1>Test de .htaccess</h1>";

// Verificar configuración actual
echo "<h2>Configuración PHP Actual:</h2>";
echo "<p><strong>upload_max_filesize:</strong> " . ini_get('upload_max_filesize') . "</p>";
echo "<p><strong>post_max_size:</strong> " . ini_get('post_max_size') . "</p>";
echo "<p><strong>max_execution_time:</strong> " . ini_get('max_execution_time') . "</p>";
echo "<p><strong>memory_limit:</strong> " . ini_get('memory_limit') . "</p>";

// Verificar si .htaccess está siendo leído
echo "<h2>Verificación de .htaccess:</h2>";
if (ini_get('upload_max_filesize') == '512000M') {
    echo "<p style='color: green;'>✅ .htaccess está funcionando - Límites aumentados</p>";
} else {
    echo "<p style='color: red;'>❌ .htaccess NO está funcionando - Límites por defecto</p>";
}

// Mostrar información del servidor
echo "<h2>Información del Servidor:</h2>";
echo "<p><strong>Server Software:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido') . "</p>";
echo "<p><strong>Document Root:</strong> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Desconocido') . "</p>";

// Verificar si mod_rewrite está activo
echo "<h2>Módulos Apache:</h2>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    echo "<p><strong>mod_rewrite:</strong> " . (in_array('mod_rewrite', $modules) ? '✅ Activo' : '❌ Inactivo') . "</p>";
    echo "<p><strong>mod_php:</strong> " . (in_array('mod_php', $modules) ? '✅ Activo' : '❌ Inactivo') . "</p>";
} else {
    echo "<p>No se puede verificar módulos Apache desde PHP</p>";
}
?>
