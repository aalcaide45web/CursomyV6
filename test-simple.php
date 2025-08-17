<?php
echo "Test simple - Apache funcionando";
echo "<br>PHP version: " . phpversion();
echo "<br>Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido');
?>
