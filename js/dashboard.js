// Variables globales
let allCourses = [];

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
    setupEventListeners();
    setupFolderImport();
});

function initializeDashboard() {
    // Obtener todas las tarjetas de cursos
    allCourses = Array.from(document.querySelectorAll('.course-card'));
    
    // Configurar filtros
    setupFilters();
}

function setupEventListeners() {
    // Búsqueda en tiempo real
    document.getElementById('searchInput').addEventListener('input', filterCourses);
    
    // Filtros
    document.getElementById('instructorFilter').addEventListener('change', filterCourses);
    document.getElementById('tematicaFilter').addEventListener('change', filterCourses);
    document.getElementById('sortBy').addEventListener('change', sortCourses);
    
    // Form de crear curso
    document.getElementById('createCourseForm').addEventListener('submit', handleCreateCourse);
}

// Importación por carpetas (sin comprimir) usando webkitdirectory
function setupFolderImport() {
    const folderInput = document.getElementById('folderPicker');
    if (!folderInput) return;

    folderInput.addEventListener('change', async function () {
        const files = Array.from(folderInput.files || []);
        if (files.length === 0) return;
        if (window.prepareFolderImport) {
            window.prepareFolderImport(files);
        }
        folderInput.value = '';
    });
}

function setupFilters() {
    // Configurar filtros iniciales si es necesario
}

function filterCourses() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const instructorFilter = document.getElementById('instructorFilter').value;
    const tematicaFilter = document.getElementById('tematicaFilter').value;
    
    allCourses.forEach(card => {
        const titulo = card.dataset.titulo;
        const instructor = card.dataset.instructor;
        const tematica = card.dataset.tematica;
        
        let show = true;
        
        // Filtro de búsqueda
        if (searchTerm && !titulo.includes(searchTerm)) {
            show = false;
        }
        
        // Filtro de instructor
        if (instructorFilter && instructor !== instructorFilter) {
            show = false;
        }
        
        // Filtro de temática
        if (tematicaFilter && tematica !== tematicaFilter) {
            show = false;
        }
        
        card.style.display = show ? 'block' : 'none';
    });
}

function sortCourses() {
    const sortBy = document.getElementById('sortBy').value;
    const container = document.getElementById('coursesGrid');
    
    const sortedCourses = Array.from(allCourses).sort((a, b) => {
        switch (sortBy) {
            case 'titulo':
                return a.dataset.titulo.localeCompare(b.dataset.titulo);
            case 'titulo_desc':
                return b.dataset.titulo.localeCompare(a.dataset.titulo);
            case 'duracion':
                return parseInt(b.dataset.duracion || 0) - parseInt(a.dataset.duracion || 0);
            default: // created_at
                return 0; // Mantener orden original
        }
    });
    
    // Reordenar elementos en el DOM
    sortedCourses.forEach(card => {
        container.appendChild(card);
    });
    
    // Actualizar referencia
    allCourses = sortedCourses;
}

// Funciones del modal
function openCreateCourseModal() {
    document.getElementById('createCourseModal').classList.remove('hidden');
}

function closeCreateCourseModal() {
    document.getElementById('createCourseModal').classList.add('hidden');
    document.getElementById('createCourseForm').reset();
}

// Manejar creación de curso
async function handleCreateCourse(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch('api/cursos.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Curso creado exitosamente', 'success');
            closeCreateCourseModal();
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(result.message || 'Error al crear el curso', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

// Editar curso
function editCourse(courseId) {
    window.location.href = `curso.php?id=${courseId}`;
}

// Eliminar curso
async function deleteCourse(courseId) {
    if (!confirm('¿Estás seguro de que quieres eliminar este curso? Esta acción no se puede deshacer.')) {
        return;
    }
    
    const deleteVideos = confirm('¿También quieres eliminar todos los videos asociados a este curso?');
    
    // Preguntar si quiere activar debug
    const enableDebug = confirm('¿Activar modo debug para ver detalles en consola (F12)?');
    
    if (enableDebug) {
        console.group(`🔍 DEBUG: Eliminando curso ${courseId}`);
        console.log('📋 Configuración:', { courseId, deleteVideos, debug: true });
    }
    
    try {
        const response = await fetch('api/cursos.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: courseId,
                deleteVideos: deleteVideos,
                debug: enableDebug
            })
        });
        
        const result = await response.json();
        
        if (enableDebug) {
            console.log('📡 Respuesta del servidor:', result);
            
            if (result.debug) {
                console.group('🔧 Información de debug del servidor:');
                result.debug.forEach(info => {
                    console.log(info);
                });
                console.groupEnd();
            }
        }
        
        if (result.success) {
            if (enableDebug) {
                console.log('✅ Eliminación exitosa');
                console.groupEnd();
            }
            showNotification('Curso eliminado exitosamente', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            if (enableDebug) {
                console.error('❌ Error en eliminación:', result.message);
                console.groupEnd();
            }
            showNotification(result.message || 'Error al eliminar el curso', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

// Sistema de notificaciones
function showNotification(message, type = 'info') {
    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg text-white transition-all duration-300 transform translate-x-full`;
    
    // Aplicar estilo según el tipo
    switch (type) {
        case 'success':
            notification.classList.add('bg-green-600');
            break;
        case 'error':
            notification.classList.add('bg-red-600');
            break;
        case 'warning':
            notification.classList.add('bg-yellow-600');
            break;
        default:
            notification.classList.add('bg-blue-600');
    }
    
    notification.innerHTML = `
        <div class="flex items-center">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animar entrada
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
    // Auto-remover después de 5 segundos
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 5000);
}

// Cerrar modal con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCreateCourseModal();
    }
});
