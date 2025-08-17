# 🔧 Cómo Aumentar los Límites de PHP para Upload

## 🚨 Problema Actual
El archivo `.htaccess` no está funcionando, por lo que los límites de PHP siguen siendo de 40MB.

## ✅ Soluciones (en orden de preferencia)

### **Opción 1: Editar php.ini (Recomendado)**

#### **Paso 1: Localizar php.ini**
```bash
# En línea de comandos
php --ini

# O crear un archivo PHP para verificar
<?php phpinfo(); ?>
```

#### **Paso 2: Editar php.ini**
Busca y modifica estas líneas:
```ini
; Tamaño máximo de archivo individual
upload_max_filesize = 1024M

; Tamaño máximo de datos POST
post_max_size = 1024M

; Tiempo máximo de ejecución (10 minutos)
max_execution_time = 600

; Límite de memoria
memory_limit = 512M

; Número máximo de archivos
max_file_uploads = 20

; Tamaño máximo de input
max_input_vars = 3000
```

#### **Paso 3: Reiniciar el servidor web**
```bash
# Apache
sudo systemctl restart apache2
# o
sudo service apache2 restart

# Nginx + PHP-FPM
sudo systemctl restart nginx
sudo systemctl restart php8.1-fpm
```

### **Opción 2: .user.ini (Si tienes hosting compartido)**

Crea un archivo `.user.ini` en el directorio raíz:
```ini
upload_max_filesize = 1024M
post_max_size = 1024M
max_execution_time = 600
memory_limit = 512M
max_file_uploads = 20
```

### **Opción 3: Configuración de hosting**

Si usas **XAMPP/WAMP/MAMP**:

#### **XAMPP:**
1. Ve a `C:\xampp\php\php.ini`
2. Edita los valores mencionados arriba
3. Reinicia Apache desde el panel de control

#### **WAMP:**
1. Click izquierdo en el icono de WAMP
2. PHP → php.ini
3. Edita los valores
4. Restart All Services

#### **MAMP:**
1. MAMP → Preferences → PHP
2. Edita el archivo php.ini
3. Reinicia los servidores

### **Opción 4: Hosting compartido / cPanel**

1. **cPanel:** Ve a "Select PHP Version" o "PHP Settings"
2. **Plesk:** Ve a "PHP Settings" 
3. Modifica:
   - `upload_max_filesize`
   - `post_max_size` 
   - `max_execution_time`
   - `memory_limit`

## 🔍 Verificar que Funciona

### **Método 1: Debug Console**
```
http://tu-servidor/cloneudemyv1/debug-console.php
```
Revisa la sección "CONFIGURACIÓN PHP"

### **Método 2: Archivo de prueba**
Crea `test-php-limits.php`:
```php
<?php
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";
?>
```

## 🎯 Valores Objetivo

Deberías ver:
- ✅ `upload_max_filesize: 1024M`
- ✅ `post_max_size: 1024M`
- ✅ `max_execution_time: 600`
- ✅ `memory_limit: 512M`

## 🚫 Si Nada Funciona

### **Alternativa: Comprimir Videos**

Usa **HandBrake** (gratuito):
1. Descarga: https://handbrake.fr/
2. Configuración recomendada:
   - **Preset:** "Fast 720p30"
   - **Video Codec:** H.264
   - **Quality:** RF 23-25
   - **Audio:** AAC 128kbps

### **Alternativa: FFmpeg (Línea de comandos)**
```bash
# Comprimir video manteniendo calidad
ffmpeg -i input.mp4 -c:v libx264 -crf 23 -preset medium -c:a aac -b:a 128k output.mp4

# Para archivos muy grandes
ffmpeg -i input.mp4 -vf scale=1280:720 -c:v libx264 -crf 25 -preset fast -c:a aac -b:a 96k output.mp4
```

## 📋 Checklist de Verificación

- [ ] Localizar archivo php.ini correcto
- [ ] Editar los 6 valores principales
- [ ] Reiniciar servidor web
- [ ] Verificar cambios en debug console
- [ ] Probar upload de archivo grande
- [ ] Si no funciona, contactar administrador del hosting

## 🆘 Soporte Específico por Servidor

### **Apache + mod_php**
- Editar php.ini y reiniciar Apache
- Verificar que mod_php esté habilitado

### **Nginx + PHP-FPM**
- Editar php.ini de PHP-FPM
- Reiniciar tanto Nginx como PHP-FPM
- Verificar configuración de Nginx para client_max_body_size

### **Hosting Compartido**
- Usar panel de control del hosting
- Contactar soporte técnico si no tienes acceso
- Considerar upgrade de plan si es necesario

---

**Una vez que aumentes los límites, podrás subir archivos de hasta 500GB sin problemas!** 🎉
