// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
});

function setupEventListeners() {
    // Forms
    document.getElementById('instructorForm').addEventListener('submit', handleInstructorSubmit);
    document.getElementById('tematicaForm').addEventListener('submit', handleTematicaSubmit);
}

// FUNCIONES DE INSTRUCTORES

function openInstructorModal(instructorData = null) {
    const modal = document.getElementById('instructorModal');
    const title = document.getElementById('instructorModalTitle');
    const form = document.getElementById('instructorForm');
    
    if (instructorData) {
        // Modo edición
        title.innerHTML = '<i class="fas fa-user-tie mr-2"></i>Editar Instructor';
        document.getElementById('instructorId').value = instructorData.id;
        document.getElementById('instructorNombre').value = instructorData.nombre;
        document.getElementById('instructorEmail').value = instructorData.email || '';
        document.getElementById('instructorBio').value = instructorData.bio || '';
    } else {
        // Modo creación
        title.innerHTML = '<i class="fas fa-user-tie mr-2"></i>Nuevo Instructor';
        form.reset();
        document.getElementById('instructorId').value = '';
    }
    
    modal.classList.remove('hidden');
}

function closeInstructorModal() {
    document.getElementById('instructorModal').classList.add('hidden');
    document.getElementById('instructorForm').reset();
}

async function handleInstructorSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const instructorId = formData.get('id');
    
    const data = {
        nombre: formData.get('nombre'),
        email: formData.get('email'),
        bio: formData.get('bio')
    };
    
    if (instructorId) {
        data.id = instructorId;
    }
    
    try {
        const method = instructorId ? 'PUT' : 'POST';
        const response = await fetch('api/instructores.php', {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(
                instructorId ? 'Instructor actualizado exitosamente' : 'Instructor creado exitosamente', 
                'success'
            );
            closeInstructorModal();
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(result.message || 'Error al guardar el instructor', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

async function editInstructor(instructorId) {
    try {
        const response = await fetch(`api/instructores.php?id=${instructorId}`);
        const result = await response.json();
        
        if (result.success) {
            openInstructorModal(result.data);
        } else {
            showNotification('Error al cargar los datos del instructor', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

async function deleteInstructor(instructorId) {
    if (!confirm('¿Estás seguro de que quieres eliminar este instructor?')) {
        return;
    }
    
    try {
        const response = await fetch('api/instructores.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: instructorId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Instructor eliminado exitosamente', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(result.message || 'Error al eliminar el instructor', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

// FUNCIONES DE TEMÁTICAS

function openTematicaModal(tematicaData = null) {
    const modal = document.getElementById('tematicaModal');
    const title = document.getElementById('tematicaModalTitle');
    const form = document.getElementById('tematicaForm');
    
    if (tematicaData) {
        // Modo edición
        title.innerHTML = '<i class="fas fa-tags mr-2"></i>Editar Temática';
        document.getElementById('tematicaId').value = tematicaData.id;
        document.getElementById('tematicaNombre').value = tematicaData.nombre;
        document.getElementById('tematicaDescripcion').value = tematicaData.descripcion || '';
    } else {
        // Modo creación
        title.innerHTML = '<i class="fas fa-tags mr-2"></i>Nueva Temática';
        form.reset();
        document.getElementById('tematicaId').value = '';
    }
    
    modal.classList.remove('hidden');
}

function closeTematicaModal() {
    document.getElementById('tematicaModal').classList.add('hidden');
    document.getElementById('tematicaForm').reset();
}

async function handleTematicaSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const tematicaId = formData.get('id');
    
    const data = {
        nombre: formData.get('nombre'),
        descripcion: formData.get('descripcion')
    };
    
    if (tematicaId) {
        data.id = tematicaId;
    }
    
    try {
        const method = tematicaId ? 'PUT' : 'POST';
        const response = await fetch('api/tematicas.php', {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(
                tematicaId ? 'Temática actualizada exitosamente' : 'Temática creada exitosamente', 
                'success'
            );
            closeTematicaModal();
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(result.message || 'Error al guardar la temática', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

async function editTematica(tematicaId) {
    try {
        const response = await fetch(`api/tematicas.php?id=${tematicaId}`);
        const result = await response.json();
        
        if (result.success) {
            openTematicaModal(result.data);
        } else {
            showNotification('Error al cargar los datos de la temática', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

async function deleteTematica(tematicaId) {
    if (!confirm('¿Estás seguro de que quieres eliminar esta temática?')) {
        return;
    }
    
    try {
        const response = await fetch('api/tematicas.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: tematicaId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Temática eliminada exitosamente', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(result.message || 'Error al eliminar la temática', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

// Sistema de notificaciones (reutilizado del dashboard)
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

// Cerrar modales con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeInstructorModal();
        closeTematicaModal();
    }
});
