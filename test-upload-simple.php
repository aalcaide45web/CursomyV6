<?php
// Script simple para probar uploads
echo "<h1>🧪 Test Simple de Upload - CursosMy</h1>";

echo "<h2>📋 Configuración Actual de PHP:</h2>";
echo "<p><strong>upload_max_filesize:</strong> " . ini_get('upload_max_filesize') . "</p>";
echo "<p><strong>post_max_size:</strong> " . ini_get('post_max_size') . "</p>";
echo "<p><strong>max_execution_time:</strong> " . ini_get('max_execution_time') . "</p>";
echo "<p><strong>memory_limit:</strong> " . ini_get('memory_limit') . "</p>";

echo "<h2>🔧 Aplicando Límites Programáticamente:</h2>";
ini_set('upload_max_filesize', '512000M');
ini_set('post_max_size', '512000M');
ini_set('max_execution_time', '86400');
ini_set('memory_limit', '2048M');

echo "<p>✅ Límites aplicados programáticamente</p>";

echo "<h2>📋 Configuración Después de Cambios:</h2>";
echo "<p><strong>upload_max_filesize:</strong> " . ini_get('upload_max_filesize') . "</p>";
echo "<p><strong>post_max_size:</strong> " . ini_get('post_max_size') . "</p>";
echo "<p><strong>max_execution_time:</strong> " . ini_get('max_execution_time') . "</p>";
echo "<p><strong>memory_limit:</strong> " . ini_get('memory_limit') . "</p>";

echo "<h2>🧪 Test de Upload:</h2>";
echo "<form action='test-upload-handler.php' method='post' enctype='multipart/form-data'>";
echo "<p>Selecciona un archivo para probar:</p>";
echo "<input type='file' name='test_file' accept='*/*'>";
echo "<br><br>";
echo "<input type='submit' value='Probar Upload' style='padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;'>";
echo "</form>";

echo "<br><br>";
echo "<a href='index.php' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>🏠 Volver al Dashboard</a>";
?>
