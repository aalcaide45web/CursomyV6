# 🔧 Solución Definitiva para Límites de Upload en XAMPP

## 🚨 Problema Común en XAMPP
XAMPP tiene configuraciones específicas que pueden estar limitando la subida a 40MB incluso después de modificar `php.ini`.

## 📍 Paso 1: Ubicar el php.ini Correcto

### Opción A: Desde el Panel de Control de XAMPP
1. **Abrir XAMPP Control Panel**
2. **Click en "Config" junto a Apache**
3. **Seleccionar "PHP (php.ini)"**
4. Esto abrirá el archivo `php.ini` correcto que está usando Apache

### Opción B: Verificar desde la Web
1. **Crear archivo `phpinfo.php`** en `C:\xampp\htdocs\`:
```php
<?php phpinfo(); ?>
```
2. **Abrir en navegador:** `http://localhost/phpinfo.php`
3. **Buscar "Loaded Configuration File"** - esa es la ubicación correcta del `php.ini`

## ⚙️ Paso 2: Modificar php.ini Correctamente

Buscar y modificar estas líneas en el `php.ini` correcto:

```ini
; Aumentar límite de memoria
memory_limit = 512M

; Aumentar límite de subida por archivo
upload_max_filesize = 1024M

; Aumentar límite total de POST
post_max_size = 1024M

; Aumentar tiempo de ejecución
max_execution_time = 300

; Aumentar tiempo de entrada
max_input_time = 300

; Aumentar número máximo de archivos
max_file_uploads = 20
```

## 🔄 Paso 3: Reiniciar Apache en XAMPP

### ⚠️ CRÍTICO: Debes reiniciar Apache
1. **Abrir XAMPP Control Panel**
2. **Click en "Stop" junto a Apache**
3. **Esperar unos segundos**
4. **Click en "Start" junto a Apache**

## 🧪 Paso 4: Verificar Cambios

### Crear archivo de prueba `test-limits.php`:
```php
<?php
echo "<h2>Configuración PHP Actual:</h2>";
echo "<strong>upload_max_filesize:</strong> " . ini_get('upload_max_filesize') . "<br>";
echo "<strong>post_max_size:</strong> " . ini_get('post_max_size') . "<br>";
echo "<strong>memory_limit:</strong> " . ini_get('memory_limit') . "<br>";
echo "<strong>max_execution_time:</strong> " . ini_get('max_execution_time') . "<br>";
echo "<strong>max_input_time:</strong> " . ini_get('max_input_time') . "<br>";
?>
```

Abrir: `http://localhost/cloneUdemyV1/test-limits.php`

## 🛠️ Soluciones Alternativas si No Funciona

### 1. Verificar que no hay múltiples php.ini
XAMPP a veces tiene varios archivos `php.ini`. Ubicaciones comunes:
- `C:\xampp\php\php.ini` ← **Principal**
- `C:\xampp\apache\bin\php.ini`
- `C:\xampp\phpMyAdmin\php.ini`

### 2. Verificar Apache httpd.conf
Archivo: `C:\xampp\apache\conf\httpd.conf`
Buscar y modificar:
```apache
# Aumentar límite de request (500 GB)
# 500GB = 500 * 1024 * 1024 * 1024 = 536870912000 bytes
LimitRequestBody 536870912000
```

### 3. Crear .htaccess Local
En la carpeta del proyecto (`C:\xampp\htdocs\cloneUdemyV1\.htaccess`):
```apache
php_value upload_max_filesize 512000M
php_value post_max_size 512000M
php_value memory_limit 2048M
php_value max_execution_time 86400
php_value max_input_time 86400
```

## 🔍 Diagnóstico Avanzado

### Si sigue sin funcionar, verificar:

1. **Permisos de carpeta:**
   - La carpeta `uploads/` debe tener permisos de escritura

2. **Logs de error:**
   - Revisar: `C:\xampp\apache\logs\error.log`
   - Revisar: `C:\xampp\php\logs\php_error_log`

3. **Antivirus:**
   - Algunos antivirus bloquean archivos grandes
   - Agregar excepción para XAMPP

## 🎯 Comando Rápido para Verificar

Crear `check-config.php`:
```php
<?php
$configs = [
    'upload_max_filesize',
    'post_max_size', 
    'memory_limit',
    'max_execution_time',
    'max_input_time',
    'max_file_uploads'
];

echo "<h2>Configuración Actual de PHP:</h2>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Configuración</th><th>Valor Actual</th><th>Recomendado</th></tr>";

$recommended = [
    'upload_max_filesize' => '1024M',
    'post_max_size' => '1024M',
    'memory_limit' => '512M', 
    'max_execution_time' => '300',
    'max_input_time' => '300',
    'max_file_uploads' => '20'
];

foreach ($configs as $config) {
    $current = ini_get($config);
    $rec = $recommended[$config];
    $color = ($current === $rec) ? 'green' : 'red';
    echo "<tr>";
    echo "<td>$config</td>";
    echo "<td style='color: $color;'>$current</td>";
    echo "<td>$rec</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Archivo php.ini en uso:</h3>";
echo php_ini_loaded_file();
?>
```

## 🚀 Pasos Resumidos

1. ✅ Abrir XAMPP Control Panel → Config → PHP (php.ini)
2. ✅ Modificar valores (upload_max_filesize = 1024M, etc.)
3. ✅ Guardar archivo
4. ✅ **REINICIAR Apache en XAMPP**
5. ✅ Verificar con archivo de prueba
6. ✅ Probar subida en la aplicación

**¡El reinicio de Apache es FUNDAMENTAL!** 🔄

