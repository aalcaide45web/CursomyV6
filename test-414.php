<?php
echo "<h1>🔍 Diagnóstico del Error 414 - Request-URI Too Long</h1>";

echo "<h2>📋 Información del Servidor:</h2>";
echo "<p><strong>Server Software:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido') . "</p>";
echo "<p><strong>Document Root:</strong> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Desconocido') . "</p>";
echo "<p><strong>Script Path:</strong> " . ($_SERVER['SCRIPT_NAME'] ?? 'Desconocido') . "</p>";
echo "<p><strong>Request URI:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'Desconocido') . "</p>";
echo "<p><strong>Query String:</strong> " . ($_SERVER['QUERY_STRING'] ?? 'Ninguna') . "</p>";

echo "<h2>🔧 Configuración de PHP:</h2>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>upload_max_filesize:</strong> " . ini_get('upload_max_filesize') . "</p>";
echo "<p><strong>post_max_size:</strong> " . ini_get('post_max_size') . "</p>";
echo "<p><strong>max_execution_time:</strong> " . ini_get('max_execution_time') . "</p>";
echo "<p><strong>memory_limit:</strong> " . ini_get('memory_limit') . "</p>";

echo "<h2>🧪 Test de URLs:</h2>";
echo "<p>Prueba estas URLs para verificar si el problema persiste:</p>";
echo "<ul>";
echo "<li><a href='test-simple.php' target='_blank'>test-simple.php</a> - Script básico</li>";
echo "<li><a href='index.php' target='_blank'>index.php</a> - Página principal</li>";
echo "<li><a href='debug-console.php' target='_blank'>debug-console.php</a> - Consola de debug</li>";
echo "</ul>";

echo "<h2>📝 Próximos Pasos:</h2>";
echo "<p>1. Haz clic en los enlaces de arriba para probar diferentes URLs</p>";
echo "<p>2. Si alguna URL da error 414, el problema persiste</p>";
echo "<p>3. Si todas funcionan, el problema del 414 está solucionado</p>";

echo "<br><br>";
echo "<a href='index.php' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>🏠 Volver al Dashboard</a>";
?>
