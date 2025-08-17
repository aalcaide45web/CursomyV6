# 🎓 Aplicación CursomyV6

## 📋 Descripción

**CursomyV6** es una plataforma educativa completa desarrollada en PHP que permite la gestión y distribución de cursos online. La aplicación está diseñada para instructores que desean crear, gestionar y vender contenido educativo de manera profesional.

## ✨ Características Principales

### 🎯 Gestión de Cursos
- Creación y edición de cursos con múltiples secciones
- Sistema de temáticas y categorías
- Gestión de recursos multimedia (videos, imágenes, documentos)
- Control de progreso del estudiante
- Sistema de notas y evaluaciones

### 👨‍🏫 Gestión de Instructores
- Panel de administración para instructores
- Gestión de perfiles y credenciales
- Sistema de permisos y roles
- Dashboard personalizado

### 📚 Sistema de Contenido
- Reproductor de video integrado
- Sistema de comentarios y feedback
- Gestión de recursos descargables
- Organización por secciones y lecciones

### 🔐 Sistema de Autenticación
- Login y registro de usuarios
- Recuperación de contraseñas con sistema temporal
- Gestión de sesiones seguras
- Perfiles de usuario personalizables

### 📊 Análisis y Reportes
- Seguimiento del progreso del estudiante
- Sistema de logs detallado
- Estadísticas de uso
- Reportes de actividad

## 🛠️ Requisitos del Sistema

### Servidor Web
- **Apache** 2.4+ (recomendado)
- **PHP** 7.4+ (recomendado PHP 8.0+)
- **MySQL** 5.7+ o **SQLite** 3.x

### Extensiones PHP Requeridas
- `mysqli` o `pdo_mysql`
- `gd` (para procesamiento de imágenes)
- `curl` (para funcionalidades de red)
- `json` (para APIs)
- `session` (para gestión de sesiones)
- `fileinfo` (para validación de archivos)

### Configuración PHP
- `upload_max_filesize`: 100M o superior
- `post_max_size`: 100M o superior
- `max_execution_time`: 300 segundos
- `memory_limit`: 256M o superior

## 🚀 Instalación

### 1. Clonar el Repositorio
```bash
git clone https://github.com/aalcaide45web/CursomyV6.git
cd CursomyV6
```

### 2. Configurar el Servidor Web
- Coloca el proyecto en tu directorio web (ej: `htdocs/` en XAMPP)
- Asegúrate de que Apache tenga permisos de escritura en la carpeta `uploads/`

### 3. Configurar la Base de Datos
- **Opción A: MySQL**
  - Crea una base de datos MySQL
  - Importa el esquema desde `database/cursosmy.sql` (si existe)
  - Configura las credenciales en `config/database.php`

- **Opción B: SQLite**
  - La aplicación puede usar SQLite por defecto
  - Asegúrate de que la carpeta `database/` tenga permisos de escritura

### 4. Configurar la Aplicación
- Edita `config/config.php` con la configuración de tu entorno
- Ajusta `config/upload-limits.php` según tus necesidades
- Verifica que `config/database.php` tenga la configuración correcta

### 5. Permisos de Archivos
```bash
# En sistemas Unix/Linux
chmod 755 uploads/
chmod 755 logs/
chmod 755 database/
```

### 6. Acceder a la Aplicación
- Abre tu navegador y ve a `http://localhost/CursomyV6`
- La aplicación debería cargar correctamente

## ⚙️ Configuración

### Archivos de Configuración Principales
- `config/config.php` - Configuración general de la aplicación
- `config/database.php` - Configuración de la base de datos
- `config/upload-limits.php` - Límites de subida de archivos
- `config/logger.php` - Configuración del sistema de logs

### Variables de Entorno Importantes
- `DB_HOST` - Host de la base de datos
- `DB_NAME` - Nombre de la base de datos
- `DB_USER` - Usuario de la base de datos
- `DB_PASS` - Contraseña de la base de datos
- `UPLOAD_PATH` - Ruta de la carpeta de uploads
- `LOG_PATH` - Ruta de la carpeta de logs

## 📁 Estructura del Proyecto

```
CursomyV6/
├── api/                    # APIs y endpoints
├── config/                 # Archivos de configuración
├── css/                    # Estilos CSS (Tailwind CSS)
├── database/               # Base de datos y esquemas
├── js/                     # JavaScript del frontend
├── logs/                   # Archivos de logs
├── uploads/                # Archivos subidos (ignorado por Git)
├── index.php              # Página principal
├── curso.php              # Vista de curso individual
├── reproductor.php        # Reproductor de video
└── README.md              # Este archivo
```

## 🔧 Solución de Problemas Comunes

### Error de Límites de Subida
Si tienes problemas con la subida de archivos grandes:
1. Verifica `php.ini` y aumenta `upload_max_filesize` y `post_max_size`
2. Revisa `config/upload-limits.php`
3. Ejecuta `fix-upload-limits.php` para verificar la configuración

### Problemas de Base de Datos
1. Verifica las credenciales en `config/database.php`
2. Asegúrate de que la base de datos esté creada
3. Verifica que el usuario tenga permisos suficientes

### Errores de Permisos
1. Verifica que Apache tenga permisos de escritura en `uploads/`
2. Revisa los permisos de `logs/` y `database/`
3. En Windows, ejecuta como administrador si es necesario

## 📝 Logs y Debugging

La aplicación incluye un sistema de logs completo:
- **Logs de aplicación**: `logs/`
- **Logs de API**: `api/logs/`
- **Visor de logs**: `logs-viewer.php`
- **Consola de debug**: `debug-console.php`

## 🔒 Seguridad

- La carpeta `uploads/` está completamente excluida del control de versiones
- Sistema de autenticación seguro con contraseñas temporales
- Validación de archivos subidos
- Protección contra ataques comunes

## 🤝 Contribución

Para contribuir al proyecto:
1. Haz un fork del repositorio
2. Crea una rama para tu feature (`git checkout -b feature/nueva-funcionalidad`)
3. Haz commit de tus cambios (`git commit -am 'Agregar nueva funcionalidad'`)
4. Haz push a la rama (`git push origin feature/nueva-funcionalidad`)
5. Abre un Pull Request

## 📄 Licencia

Este proyecto está bajo licencia MIT. Ver el archivo `LICENSE` para más detalles.

## 📞 Soporte

Si tienes problemas o preguntas:
- Revisa la documentación en este README
- Consulta los logs de la aplicación
- Abre un issue en GitHub
- Contacta al equipo de desarrollo

## 🚀 Roadmap

- [ ] Sistema de pagos integrado
- [ ] App móvil nativa
- [ ] Sistema de certificados
- [ ] Integración con LMS externos
- [ ] API pública para desarrolladores
- [ ] Sistema de notificaciones push

---

**Desarrollado con ❤️ por el equipo de CursomyV6**
