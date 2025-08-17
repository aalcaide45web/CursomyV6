// Variables globales
let video;
let progressBar;
let progressFilled;
let playPauseBtn;
let timeDisplay;
let volumeBtn;
let volumeSlider;
let fullscreenBtn;
let speedBtn;
let speedMenu;
let qualityBtn;
let qualityMenu;
let progressSaveTimer;
let currentSpeed = 1;
let currentQuality = 'auto';

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    initializePlayer();
    setupEventListeners();
    setupNoteMarkers();
    // Autoplay: solo si está habilitado y en escritorio. En móviles/tablets evitar autoplay.
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    const shouldAutoplay = !isMobile && video && typeof video.play === 'function' && (typeof AUTOPLAY_ENABLED !== 'undefined' ? AUTOPLAY_ENABLED : true);
    
    if (shouldAutoplay) {
        const tryPlay = () => {
            const p = video.play();
            if (p && typeof p.catch === 'function') {
                p.catch(() => {
                    document.addEventListener('click', () => video.play(), { once: true });
                });
            }
        };
        if (video.readyState >= 1) {
            tryPlay();
        } else {
            video.addEventListener('loadedmetadata', tryPlay, { once: true });
        }
    }
});

function initializePlayer() {
    video = document.getElementById('videoPlayer');
    progressBar = document.getElementById('progressBar');
    progressFilled = document.getElementById('progressFilled');
    playPauseBtn = document.getElementById('playPauseBtn');
    timeDisplay = document.getElementById('timeDisplay');
    volumeBtn = document.getElementById('volumeBtn');
    volumeSlider = document.getElementById('volumeSlider');
    fullscreenBtn = document.getElementById('fullscreenBtn');
    speedBtn = document.getElementById('speedBtn');
    speedMenu = document.getElementById('speedMenu');
    qualityBtn = document.getElementById('qualityBtn');
    qualityMenu = document.getElementById('qualityMenu');
    
    // Remover atributo controls del video para evitar dobles controles
    video.removeAttribute('controls');
    
    // Configurar tiempo de inicio si hay progreso guardado
    if (START_TIME > 0) {
        video.addEventListener('loadedmetadata', function() {
            video.currentTime = START_TIME;
        });
    }
}

function setupEventListeners() {
    // Eventos del video
    video.addEventListener('play', updatePlayPauseButton);
    video.addEventListener('pause', updatePlayPauseButton);
    video.addEventListener('timeupdate', updateProgress);
    video.addEventListener('loadedmetadata', updateDuration);
    video.addEventListener('volumechange', updateVolumeButton);
    
    // Controles personalizados
    playPauseBtn.addEventListener('click', togglePlayPause);
    video.addEventListener('click', togglePlayPause); // Click en video para play/pause
    progressBar.addEventListener('click', seek);
    volumeBtn.addEventListener('click', toggleMute);
    volumeSlider.addEventListener('input', updateVolume);
    // En móvil: mostrar overlay vertical al tocar el botón de volumen
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    if (isMobile) {
        volumeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const overlay = document.getElementById('mobileVolumeOverlay');
            if (!overlay) return;
            overlay.classList.toggle('hidden');
            const mSlider = document.getElementById('mobileVolumeSlider');
            if (mSlider) {
                mSlider.value = Math.round((video.muted ? 0 : video.volume) * 100);
                mSlider.addEventListener('input', () => {
                    video.volume = mSlider.value / 100;
                    video.muted = false;
                    updateVolumeButton();
                });
            }
        });
        document.addEventListener('click', (e) => {
            const overlay = document.getElementById('mobileVolumeOverlay');
            if (!overlay) return;
            if (!overlay.contains(e.target) && e.target !== volumeBtn) {
                overlay.classList.add('hidden');
            }
        });
    }
    fullscreenBtn.addEventListener('click', toggleFullscreen);
    
    // Controles de velocidad
    speedBtn.addEventListener('click', toggleSpeedMenu);
    document.querySelectorAll('.speed-option').forEach(option => {
        option.addEventListener('click', changeSpeed);
    });
    
    // Controles de calidad
    qualityBtn.addEventListener('click', toggleQualityMenu);
    document.querySelectorAll('.quality-option').forEach(option => {
        option.addEventListener('click', changeQuality);
    });
    
    // Inicializar botón de atajos de teclado
    const keyboardShortcutsBtn = document.getElementById('keyboardShortcutsBtn');
    if (keyboardShortcutsBtn) keyboardShortcutsBtn.addEventListener('click', openKeyboardShortcutsModal);
    
    // Cerrar menús al hacer click fuera
    document.addEventListener('click', function(e) {
        if (!speedBtn.contains(e.target) && !speedMenu.contains(e.target)) {
            closeDropdownMenu(speedMenu);
        }
        if (!qualityBtn.contains(e.target) && !qualityMenu.contains(e.target)) {
            closeDropdownMenu(qualityMenu);
        }
    });
    
    // Guardar progreso cada 5 segundos
    video.addEventListener('timeupdate', function() {
        clearTimeout(progressSaveTimer);
        progressSaveTimer = setTimeout(saveProgress, 5000);
    });

    // Al terminar el video: cuenta atrás 5s para ir a la siguiente clase
    video.addEventListener('ended', handleVideoEnded);

    // Intentar exponer botón de persistencia si no hay duración previa
    // Oculto por petición: no mostrar botón "Guardar duración"
    
    // Formularios
    document.getElementById('noteForm').addEventListener('submit', handleNoteSubmit);
    document.getElementById('commentForm').addEventListener('submit', handleCommentSubmit);
    // Buscadores
    const notesSearch = document.getElementById('notesSearch');
    if (notesSearch) {
        notesSearch.addEventListener('input', () => filterList('notesList', notesSearch.value));
    }
    const classesSearch = document.getElementById('classesSearch');
    if (classesSearch) {
        classesSearch.addEventListener('input', () => filterList('classesList', classesSearch.value));
    }
    const commentsSearch = document.getElementById('commentsSearch');
    if (commentsSearch) {
        commentsSearch.addEventListener('input', () => filterList('commentsList', commentsSearch.value));
    }
    
    // Atajos de teclado
    document.addEventListener('keydown', handleKeyboard);
}

async function persistExactDuration() {
    const seconds = Math.floor(video.duration || 0);
    if (!seconds) return;
    try {
        await fetch('api/clases.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: CLASE_ID, duracion: seconds })
        });
        showNotification('Duración guardada', 'success');
        const disp = document.getElementById('timeDisplay');
        if (disp) {
            const parts = disp.textContent.split('/');
            if (parts.length === 2) {
                disp.textContent = parts[0].trim() + ' / ' + formatTime(seconds);
            }
        }
    } catch (e) {
        showNotification('Error guardando duración', 'error');
    }
}

function filterList(listId, term) {
    term = (term || '').toLowerCase();
    const list = document.getElementById(listId);
    if (!list) return;
    let items = [];
    if (listId === 'notesList' || listId === 'commentsList') {
        // Filtrar tarjetas (hijos directos div)
        items = Array.from(list.children).filter(el => el.tagName === 'DIV');
    } else if (listId === 'classesList') {
        items = Array.from(list.querySelectorAll('a'));
    }
    items.forEach(el => {
        const text = el.textContent.toLowerCase();
        el.style.display = text.includes(term) ? '' : 'none';
    });
}

// Cuenta atrás y salto a la siguiente clase
let nextCountdownTimer = null;
function handleVideoEnded() {
    // Buscar botón/URL de siguiente clase
    const nextLink = document.querySelector('.border-t a[href*="reproductor.php?clase="]:last-child');
    if (!nextLink) return; // no hay siguiente

    // Crear/mostrar overlay de cuenta atrás
    let overlay = document.getElementById('nextCountdownOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'nextCountdownOverlay';
        overlay.className = 'absolute inset-0 flex items-center justify-center';
        overlay.innerHTML = `
            <div class="glass-dark rounded-lg border border-white/20 p-6 text-center shadow-xl bg-black/70">
                <div class="text-white text-xl mb-3">Reproducción automática en <span id="countdownValue">5</span>s</div>
                <div class="flex gap-2 justify-center">
                    <a id="goNextNow" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded cursor-pointer">Ir ahora</a>
                    <button id="cancelCountdown" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">Cancelar</button>
                </div>
            </div>`;
        const container = document.querySelector('.video-container');
        container.appendChild(overlay);
        document.getElementById('goNextNow').addEventListener('click', () => {
            window.location.href = nextLink.getAttribute('href');
        });
        document.getElementById('cancelCountdown').addEventListener('click', () => {
            if (nextCountdownTimer) clearInterval(nextCountdownTimer);
            overlay.remove();
        });
    }

    let counter = 5;
    document.getElementById('countdownValue').textContent = String(counter);
    nextCountdownTimer = setInterval(() => {
        counter -= 1;
        if (counter <= 0) {
            clearInterval(nextCountdownTimer);
            window.location.href = nextLink.getAttribute('href');
        } else {
            document.getElementById('countdownValue').textContent = String(counter);
        }
    }, 1000);
}

function setupNoteMarkers() {
    const noteMarkers = document.querySelectorAll('.note-marker');
    const videoDuration = video.duration || 1;
    
    noteMarkers.forEach(marker => {
        const time = parseInt(marker.dataset.time);
        const percentage = (time / videoDuration) * 100;
        marker.style.left = percentage + '%';
        
        marker.addEventListener('click', function() {
            seekToTime(time);
        });
    });
    
    // Actualizar posiciones cuando se carguen los metadatos
    video.addEventListener('loadedmetadata', function() {
        setupNoteMarkers();
    });
}

// CONTROLES DE VIDEO

function togglePlayPause(e) {
    // Si el click es en el video, mostrar indicador visual
    if (e && e.target === video) {
        showVideoClickIndicator();
    }
    
    if (video.paused) {
        video.play();
    } else {
        video.pause();
    }
}

function showVideoClickIndicator() {
    const indicator = document.getElementById('videoClickIndicator');
    if (indicator) {
        // Cambiar icono según el estado actual (antes del cambio)
        const icon = indicator.querySelector('i');
        if (video.paused) {
            icon.className = 'fas fa-play';
        } else {
            icon.className = 'fas fa-pause';
        }
        
        // Mostrar indicador brevemente
        indicator.style.opacity = '0.8';
        setTimeout(() => {
            indicator.style.opacity = '0';
        }, 500);
    }
}

function updatePlayPauseButton() {
    const icon = playPauseBtn.querySelector('i');
    if (video.paused) {
        icon.className = 'fas fa-play text-lg';
    } else {
        icon.className = 'fas fa-pause text-lg';
    }
}

function updateProgress() {
    if (video.duration) {
        const percentage = (video.currentTime / video.duration) * 100;
        progressFilled.style.width = percentage + '%';
        
        const currentTime = formatTime(video.currentTime);
        const duration = formatTime(video.duration);
        timeDisplay.textContent = currentTime + ' / ' + duration;
    }
}

function updateDuration() {
    if (video.duration) {
        const duration = formatTime(video.duration);
        timeDisplay.textContent = '00:00 / ' + duration;
        // Exponer duración exacta al contexto global para uso en otras pantallas
        try {
            window.__VIDEO_EXACT_DURATION__ = Math.floor(video.duration);
        } catch (e) {}
    }
}

function seek(e) {
    const rect = progressBar.getBoundingClientRect();
    const percentage = (e.clientX - rect.left) / rect.width;
    const time = percentage * video.duration;
    video.currentTime = time;
}

function seekToTime(time) {
    video.currentTime = time;
    video.play();
}

function toggleMute() {
    video.muted = !video.muted;
    updateVolumeSlider();
}

function updateVolume() {
    const volume = volumeSlider.value / 100;
    video.volume = volume;
    video.muted = false;
    updateVolumeButton();
}

function updateVolumeButton() {
    const icon = volumeBtn.querySelector('i');
    const volume = video.muted ? 0 : video.volume;
    
    if (volume === 0) {
        icon.className = 'fas fa-volume-mute';
    } else if (volume < 0.5) {
        icon.className = 'fas fa-volume-down';
    } else {
        icon.className = 'fas fa-volume-up';
    }
}

function updateVolumeSlider() {
    const volume = video.muted ? 0 : video.volume * 100;
    volumeSlider.value = volume;
    
    // Actualizar el color del slider
    const percentage = volume;
    volumeSlider.style.background = `linear-gradient(to right, #8b5cf6 0%, #8b5cf6 ${percentage}%, #4b5563 ${percentage}%, #4b5563 100%)`;
}

function toggleFullscreen() {
    const videoContainer = document.querySelector('.video-container');
    
    if (!document.fullscreenElement) {
        videoContainer.requestFullscreen().catch(err => {
            console.log('Error entering fullscreen:', err);
        });
        fullscreenBtn.querySelector('i').className = 'fas fa-compress';
    } else {
        document.exitFullscreen();
        fullscreenBtn.querySelector('i').className = 'fas fa-expand';
    }
}

// FUNCIONES AUXILIARES PARA DROPDOWNS

function openDropdownMenu(menu) {
    // Limpiar estilos previos para usar CSS puro
    menu.style.position = '';
    menu.style.top = '';
    menu.style.bottom = '';
    menu.style.left = '';
    menu.style.right = '';
    
    menu.classList.remove('hidden');
    // Pequeño delay para que la animación CSS funcione
    setTimeout(() => {
        menu.style.pointerEvents = 'auto';
    }, 50);
}

function closeDropdownMenu(menu) {
    menu.style.pointerEvents = 'none';
    // Usar timeout para permitir que la animación termine
    setTimeout(() => {
        menu.classList.add('hidden');
    }, 150);
}

function closeAllDropdowns() {
    closeDropdownMenu(speedMenu);
    closeDropdownMenu(qualityMenu);
}

// FUNCIONES DE VELOCIDAD

function toggleSpeedMenu() {
    const isHidden = speedMenu.classList.contains('hidden');
    
    // Cerrar el menú de calidad primero
    closeDropdownMenu(qualityMenu);
    
    if (isHidden) {
        openDropdownMenu(speedMenu);
    } else {
        closeDropdownMenu(speedMenu);
    }
}

function changeSpeed(e) {
    const speed = parseFloat(e.target.dataset.speed);
    currentSpeed = speed;
    video.playbackRate = speed;
    speedBtn.innerHTML = `<i class="fas fa-tachometer-alt mr-1"></i>${speed}x`;
    
    // Actualizar estilos de selección
    document.querySelectorAll('.speed-option').forEach(option => {
        option.classList.remove('bg-purple-600');
    });
    e.target.classList.add('bg-purple-600');
    
    closeDropdownMenu(speedMenu);
    
    console.log(`🎬 [DEBUG] Velocidad cambiada a: ${speed}x`);
}

function decreaseSpeed() {
    const speeds = [0.25, 0.5, 0.75, 1, 1.25, 1.5, 2, 3, 5, 10];
    const currentIndex = speeds.indexOf(currentSpeed);
    
    if (currentIndex > 0) {
        const newSpeed = speeds[currentIndex - 1];
        setSpeed(newSpeed);
        showNotification(`Velocidad: ${newSpeed}x`, 'info');
    }
}

function increaseSpeed() {
    const speeds = [0.25, 0.5, 0.75, 1, 1.25, 1.5, 2, 3, 5, 10];
    const currentIndex = speeds.indexOf(currentSpeed);
    
    if (currentIndex < speeds.length - 1) {
        const newSpeed = speeds[currentIndex + 1];
        setSpeed(newSpeed);
        showNotification(`Velocidad: ${newSpeed}x`, 'info');
    }
}

function resetSpeed() {
    setSpeed(1);
    showNotification('Velocidad normal: 1x', 'info');
}

function setSpeed(speed) {
    currentSpeed = speed;
    video.playbackRate = speed;
    speedBtn.innerHTML = `<i class="fas fa-tachometer-alt mr-1"></i>${speed}x`;
    
    // Actualizar estilos de selección
    document.querySelectorAll('.speed-option').forEach(option => {
        option.classList.remove('bg-purple-600');
        if (parseFloat(option.dataset.speed) === speed) {
            option.classList.add('bg-purple-600');
        }
    });
}

// FUNCIONES DE CALIDAD

function toggleQualityMenu() {
    const isHidden = qualityMenu.classList.contains('hidden');
    
    // Cerrar el menú de velocidad primero
    closeDropdownMenu(speedMenu);
    
    if (isHidden) {
        openDropdownMenu(qualityMenu);
    } else {
        closeDropdownMenu(qualityMenu);
    }
}

function changeQuality(e) {
    const quality = e.target.dataset.quality;
    currentQuality = quality;
    
    const displayText = quality === 'auto' ? 'Auto' : quality;
    qualityBtn.innerHTML = `<i class="fas fa-cog mr-1"></i>${displayText}`;
    
    // Actualizar estilos de selección
    document.querySelectorAll('.quality-option').forEach(option => {
        option.classList.remove('bg-purple-600');
    });
    e.target.classList.add('bg-purple-600');
    
    closeDropdownMenu(qualityMenu);
    
    // Nota: La calidad real se implementaría con múltiples fuentes de video
    console.log(`📺 [DEBUG] Calidad cambiada a: ${quality}`);
    
    // Por ahora solo mostramos un mensaje informativo
    if (quality !== 'auto') {
        showNotification(`Calidad cambiada a ${quality} (simulado - requiere múltiples archivos de video)`, 'info');
    }
}

function handleKeyboard(e) {
    // Solo procesar si no estamos en un input
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
        return;
    }
    
    switch (e.code) {
        case 'Space':
            e.preventDefault();
            togglePlayPause();
            break;
        case 'ArrowLeft':
            e.preventDefault();
            video.currentTime = Math.max(0, video.currentTime - 10);
            break;
        case 'ArrowRight':
            e.preventDefault();
            video.currentTime = Math.min(video.duration, video.currentTime + 10);
            break;
        case 'KeyM':
            e.preventDefault();
            toggleMute();
            break;
        case 'KeyF':
            e.preventDefault();
            toggleFullscreen();
            break;
        case 'Minus':
        case 'NumpadSubtract':
            e.preventDefault();
            decreaseSpeed();
            break;
        case 'Equal':
        case 'NumpadAdd':
            e.preventDefault();
            increaseSpeed();
            break;
        case 'KeyR':
            e.preventDefault();
            resetSpeed();
            break;
        case 'Escape':
            e.preventDefault();
            closeAllDropdowns();
            closeKeyboardShortcutsModal();
            break;
    }
}

// FUNCIONES DE PROGRESO

async function saveProgress() {
    if (!video.duration) return;
    
    const data = {
        clase_id: CLASE_ID,
        tiempo_visto: Math.floor(video.currentTime),
        completada: video.currentTime >= video.duration * 0.9 // 90% completado
    };
    
    try {
        await fetch('api/progreso.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
    } catch (error) {
        console.error('Error saving progress:', error);
    }
}

// FUNCIONES DE NOTAS

function openNotesModal() {
    const currentTime = Math.floor(video.currentTime);
    document.getElementById('noteTime').value = formatTime(currentTime);
    document.getElementById('noteTime').dataset.seconds = currentTime;
    document.getElementById('notesModal').classList.remove('hidden');
}

function closeNotesModal() {
    document.getElementById('notesModal').classList.add('hidden');
    document.getElementById('noteForm').reset();
}

async function handleNoteSubmit(e) {
    e.preventDefault();
    
    const timeSeconds = parseInt(document.getElementById('noteTime').dataset.seconds);
    const content = document.getElementById('noteContent').value.trim();
    
    if (!content) {
        showNotification('El contenido de la nota es obligatorio', 'warning');
        return;
    }
    
    const data = {
        clase_id: CLASE_ID,
        tiempo_video: timeSeconds,
        contenido_nota: content
    };
    
    try {
        const response = await fetch('api/notas.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Nota agregada exitosamente', 'success');
            closeNotesModal();
            
            // Agregar nota dinámicamente sin recargar
            addNoteToList(result.nota);
            addNoteMarker(result.nota);
            
        } else {
            showNotification(result.message || 'Error al agregar la nota', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

async function deleteNote(noteId) {
    if (!confirm('¿Estás seguro de que quieres eliminar esta nota?')) {
        return;
    }
    
    try {
        const response = await fetch('api/notas.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: noteId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Nota eliminada exitosamente', 'success');
            
            // Remover nota dinámicamente sin recargar
            removeNoteFromList(noteId);
            removeNoteMarker(noteId);
            
        } else {
            showNotification(result.message || 'Error al eliminar la nota', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

// FUNCIONES DE COMENTARIOS

async function handleCommentSubmit(e) {
    e.preventDefault();
    
    const commentText = document.getElementById('commentText').value.trim();
    
    if (!commentText) {
        showNotification('El comentario no puede estar vacío', 'warning');
        return;
    }
    
    const data = {
        clase_id: CLASE_ID,
        comentario: commentText
    };
    
    try {
        const response = await fetch('api/comentarios.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Comentario agregado exitosamente', 'success');
            document.getElementById('commentText').value = '';
            prependCommentToList(result.data ? result.data : { id: result.id, comentario: commentText, created_at: new Date().toISOString() });
        } else {
            showNotification(result.message || 'Error al agregar el comentario', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

async function deleteComment(commentId) {
    if (!confirm('¿Estás seguro de que quieres eliminar este comentario?')) {
        return;
    }
    
    try {
        const response = await fetch('api/comentarios.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: commentId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Comentario eliminado exitosamente', 'success');
            removeCommentFromList(commentId);
        } else {
            showNotification(result.message || 'Error al eliminar el comentario', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

// FUNCIONES DE EDICIÓN

async function saveChanges() {
    if (!EDIT_MODE) return;
    
    const data = {
        id: CLASE_ID,
        titulo: document.getElementById('editTitulo').value.trim(),
        seccion_id: parseInt(document.getElementById('editSeccion').value),
        orden: parseInt(document.getElementById('editOrden').value),
        duracion: parseInt(document.getElementById('editDuracion').value) || 0
    };
    
    if (!data.titulo) {
        showNotification('El título es obligatorio', 'warning');
        return;
    }
    
    try {
        const response = await fetch('api/clases.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Clase actualizada exitosamente', 'success');
            setTimeout(() => {
                window.location.href = `reproductor.php?clase=${CLASE_ID}`;
            }, 1000);
        } else {
            showNotification(result.message || 'Error al actualizar la clase', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

// UTILIDADES

function formatTime(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    
    if (hours > 0) {
        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    } else {
        return `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
}

// Sistema de notificaciones
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg text-white transition-all duration-300 transform translate-x-full`;
    
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
    
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
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
        closeNotesModal();
    }
});

// Eliminar recurso desde reproductor
async function deleteResourcePlayer(id) {
    if (!confirm('¿Eliminar este recurso?')) return;
    try {
        const resp = await fetch('api/recursos.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const json = await resp.json();
        if (!json.success) throw new Error(json.message || 'Error');
        // Eliminar card del DOM
        const btn = document.querySelector(`#resourcesList button[onclick="deleteResourcePlayer(${id})"]`);
        if (btn) {
            const card = btn.closest('div');
            if (card) card.remove();
        }
        showNotification('Recurso eliminado', 'success');
    } catch (e) {
        showNotification('No se pudo eliminar el recurso', 'error');
    }
}

// Limpiar timer al salir de la página
window.addEventListener('beforeunload', function() {
    saveProgress();
});

// Inicialización adicional al cargar
document.addEventListener('DOMContentLoaded', function() {
    // Listener para eventos de fullscreen
    document.addEventListener('fullscreenchange', function() {
        const icon = fullscreenBtn.querySelector('i');
        if (document.fullscreenElement) {
            icon.className = 'fas fa-compress';
        } else {
            icon.className = 'fas fa-expand';
        }
    });
    
    // Inicializar slider de volumen cuando el video esté listo
    video.addEventListener('loadedmetadata', function() {
        updateVolumeSlider();
    });
    
    // Configurar modal de atajos de teclado
    const keyboardModal = document.getElementById('keyboardShortcutsModal');
    if (keyboardModal) {
        keyboardModal.addEventListener('click', function(e) {
            if (e.target === keyboardModal) {
                closeKeyboardShortcutsModal();
            }
        });
    }
});

// FUNCIONES PARA MODAL DE ATAJOS DE TECLADO

function openKeyboardShortcutsModal() {
    const modal = document.getElementById('keyboardShortcutsModal');
    if (modal) {
        modal.classList.remove('hidden');
        // Cerrar todos los dropdowns primero
        closeAllDropdowns();
        console.log('⌨️ [DEBUG] Modal de atajos de teclado abierto');
    }
}

function closeKeyboardShortcutsModal() {
    const modal = document.getElementById('keyboardShortcutsModal');
    if (modal) {
        modal.classList.add('hidden');
        console.log('⌨️ [DEBUG] Modal de atajos de teclado cerrado');
    }
}

// FUNCIONES PARA MANEJO DINÁMICO DE NOTAS

function addNoteToList(nota) {
    const notesList = document.getElementById('notesList');
    
    // Si no hay notas, remover el mensaje de "no hay notas"
    const noNotesMsg = notesList.querySelector('p');
    if (noNotesMsg && noNotesMsg.textContent.includes('No hay notas')) {
        noNotesMsg.remove();
    }
    
    // Crear elemento de nota (igual al HTML generado por PHP)
    const noteElement = document.createElement('div');
    noteElement.className = 'bg-black/20 rounded-lg p-3 border border-white/10';
    noteElement.dataset.noteId = nota.id;
    
    const timeFormatted = formatTime(nota.tiempo_video);
    
    noteElement.innerHTML = `
        <div class="flex justify-between items-start mb-2">
            <button onclick="seekToTime(${nota.tiempo_video})" 
                    class="text-yellow-400 hover:text-yellow-300 text-sm font-medium">
                ${timeFormatted}
            </button>
                    <div class="flex gap-2">
                        <button onclick="editNote(${nota.id})" class="text-blue-400 hover:text-blue-300"><i class="fas fa-edit text-xs"></i></button>
                        <button onclick="deleteNote(${nota.id})" class="text-red-400 hover:text-red-300"><i class="fas fa-trash text-xs"></i></button>
                    </div>
        </div>
        <p class="text-gray-300 text-sm" data-note-content>${nota.contenido_nota.replace(/\n/g, '<br>')}</p>
    `;
    
    // Insertar la nota en orden cronológico
    const existingNotes = notesList.querySelectorAll('div[data-note-id]');
    let inserted = false;
    
    for (let i = 0; i < existingNotes.length; i++) {
        const existingButton = existingNotes[i].querySelector('button[onclick*="seekToTime"]');
        if (existingButton) {
            const existingTime = parseInt(existingButton.getAttribute('onclick').match(/\d+/)[0]);
            if (nota.tiempo_video < existingTime) {
                notesList.insertBefore(noteElement, existingNotes[i]);
                inserted = true;
                break;
            }
        }
    }
    
    if (!inserted) {
        notesList.appendChild(noteElement);
    }
    
    console.log(`📝 [DEBUG] Nota agregada a la lista: ${timeFormatted}`);
}

function addNoteMarker(nota) {
    const progressBar = document.getElementById('progressBar');
    const videoDuration = video.duration || 1;
    const percentage = (nota.tiempo_video / videoDuration) * 100;
    
    // Crear marcador de nota
    const marker = document.createElement('div');
    marker.className = 'note-marker';
    marker.style.left = percentage + '%';
    marker.dataset.time = nota.tiempo_video;
    marker.dataset.content = nota.contenido_nota;
    marker.dataset.noteId = nota.id;
    marker.title = nota.contenido_nota;
    
    // Agregar evento click
    marker.addEventListener('click', function() {
        seekToTime(nota.tiempo_video);
    });
    
    progressBar.appendChild(marker);
    
    console.log(`🎯 [DEBUG] Marcador agregado en: ${percentage.toFixed(2)}%`);
}

function removeNoteFromList(noteId) {
    const noteElement = document.querySelector(`[data-note-id="${noteId}"]`);
    if (noteElement) {
        noteElement.remove();
        console.log(`🗑️ [DEBUG] Nota eliminada de la lista: ID ${noteId}`);
        
        // Si no quedan notas, mostrar mensaje
        const notesList = document.getElementById('notesList');
        const remainingNotes = notesList.querySelectorAll('div[data-note-id]');
        if (remainingNotes.length === 0) {
            const p = document.createElement('p');
            p.className = 'text-gray-400 text-sm text-center py-4';
            p.textContent = 'No hay notas';
            notesList.appendChild(p);
        }
    }
}

function removeNoteMarker(noteId) {
    const marker = document.querySelector(`[data-note-id="${noteId}"]`);
    if (marker) {
        marker.remove();
        console.log(`🎯 [DEBUG] Marcador eliminado: ID ${noteId}`);
    }
}

// Edición inline de notas
async function editNote(noteId) {
    const container = document.querySelector(`[data-note-id="${noteId}"]`);
    if (!container) return;
    const p = container.querySelector('[data-note-content]');
    const originalHtml = p.innerHTML;
    const originalText = p.textContent.trim();
    const textarea = document.createElement('textarea');
    textarea.className = 'w-full bg-black/20 border border-white/20 rounded-lg px-2 py-1 text-white text-sm';
    textarea.value = originalText;
    p.replaceWith(textarea);
    // Barra de acciones
    const actions = document.createElement('div');
    actions.className = 'mt-2 flex gap-2';
    const saveBtn = document.createElement('button');
    saveBtn.className = 'bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs';
    saveBtn.textContent = 'Guardar';
    const cancelBtn = document.createElement('button');
    cancelBtn.className = 'bg-gray-600 hover:bg-gray-700 text-white px-2 py-1 rounded text-xs';
    cancelBtn.textContent = 'Cancelar';
    actions.appendChild(saveBtn);
    actions.appendChild(cancelBtn);
    container.appendChild(actions);

    cancelBtn.onclick = () => {
        textarea.replaceWith(p);
        p.innerHTML = originalHtml;
        actions.remove();
    };
    saveBtn.onclick = async () => {
        const newText = textarea.value.trim();
        if (!newText) {
            showNotification('La nota no puede estar vacía', 'warning');
            return;
        }
        try {
            const resp = await fetch('api/notas.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: noteId, contenido_nota: newText })
            });
            const json = await resp.json();
            if (!json.success) throw new Error(json.message || 'Error al actualizar la nota');
            const newP = document.createElement('p');
            newP.className = 'text-gray-300 text-sm';
            newP.setAttribute('data-note-content', '');
            newP.innerHTML = newText.replace(/\n/g, '<br>');
            textarea.replaceWith(newP);
            actions.remove();
            showNotification('Nota actualizada', 'success');
        } catch (e) {
            showNotification('Error al actualizar la nota', 'error');
        }
    };
}

// Edición inline de comentarios
async function editComment(commentId) {
    const container = document.querySelector(`[data-comment-id="${commentId}"]`);
    if (!container) return;
    const p = container.querySelector('[data-comment-content]');
    const originalHtml = p.innerHTML;
    const originalText = p.textContent.trim();
    const textarea = document.createElement('textarea');
    textarea.className = 'w-full bg-black/20 border border-white/20 rounded-lg px-2 py-1 text-white text-sm';
    textarea.value = originalText;
    p.replaceWith(textarea);
    // Barra de acciones
    const actions = document.createElement('div');
    actions.className = 'mt-2 flex gap-2';
    const saveBtn = document.createElement('button');
    saveBtn.className = 'bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs';
    saveBtn.textContent = 'Guardar';
    const cancelBtn = document.createElement('button');
    cancelBtn.className = 'bg-gray-600 hover:bg-gray-700 text-white px-2 py-1 rounded text-xs';
    cancelBtn.textContent = 'Cancelar';
    actions.appendChild(saveBtn);
    actions.appendChild(cancelBtn);
    container.appendChild(actions);

    cancelBtn.onclick = () => {
        textarea.replaceWith(p);
        p.innerHTML = originalHtml;
        actions.remove();
    };
    saveBtn.onclick = async () => {
        const newText = textarea.value.trim();
        if (!newText) {
            showNotification('El comentario no puede estar vacío', 'warning');
            return;
        }
        try {
            const resp = await fetch('api/comentarios.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: commentId, comentario: newText })
            });
            const json = await resp.json();
            if (!json.success) throw new Error(json.message || 'Error al actualizar el comentario');
            const newP = document.createElement('p');
            newP.className = 'text-gray-300 text-sm';
            newP.setAttribute('data-comment-content', '');
            newP.innerHTML = newText.replace(/\n/g, '<br>');
            textarea.replaceWith(newP);
            actions.remove();
            showNotification('Comentario actualizado', 'success');
        } catch (e) {
            showNotification('Error al actualizar el comentario', 'error');
        }
    };
}

function prependCommentToList(comentario) {
    const list = document.getElementById('commentsList');
    if (!list) return;
    const empty = list.querySelector('p');
    if (empty && empty.textContent.includes('No hay comentarios')) empty.remove();
    const card = document.createElement('div');
    card.className = 'bg-black/20 rounded-lg p-3 border border-white/10';
    card.setAttribute('data-comment-id', comentario.id);
    const fecha = comentario.created_at ? new Date(comentario.created_at) : new Date();
    const fechaTxt = `${fecha.toLocaleDateString()} ${fecha.toLocaleTimeString()}`;
    card.innerHTML = `
        <div class="flex justify-between items-start mb-2">
            <span class="text-gray-400 text-xs">${fechaTxt}</span>
            <div class="flex gap-2">
                <button onclick="editComment(${comentario.id})" class="text-blue-400 hover:text-blue-300"><i class="fas fa-edit text-xs"></i></button>
                <button onclick="deleteComment(${comentario.id})" class="text-red-400 hover:text-red-300"><i class="fas fa-trash text-xs"></i></button>
            </div>
        </div>
        <p class="text-gray-300 text-sm" data-comment-content>${(comentario.comentario || '').replace(/\n/g, '<br>')}</p>
    `;
    list.prepend(card);
}

function removeCommentFromList(commentId) {
    const el = document.querySelector(`[data-comment-id="${commentId}"]`);
    if (el) el.remove();
    const list = document.getElementById('commentsList');
    if (list && list.children.length === 0) {
        const p = document.createElement('p');
        p.className = 'text-gray-400 text-sm text-center py-4';
        p.textContent = 'No hay comentarios';
        list.appendChild(p);
    }
}
