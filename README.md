# 🎓 CursomyV6 - Reproductor Personal de Cursos

## ⚠️ **IMPORTANTE: LIMITACIONES DE SUBIDA**

### 🚫 **NO SUBIR VIDEOS DESDE MÚLTIPLES PESTAÑAS**
- **Solo una pestaña activa** para subida de videos
- **Cerrar todas las demás pestañas** antes de subir
- **Esperar a que termine** la subida de un curso antes de subir otro
- **⚠️⚠️⚠️⚠️Si en la subida de Carpeta, la ruta de esta, es muy larga, dará errores, a veces los titulos de los videos son muy largos** Cambiarlo a una ruta mas corta o reducir el largo de los nombres.⚠️⚠️⚠️⚠️

### 🔒 **SUBIDA SECUENCIAL OBLIGATORIA**
- **Un curso a la vez** por limitaciones de PHP
- **No subir videos simultáneamente** desde diferentes navegadores
- **Esperar confirmación** de finalización antes de continuar

---

## 📋 Descripción

**CursomyV6** es una **aplicación personal** para gestionar y reproducir tus propios cursos de video. No es una plataforma de distribución online, sino un **reproductor avanzado** que te permite organizar, estudiar y tomar notas de tus cursos de manera profesional.

### 🎯 **¿Para qué sirve?**
- **Reproducir tus cursos** con un reproductor avanzado
- **Organizar contenido** por secciones y lecciones
- **Tomar notas con timestamp** durante la reproducción
- **Agregar comentarios** a cada clase
- **Buscar contenido** rápidamente
- **Gestionar progreso** de visualización

### 🚫 **¿Para qué NO sirve?**
- ❌ Distribuir cursos online
- ❌ Compartir contenido con otros usuarios
- ❌ Sistema de pagos o suscripciones
- ❌ Plataforma multi-usuario

---

## ✨ Características Principales

### 🎥 **Reproductor Avanzado**
- **Guardado automático de progreso** - retoma donde lo dejaste
- **Sistema de notas con timestamp** - crea notas en momentos específicos
- **Comentarios por clase** - agrega comentarios que se guardan permanentemente
- **Navegación entre clases** de la misma sección
- **Atajos de teclado** (Espacio, flechas, M para mute, F para fullscreen)
- **Marcadores visuales** de notas en la barra de progreso

### 📚 **Gestión de Contenido**
- **Organización por secciones** y lecciones
- **Sistema de búsqueda** en títulos y contenido
- **Categorización por temáticas** e instructores
- **Gestión de recursos** (videos, imágenes, documentos)

### 🔍 **Sistema de Búsqueda y Notas**
- **Búsqueda en tiempo real** por título, instructor o temática
- **Notas con timestamp** que te llevan al momento exacto del video
- **Comentarios organizados** por clase y sección
- **Historial de progreso** detallado

### 🎨 **Interfaz Moderna**
- **Diseño glassmorphism** con efectos de cristal
- **Modo oscuro** optimizado para visualización prolongada
- **Responsive** para móviles, tablets y desktop
- **Tailwind CSS** para diseño consistente

---

## 🛠️ Requisitos del Sistema

### Servidor Web
- **Apache** 2.4+ (recomendado)
- **PHP** 7.4+ (recomendado PHP 8.0+)
- **SQLite** 3.x (incluido por defecto)

### Extensiones PHP Requeridas
- `sqlite3` o `pdo_sqlite`
- `gd` (para procesamiento de imágenes)
- `curl` (para funcionalidades de red)
- `json` (para APIs)
- `session` (para gestión de sesiones)
- `fileinfo` (para validación de archivos)

### Configuración PHP Crítica
- `upload_max_filesize`: 100M o superior
- `post_max_size`: 100M o superior
- `max_execution_time`: 300 segundos
- `memory_limit`: 256M o superior

---

## 🚀 Instalación

### 1. Clonar el Repositorio
```bash
git clone https://github.com/aalcaide45web/CursomyV6.git
cd CursomyV6
```

### 2. Configurar el Servidor Web
- Coloca el proyecto en tu directorio web (ej: `htdocs/` en XAMPP)
- **Asegúrate de que Apache tenga permisos de escritura** en la carpeta `uploads/`

### 3. Configurar la Aplicación
- Edita `config/config.php` con la configuración de tu entorno
- Ajusta `config/upload-limits.php` según tus necesidades
- Verifica que `config/database.php` tenga la configuración correcta

### 4. Permisos de Archivos
```bash
# En sistemas Unix/Linux
chmod 755 uploads/
chmod 755 logs/
chmod 755 database/
```

### 5. Acceder a la Aplicación
- Abre tu navegador y ve a `http://localhost/CursomyV6`
- La aplicación debería cargar correctamente

---

## ⚠️ **USO CORRECTO - LECTURA OBLIGATORIA**

### 🚫 **PROHIBIDO - NO HACER**
- ❌ **NO abrir múltiples pestañas** para subir videos
- ❌ **NO subir videos simultáneamente** desde diferentes navegadores
- ❌ **NO interrumpir** una subida en curso
- ❌ **NO cerrar el navegador** durante la subida

### ✅ **CORRECTO - HACER ASÍ**
- ✅ **Cerrar todas las pestañas** antes de subir
- ✅ **Subir UN curso a la vez**
- ✅ **Esperar confirmación** de finalización
- ✅ **Usar solo una pestaña** activa para subidas

### 🔄 **Proceso Recomendado**
1. **Cierra todas las pestañas** del navegador
2. **Abre solo una pestaña** con la aplicación
3. **Sube un curso completo** (espera a que termine)
4. **Confirma que terminó** la subida
5. **Ahora puedes subir** el siguiente curso

---

## 📁 Estructura del Proyecto

```
CursomyV6/
├── api/                    # APIs y endpoints
├── config/                 # Archivos de configuración
├── css/                    # Estilos CSS (Tailwind CSS)
├── database/               # Base de datos SQLite
├── js/                     # JavaScript del frontend
├── logs/                   # Archivos de logs
├── uploads/                # Archivos subidos (ignorado por Git)
├── index.php              # Dashboard principal
├── curso.php              # Gestión de curso individual
├── reproductor.php        # Reproductor de video
└── README.md              # Este archivo
```

---

## 🔧 Solución de Problemas Comunes

### Error de Límites de Subida
Si tienes problemas con la subida de archivos grandes:
1. Verifica `php.ini` y aumenta `upload_max_filesize` y `post_max_size`
2. Revisa `config/upload-limits.php`
3. Ejecuta `fix-upload-limits.php` para verificar la configuración

### Problemas de Base de Datos
1. Verifica que la carpeta `database/` tenga permisos de escritura
2. La base de datos SQLite se crea automáticamente
3. Verifica que PHP tenga la extensión SQLite habilitada

### Errores de Permisos
1. Verifica que Apache tenga permisos de escritura en `uploads/`
2. Revisa los permisos de `logs/` y `database/`
3. En Windows, ejecuta como administrador si es necesario

### Problemas de Subida Múltiple
1. **Cierra todas las pestañas** del navegador
2. **Reinicia el navegador** si es necesario
3. **Usa solo una pestaña** para subidas
4. **Espera a que termine** antes de subir otro curso

---

## 📝 Logs y Debugging

La aplicación incluye un sistema de logs completo:
- **Logs de aplicación**: `logs/`
- **Logs de API**: `api/logs/`
- **Visor de logs**: `logs-viewer.php`
- **Consola de debug**: `debug-console.php`

---

## 🔒 Seguridad

- La carpeta `uploads/` está completamente excluida del control de versiones
- Sistema de autenticación local
- Validación de archivos subidos
- Protección contra ataques comunes

---

## 🤝 Contribución

Para contribuir al proyecto:
1. Haz un fork del repositorio
2. Crea una rama para tu feature (`git checkout -b feature/nueva-funcionalidad`)
3. Haz commit de tus cambios (`git commit -am 'Agregar nueva funcionalidad'`)
4. Haz push a la rama (`git push origin feature/nueva-funcionalidad`)
5. Abre un Pull Request

---

## 📄 Licencia

Este proyecto está bajo licencia MIT. Ver el archivo `LICENSE` para más detalles.

---

## 🚀 Roadmap

- [ ] Mejoras en el reproductor de video
- [ ] Sistema de marcadores avanzados
- [ ] Exportación de notas y comentarios
- [ ] Integración con servicios de almacenamiento en la nube
- [ ] App móvil para visualización
- [ ] Sistema de respaldo automático

---

## 📞 Soporte

Si tienes problemas o preguntas:
- **Primero**: Revisa esta documentación completa
- **Segundo**: Consulta los logs de la aplicación
- **Tercero**: Abre un issue en GitHub
- **Cuarto**: Contacta al equipo de desarrollo

---

**⚠️ RECUERDA: Esta es una aplicación PERSONAL. NO subas videos desde múltiples pestañas. UN curso a la vez. ⚠️**

**Desarrollado con ❤️ para uso personal y educativo**
