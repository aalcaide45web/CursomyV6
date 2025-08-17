// Variables globales
let selectedFiles = [];
let serverLimits = null; // Se llenará con los límites reales del servidor si están disponibles

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
    setupDropZone();
    fetchServerLimits();
    setupCourseProgressUI();
});

// Cargar límites reales del servidor desde la consola de debug (si existe)
async function fetchServerLimits() {
    try {
        const response = await fetch('debug-console.php?ajax=1');
        const data = await response.json();
        const uploadMax = parseSizeString(data?.php_config?.upload_max_filesize || '0');
        const postMax = parseSizeString(data?.php_config?.post_max_size || '0');
        if (uploadMax > 0 && postMax > 0) {
            serverLimits = {
                uploadMaxBytes: uploadMax,
                postMaxBytes: postMax,
                effectiveBytes: Math.min(uploadMax, postMax)
            };
            console.log('[DEBUG] Límites PHP detectados:', {
                upload_max_filesize: data.php_config.upload_max_filesize,
                post_max_size: data.php_config.post_max_size,
                effective: formatBytes(serverLimits.effectiveBytes)
            });
        }
    } catch (err) {
        console.warn('[DEBUG] No se pudieron obtener los límites del servidor. Se continuará sin validación previa.', err);
    }
}

function setupEventListeners() {
    // Forms
    document.getElementById('editCourseForm').addEventListener('submit', handleEditCourse);
    document.getElementById('sectionForm').addEventListener('submit', handleSectionSubmit);
    document.getElementById('uploadForm').addEventListener('submit', handleVideoUpload);
    
    // File input
    document.getElementById('videoFiles').addEventListener('change', handleFileSelect);
}

// Progreso en pantalla del curso
async function setupCourseProgressUI() {
    if (typeof CURSO_ID === 'undefined') return;
    try {
        console.log('[COURSE][SYNC] iniciando sync de duraciones');
        const syncRes = await fetch(`api/sync-durations.php?curso_id=${CURSO_ID}`);
        const syncJson = await syncRes.json().catch(()=>({}));
        console.log('[COURSE][SYNC] resultado:', syncJson);
        // Escaneo en navegador en cada carga de la página (no forzado):
        await scanDurations({ force: false });
        const res = await fetch(`api/progreso.php?curso_id=${CURSO_ID}`);
        const data = await res.json();
        if (!data.success) { console.error('[COURSE][PROGRESS] fallo', data); return; }

        // Construir mapas
        const classProgress = new Map(); // clase_id -> {tiempo_visto, duracion}
        (data.data || []).forEach(p => {
            if (p && p.clase_id) {
                classProgress.set(parseInt(p.clase_id), {
                    tiempo_visto: parseInt(p.tiempo_visto || 0),
                    duracion: parseInt(p.duracion || 0)
                });
            }
        });

        // Pintar porcentaje por clase
        document.querySelectorAll('[data-class-id]').forEach(row => {
            const id = parseInt(row.dataset.classId);
            const api = classProgress.get(id);
            let dur = api ? api.duracion : 0;
            if (!dur) {
                // fallback: intentar del data-attribute inyectado
                const dAttr = parseInt(row.getAttribute('data-duration') || '0');
                if (dAttr) dur = dAttr;
            }
            if (!dur) {
                // último fallback: estimar a partir del archivo (no disponible desde cliente). dejar 0
            }
            const holder = row.querySelector(`#clsProg-${id}`);
            const time = api ? api.tiempo_visto : 0;
            const percent = (time && dur) ? Math.min(100, Math.round((time / dur) * 100)) : 0;
            if (holder) holder.textContent = `${percent}%`;
            const seen = row.querySelector(`#clsSeen-${id}`);
            if (seen) seen.textContent = formatTimeDisplay(time);
            const total = row.querySelector(`#clsTotal-${id}`);
            if (total) total.textContent = formatTimeDisplay(dur, true);
        });

        // Porcentaje por sección
        document.querySelectorAll('[data-section-id]').forEach(card => {
            const sid = parseInt(card.dataset.sectionId);
            const clsRows = card.querySelectorAll('[data-class-id]');
            let sum = 0, count = 0;
            clsRows.forEach(r => {
                const id = parseInt(r.dataset.classId);
                const api = classProgress.get(id);
                const dur = api ? api.duracion : extractDurationSeconds(r);
                const time = api ? api.tiempo_visto : 0;
                const perc = (time && dur) ? Math.min(100, (time / dur) * 100) : 0;
                sum += perc; count += 1;
            });
            const avg = count ? Math.round(sum / count) : 0;
            const badge = card.querySelector(`[id^="secProg-"]`);
            if (badge) badge.textContent = `${avg}%`;
        });

        // Expandir/contraer secciones
        document.querySelectorAll('.toggleSection').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.getAttribute('data-target');
                const el = document.querySelector(target);
                if (!el) return;
                el.classList.toggle('hidden');
                const icon = btn.querySelector('i');
                if (icon) icon.className = el.classList.contains('hidden') ? 'fas fa-chevron-right' : 'fas fa-chevron-down';
            });
        });
        const expandAll = document.getElementById('expandAllBtn');
        const collapseAll = document.getElementById('collapseAllBtn');
        if (expandAll) expandAll.addEventListener('click', () => {
            document.querySelectorAll('[id^="sec-" ]').forEach(el => el.classList.remove('hidden'));
            document.querySelectorAll('.toggleSection i').forEach(i => i.className = 'fas fa-chevron-down');
        });
        if (collapseAll) collapseAll.addEventListener('click', () => {
            document.querySelectorAll('[id^="sec-" ]').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.toggleSection i').forEach(i => i.className = 'fas fa-chevron-right');
        });

        // Reset por sección (botón dentro de cada header)
        document.querySelectorAll('.resetSection').forEach(btn => {
            btn.addEventListener('click', async () => {
                const sid = parseInt(btn.getAttribute('data-section-id'));
                if (!sid) return;
                if (!confirm('¿Reiniciar progreso de esta sección?')) return;
                await fetch('api/progreso.php', { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ seccion_id: sid }) });
                window.location.reload();
            });
        });

        // Botones de sincronización (manual)
        const syncBtn = document.getElementById('syncDurationsBtn');
        const syncForceBtn = document.getElementById('syncDurationsForceBtn');
        if (syncBtn) syncBtn.addEventListener('click', async () => {
            console.log('[COURSE][SYNC] manual normal');
            const r = await fetch(`api/sync-durations.php?curso_id=${CURSO_ID}`);
            const j = await r.json().catch(()=>({}));
            console.log('[COURSE][SYNC] respuesta:', j);
            // Recorrido por navegador para las que queden a 0
            await scanDurations({ force: false });
            alert('Sincronización terminada');
            window.location.reload();
        });
        if (syncForceBtn) syncForceBtn.addEventListener('click', async () => {
            console.log('[COURSE][SYNC] manual forzada');
            const r = await fetch(`api/sync-durations.php?curso_id=${CURSO_ID}&force=1`);
            const j = await r.json().catch(()=>({}));
            console.log('[COURSE][SYNC] respuesta:', j);
            await scanDurations({ force: true });
            alert('Sincronización forzada terminada');
            window.location.reload();
        });
    } catch (e) {
        console.error('[COURSE][ERROR]', e);
    }
}

function extractDurationSeconds(row) {
    // Buscar el texto de duración "HH:MM:SS" o "MM:SS" en la fila si existe
    const txt = row.querySelector('p.text-gray-400');
    if (!txt) return 0;
    const m = txt.textContent.match(/(\d{1,2}:)?\d{1,2}:\d{2}/);
    if (!m) return 0;
    const parts = m[0].split(':').map(Number);
    if (parts.length === 3) return parts[0]*3600 + parts[1]*60 + parts[2];
    if (parts.length === 2) return parts[0]*60 + parts[1];
    return 0;
}

function formatTimeDisplay(seconds, forceHours = false) {
    seconds = parseInt(seconds || 0);
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    if (h > 0 || forceHours) return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    return `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
}

// Recorre clases del curso y persiste duración usando video.duration en el navegador
async function scanDurations({ force = false } = {}) {
    console.log('[COURSE][SCAN] iniciando scan de duraciones. force=', force);
    const classRows = Array.from(document.querySelectorAll('[data-class-id]'));
    for (const row of classRows) {
        const classId = parseInt(row.dataset.classId);
        const existing = parseInt(row.getAttribute('data-duration') || '0');
        if (!force && existing > 0) {
            continue;
        }
        const src = row.getAttribute('data-video-src');
        if (!src) continue;
        try {
            const dur = await getDurationViaBrowser(src);
            console.log('[COURSE][SCAN] clase', classId, 'dur=', dur);
            if (dur > 0) {
                await fetch('api/clases.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: classId, duracion: Math.floor(dur) })
                });
                row.setAttribute('data-duration', String(Math.floor(dur)));
            }
        } catch (e) {
            console.warn('[COURSE][SCAN] fallo duracion', classId, e);
        }
    }
}

function getDurationViaBrowser(src) {
    return new Promise((resolve) => {
        const v = document.createElement('video');
        v.preload = 'metadata';
        v.muted = true;
        v.src = src + (src.includes('?') ? '&' : '?') + 'cachebust=' + Date.now();
        const onLoaded = () => {
            const d = isFinite(v.duration) ? v.duration : 0;
            cleanup();
            resolve(d);
        };
        const onError = () => { cleanup(); resolve(0); };
        function cleanup() {
            v.removeEventListener('loadedmetadata', onLoaded);
            v.removeEventListener('error', onError);
            v.src = '';
        }
        v.addEventListener('loadedmetadata', onLoaded);
        v.addEventListener('error', onError);
        // timeout de seguridad
        setTimeout(() => { try { onError(); } catch(_){} }, 10000);
    });
}

function setupDropZone() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('videoFiles');
    
    // Click para abrir selector de archivos
    dropZone.addEventListener('click', () => fileInput.click());
    
    // Drag and drop
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });
    
    dropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        
        const files = Array.from(e.dataTransfer.files);
        handleFiles(files);
    });
}

function handleFileSelect(e) {
    const files = Array.from(e.target.files);
    handleFiles(files);
}

function handleFiles(files) {
    console.log('🔍 [DEBUG] Archivos seleccionados:', files.length);
    
    // Filtrar solo archivos MP4
    const mp4Files = files.filter(file => file.type === 'video/mp4');
    
    if (mp4Files.length !== files.length) {
        showNotification('Solo se permiten archivos MP4', 'warning');
        console.warn('⚠️ [DEBUG] Algunos archivos no son MP4');
    }
    
    // Verificar tamaño máximo - respetar límite de la app (500GB)
    const maxSizeApp = 500 * 1024 * 1024 * 1024; // 500GB (límite de la app)
    const effectiveServerLimit = serverLimits ? serverLimits.effectiveBytes : null; // Puede ser null si no se pudo obtener
    
    console.log(`📏 [DEBUG] Límite de la app: ${formatBytes(maxSizeApp)}`);
    if (effectiveServerLimit) {
        console.log(`📏 [DEBUG] Límite efectivo de PHP detectado: ${formatBytes(effectiveServerLimit)}`);
    }
    
    const validFiles = mp4Files.filter(file => {
        console.log(`📁 [DEBUG] Verificando archivo: ${file.name} (${formatBytes(file.size)})`);
        
        if (file.size > maxSizeApp) {
            showNotification(`El archivo "${file.name}" es demasiado grande (máximo 500GB)`, 'error');
            console.error(`❌ [DEBUG] Archivo excede límite de la app: ${file.name}`);
            return false;
        }
        
        // Si conocemos los límites del servidor, solo advertimos si el archivo los excede,
        // pero no bloqueamos el envío (el servidor será la última autoridad)
        if (effectiveServerLimit && file.size > effectiveServerLimit) {
            showNotification(`Advertencia: "${file.name}" excede el límite del servidor (${formatBytes(effectiveServerLimit)}). La subida puede fallar.`, 'warning');
            console.warn(`⚠️ [DEBUG] Archivo puede exceder límites de PHP: ${file.name}`);
        }
        
        console.log(`✅ [DEBUG] Archivo válido: ${file.name}`);
        return true;
    });
    
    selectedFiles = validFiles;
    updateFileList();
    updateUploadButton();
    
    console.log(`📋 [DEBUG] Archivos válidos seleccionados: ${validFiles.length}`);
}

function updateFileList() {
    const fileList = document.getElementById('fileList');
    fileList.innerHTML = '';
    
    if (selectedFiles.length === 0) {
        return;
    }
    
    selectedFiles.forEach((file, index) => {
        const fileItem = document.createElement('div');
        fileItem.className = 'bg-black/20 rounded-lg p-3 border border-white/10 flex justify-between items-center';
        
        fileItem.innerHTML = `
            <div class="flex items-center space-x-3">
                <i class="fas fa-video text-purple-400"></i>
                <div>
                    <p class="text-white text-sm font-medium">${file.name}</p>
                    <p class="text-gray-400 text-xs">${formatBytes(file.size)}</p>
                </div>
            </div>
            <button type="button" onclick="removeFile(${index})" class="text-red-400 hover:text-red-300">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        fileList.appendChild(fileItem);
    });
}

function removeFile(index) {
    selectedFiles.splice(index, 1);
    updateFileList();
    updateUploadButton();
}

function updateUploadButton() {
    const uploadButton = document.getElementById('uploadButton');
    const seccionSelect = document.getElementById('uploadSeccionSelect');
    
    uploadButton.disabled = selectedFiles.length === 0 || !seccionSelect.value;
}

// FUNCIONES DE CURSO

function openEditCourseModal() {
    document.getElementById('editCourseModal').classList.remove('hidden');
}

function closeEditCourseModal() {
    const modal = document.getElementById('editCourseModal');
    if (!modal) return;
    modal.classList.add('hidden');
    const form = document.getElementById('editCourseForm');
    if (form) form.reset();
}

// ----- Recursos por clase -----
let currentResourceClassId = null;
window.openResourceModal = function openResourceModal(claseId) {
    currentResourceClassId = claseId;
    const modal = document.getElementById('resourceModal');
    document.getElementById('resourceClaseId').value = String(claseId);
    modal.classList.remove('hidden');
}
window.closeResourceModal = function closeResourceModal() {
    const modal = document.getElementById('resourceModal');
    document.getElementById('resourceForm').reset();
    document.getElementById('resourceProgress').classList.add('hidden');
    
    // Limpiar la lista de archivos mostrada
    const fileList = document.getElementById('resourceFileList');
    if (fileList) {
        fileList.innerHTML = '';
    }
    
    // Limpiar el input de archivos
    const fileInput = document.getElementById('resourceFiles');
    if (fileInput) {
        fileInput.value = '';
    }
    
    modal.classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', () => {
    const resForm = document.getElementById('resourceForm');
    if (resForm) {
        const fileInput = document.getElementById('resourceFiles');
        const fileList = document.getElementById('resourceFileList');
        if (fileInput && fileList) {
            fileInput.addEventListener('change', () => {
                fileList.innerHTML = '';
                Array.from(fileInput.files || []).forEach(f => {
                    const row = document.createElement('div');
                    row.textContent = `• ${f.name}`;
                    fileList.appendChild(row);
                });
            });
        }
        resForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const files = document.getElementById('resourceFiles').files;
            if (!files || files.length === 0 || !currentResourceClassId) return;
            const progress = document.getElementById('resourceProgress');
            progress.classList.remove('hidden');
            progress.textContent = `Subiendo 0/${files.length}...`;
            let done = 0;
            let uploadedResources = []; // Para almacenar recursos subidos exitosamente
            
            for (const file of files) {
                const fd = new FormData();
                fd.append('clase_id', String(currentResourceClassId));
                fd.append('archivo', file);
                try {
                    const resp = await fetch('api/recursos.php', { method: 'POST', body: fd });
                    const json = await resp.json();
                    if (!json.success) throw new Error(json.message || 'Error al subir recurso');
                    
                    // Si la subida fue exitosa, almacenar info del recurso
                    if (json.resource) {
                        uploadedResources.push(json.resource);
                    }
                } catch (err) {
                    console.error('[RECURSOS] error', err);
                    showNotification(`Error al subir ${file.name}`, 'error');
                }
                done += 1;
                progress.textContent = `Subiendo ${done}/${files.length}...`;
            }
            progress.textContent = 'Completado';
            
            // Actualizar la interfaz con los nuevos recursos
            if (uploadedResources.length > 0) {
                await updateResourcesDisplay(currentResourceClassId, uploadedResources);
                showNotification(`${uploadedResources.length} recurso(s) agregado(s)`, 'success');
            }
            
            setTimeout(() => closeResourceModal(), 800);
        });
    }
});

// ----- Actualización dinámica de recursos -----
async function updateResourcesDisplay(classId, newResources) {
    const classRow = document.querySelector(`[data-class-id="${classId}"]`);
    if (!classRow) return;
    
    let resBox = classRow.querySelector(`#resBox-${classId}`);
    let resCount = classRow.querySelector(`#resCount-${classId}`);
    
    // Si no existe el contenedor de recursos, crearlo
    if (!resBox) {
        const classInfo = classRow.querySelector('.flex-1.min-w-0');
        if (!classInfo) return;
        
        resBox = document.createElement('div');
        resBox.id = `resBox-${classId}`;
        resBox.className = 'mt-2 text-xs bg-black/30 border border-white/10 rounded p-2';
        resBox.innerHTML = `
            <div class="text-gray-300 mb-1">
                <i class="fas fa-paperclip mr-1"></i>Recursos (<span id="resCount-${classId}">0</span>):
            </div>
            <div class="flex flex-wrap gap-2"></div>
        `;
        classInfo.appendChild(resBox);
        resCount = resBox.querySelector(`#resCount-${classId}`);
    }
    
    const resourcesContainer = resBox.querySelector('.flex.flex-wrap.gap-2');
    const currentCount = parseInt(resCount.textContent) || 0;
    
    // Agregar cada nuevo recurso
    newResources.forEach(resource => {
        const chip = document.createElement('span');
        chip.className = 'res-chip inline-flex items-center gap-2 px-2 py-1 rounded bg-black/40 border border-white/10 text-gray-200';
        chip.setAttribute('data-resource-id', resource.id);
        
        // Formatear tamaño del archivo
        const sizeFormatted = formatFileSize(resource.tamano_bytes);
        
        chip.innerHTML = `
            <a href="uploads/resources/${resource.curso_id}/${resource.archivo_path}" download
               class="inline-flex items-center gap-1 hover:text-white hover:underline"
               title="${resource.nombre_archivo} (${sizeFormatted})">
                <i class="fas fa-file"></i>
                <span class="max-w-[200px] truncate">${resource.nombre_archivo}</span>
            </a>
            <button class="text-red-400 hover:text-red-300" title="Eliminar recurso" onclick="deleteResource(${resource.id}, ${classId})">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        resourcesContainer.appendChild(chip);
    });
    
    // Actualizar contador
    resCount.textContent = currentCount + newResources.length;
}

// Helper para formatear tamaño de archivo
function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// ----- Ordenar clases con candado -----
let sortUnlocked = false;
window.setSortMode = function setSortMode(active) {
    sortUnlocked = active;
    document.querySelectorAll('.drag-handle').forEach(h => h.classList.toggle('hidden', !active));
    document.querySelectorAll('.class-item').forEach(row => {
        if (active) {
            row.setAttribute('draggable', 'true');
            row.addEventListener('dragstart', onDragStart);
            row.addEventListener('dragover', onDragOver);
            row.addEventListener('drop', onDrop);
            row.classList.add('cursor-move');
        } else {
            row.removeAttribute('draggable');
            row.removeEventListener('dragstart', onDragStart);
            row.removeEventListener('dragover', onDragOver);
            row.removeEventListener('drop', onDrop);
            row.classList.remove('cursor-move');
        }
    });
    const btn = document.getElementById('toggleSortBtn');
    if (btn) {
        btn.innerHTML = sortUnlocked
            ? '<i class="fas fa-unlock"></i><span class="ml-1">Orden editable</span>'
            : '<i class="fas fa-lock"></i><span class="ml-1">Orden bloqueado</span>';
    }
}

let dragSrcEl = null;
function onDragStart(e) {
    if (!e.currentTarget.classList.contains('class-item')) return;
    dragSrcEl = e.currentTarget;
    e.dataTransfer.effectAllowed = 'move';
    try { e.dataTransfer.setData('text/plain', dragSrcEl.dataset.classId); } catch (_) {}
}
function onDragOver(e) {
    e.preventDefault();
    const target = e.currentTarget;
    if (!target.classList.contains('class-item') || target === dragSrcEl) return;
    const container = target.parentElement;
    const rect = target.getBoundingClientRect();
    const before = (e.clientY - rect.top) < rect.height / 2;
    container.insertBefore(dragSrcEl, before ? target : target.nextSibling);
}
async function onDrop(e) {
    e.preventDefault();
    // Recalcular orden en la sección en curso
    const sectionContainer = e.currentTarget.closest('[id^="sec-"]');
    const classRows = Array.from(sectionContainer.querySelectorAll('.class-item'));
    // Guardar nuevo orden al vuelo
    for (let index = 0; index < classRows.length; index++) {
        const row = classRows[index];
        const classId = parseInt(row.dataset.classId);
        const newOrder = index + 1;
        try {
            await fetch('api/clases.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: classId, orden: newOrder })
            });
        } catch (_) {}
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('toggleSortBtn');
    if (toggleBtn) toggleBtn.addEventListener('click', () => setSortMode(!sortUnlocked));
});

// Exportar funciones usadas por onclick en HTML
window.openInlineEditClass = openInlineEditClass;
window.resetClassProgress = resetClassProgress;

// ----- Eliminación de recurso desde chip -----
window.deleteResource = async function deleteResource(resourceId, classId) {
    if (!confirm('¿Eliminar este recurso?')) return;
    try {
        const resp = await fetch('api/recursos.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: resourceId })
        });
        const json = await resp.json();
        if (!json.success) throw new Error(json.message || 'Error al eliminar');
        // Quitar chip del DOM
        const classRow = document.querySelector(`[data-class-id="${classId}"]`);
        if (classRow) {
            const btn = classRow.querySelector(`button[onclick*="deleteResource(${resourceId},"]`);
            if (btn && btn.parentElement) {
                const chip = btn.parentElement;
                chip.remove();
                const countEl = classRow.querySelector(`#resCount-${classId}`);
                if (countEl) {
                    const current = parseInt(countEl.textContent) || 1;
                    const next = Math.max(0, current - 1);
                    countEl.textContent = String(next);
                    if (next === 0) {
                        const box = classRow.querySelector(`#resBox-${classId}`);
                        if (box) box.remove();
                    }
                }
            }
        }
        showNotification('Recurso eliminado', 'success');
    } catch (e) {
        showNotification('No se pudo eliminar el recurso', 'error');
    }
}

// ----- Eliminar todos los recursos de una clase -----
window.deleteAllResources = async function deleteAllResources(classId) {
    if (!confirm('¿Eliminar TODOS los adjuntos de esta clase?\n\nEsta acción no se puede deshacer.')) return;
    
    try {
        const resp = await fetch(`api/recursos.php?deleteAll=1&clase_id=${classId}`, {
            method: 'DELETE'
        });
        const json = await resp.json();
        
        if (!json.success) throw new Error(json.message || 'Error al eliminar recursos');
        
        // Actualizar la interfaz - eliminar el contenedor de recursos
        const classRow = document.querySelector(`[data-class-id="${classId}"]`);
        if (classRow) {
            const resBox = classRow.querySelector(`#resBox-${classId}`);
            if (resBox) {
                resBox.remove();
            }
        }
        
        if (json.deleted_count > 0) {
            showNotification(`${json.deleted_count} recurso(s) eliminado(s)`, 'success');
        } else {
            showNotification('No había recursos para eliminar', 'info');
        }
        
    } catch (e) {
        console.error('Error eliminando recursos:', e);
        showNotification('No se pudieron eliminar los recursos', 'error');
    }
}

// ----- Búsqueda global -----
window.openGlobalSearchModal = function openGlobalSearchModal() {
    document.getElementById('globalSearchModal').classList.remove('hidden');
    const input = document.getElementById('globalSearchInput');
    input.value = '';
    document.getElementById('globalSearchResults').innerHTML = '';
    setTimeout(() => input.focus(), 50);
}
window.closeGlobalSearchModal = function closeGlobalSearchModal() {
    document.getElementById('globalSearchModal').classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('globalSearchInput');
    if (!input) return;
    input.addEventListener('input', () => performGlobalSearch(input.value.trim().toLowerCase()));
});

async function performGlobalSearch(term) {
    const results = document.getElementById('globalSearchResults');
    results.innerHTML = '';
    if (!term) return;
    const cursoParam = typeof CURSO_ID !== 'undefined' ? `&curso_id=${CURSO_ID}` : '';
    try {
        const res = await fetch(`api/search.php?q=${encodeURIComponent(term)}${cursoParam}`);
        const json = await res.json();
        if (!json.success) return;
        (json.data || []).forEach(item => {
            if (item.type === 'Clase') {
                appendResult(results, 'Clase', item.label, () => window.location.href = `reproductor.php?clase=${item.clase_id}`);
            } else if (item.type === 'Nota') {
                const label = `${formatTime(item.time)} • ${item.label}`;
                appendResult(results, 'Nota', label, () => window.location.href = `reproductor.php?clase=${item.clase_id}&t=${item.time}`);
            } else if (item.type === 'Comentario') {
                appendResult(results, 'Comentario', item.label, () => window.location.href = `reproductor.php?clase=${item.clase_id}`);
            } else if (item.type === 'Adjunto') {
                appendResult(results, 'Adjunto', item.label, () => window.location.href = `reproductor.php?clase=${item.clase_id}`);
            }
        });
    } catch (_) {}
}

function appendResult(container, tipo, texto, onClick) {
    const row = document.createElement('div');
    row.className = 'flex items-center justify-between bg-black/30 border border-white/10 rounded px-3 py-2 text-sm text-gray-200 hover:bg-black/40 cursor-pointer';
    let strongOpen;
    if (tipo === 'Nota') {
        strongOpen = '<strong class="text-orange-400" style="color:#fb923c">';
    } else if (tipo === 'Clase') {
        strongOpen = '<strong class="text-green-400" style="color:#4ade80">';
    } else if (tipo === 'Adjunto') {
        strongOpen = '<strong class="text-blue-400" style="color:#60a5fa">';
    } else if (tipo === 'Comentario') {
        strongOpen = '<strong class="text-pink-400" style="color:#f472b6">';
    } else {
        strongOpen = '<strong class="text-purple-300">';
    }
    row.innerHTML = `<span>${strongOpen}${tipo}:</strong> ${texto}</span><i class="fas fa-arrow-right text-white/60"></i>`;
    row.addEventListener('click', onClick);
    container.appendChild(row);
}

// Cerrar búsqueda global clic fuera y con ESC
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('globalSearchModal');
    if (modal) {
        modal.addEventListener('click', (e) => { if (e.target === modal) closeGlobalSearchModal(); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeGlobalSearchModal(); });
    }
});

// Utilidad local para formatear HH:MM:SS en el buscador global
function formatTime(totalSeconds) {
    const s = Math.max(0, parseInt(totalSeconds || 0, 10));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    const hh = h.toString().padStart(2, '0');
    const mm = m.toString().padStart(2, '0');
    const ss = sec.toString().padStart(2, '0');
    return h > 0 ? `${hh}:${mm}:${ss}` : `${mm}:${ss}`;
}

async function handleEditCourse(e) {
    e.preventDefault();
    
    const formEl = e.target;
    const formData = new FormData(formEl);
    
    // Si el formulario incluye un input file "imagen" o bandera de eliminación, usar multipart con override PUT
    const hasImage = formEl.querySelector('input[name="imagen"]');
    const deleteImageCheckbox = formEl.querySelector('input[name="delete_image"]');
    const wantsDelete = deleteImageCheckbox ? deleteImageCheckbox.checked : false;
    
    try {
        if (hasImage || wantsDelete) {
            formData.append('_method', 'PUT');
            if (wantsDelete) {
                formData.append('delete_image', '1');
            }
            
            const response = await fetch('api/cursos.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                showNotification('Curso actualizado exitosamente', 'success');
                closeEditCourseModal();
                setTimeout(() => {
                    window.location.reload();
                }, 800);
            } else {
                showNotification(result.message || 'Error al actualizar el curso', 'error');
            }
        } else {
            // Ruta JSON existente
    const data = {
        id: formData.get('id'),
        titulo: formData.get('titulo'),
        tematica_id: formData.get('tematica_id') || null,
        instructor_id: formData.get('instructor_id') || null,
        comentarios: formData.get('comentarios')
    };
        const response = await fetch('api/cursos.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        
        if (result.success) {
            showNotification('Curso actualizado exitosamente', 'success');
            closeEditCourseModal();
            setTimeout(() => {
                window.location.reload();
                }, 800);
        } else {
            showNotification(result.message || 'Error al actualizar el curso', 'error');
            }
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

// FUNCIONES DE SECCIONES

function openSectionModal(sectionData = null) {
    const modal = document.getElementById('sectionModal');
    const title = document.getElementById('sectionModalTitle');
    const form = document.getElementById('sectionForm');
    
    if (sectionData) {
        title.innerHTML = '<i class="fas fa-edit mr-2"></i>Editar Sección';
        document.getElementById('sectionId').value = sectionData.id;
        document.getElementById('sectionNombre').value = sectionData.nombre;
        document.getElementById('sectionOrden').value = sectionData.orden;
    } else {
        title.innerHTML = '<i class="fas fa-plus mr-2"></i>Nueva Sección';
        form.reset();
        document.getElementById('sectionId').value = '';
        // Establecer orden siguiente basado en el número REAL de secciones
        // Preferir el valor del servidor (SECCIONES), y si no existe, contar solo las secciones de primer nivel
        let sectionsCount = 0;
        try {
            if (typeof SECCIONES !== 'undefined' && Array.isArray(SECCIONES)) {
                sectionsCount = SECCIONES.length;
            } else {
                const container = document.querySelector('.sections-container');
                if (container) {
                    sectionsCount = container.querySelectorAll(':scope > div[data-section-id]').length;
                } else {
                    sectionsCount = document.querySelectorAll(':scope > .sections-container > div[data-section-id]').length;
                }
            }
        } catch (_) { sectionsCount = 0; }
        const nextOrder = Math.max(1, Number(sectionsCount) + 1);
        document.getElementById('sectionOrden').value = String(nextOrder);
    }
    
    modal.classList.remove('hidden');
}

function closeSectionModal() {
    document.getElementById('sectionModal').classList.add('hidden');
}

async function handleSectionSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const sectionId = formData.get('id');
    
    const data = {
        nombre: formData.get('nombre'),
        curso_id: parseInt(formData.get('curso_id')),
        orden: parseInt(formData.get('orden'))
    };
    
    if (sectionId) {
        data.id = parseInt(sectionId);
    }
    
    try {
        const method = sectionId ? 'PUT' : 'POST';
        const response = await fetch('api/secciones.php', {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(
                sectionId ? 'Sección actualizada exitosamente' : 'Sección creada exitosamente', 
                'success'
            );
            closeSectionModal();
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(result.message || 'Error al guardar la sección', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

async function editSection(sectionId) {
    try {
        const response = await fetch(`api/secciones.php?id=${sectionId}`);
        const result = await response.json();
        
        if (result.success) {
            openSectionModal(result.data);
        } else {
            showNotification('Error al cargar los datos de la sección', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

async function deleteSection(sectionId) {
    if (!confirm('¿Estás seguro de que quieres eliminar esta sección? También se eliminarán todas las clases que contiene.')) {
        return;
    }
    
    try {
        const response = await fetch('api/secciones.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: sectionId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Sección eliminada exitosamente', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(result.message || 'Error al eliminar la sección', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

// FUNCIONES DE UPLOAD

function openUploadModal() {
    document.getElementById('uploadModal').classList.remove('hidden');
    // Actualizar selector de secciones
    updateUploadButton();
}

function closeUploadModal() {
    document.getElementById('uploadModal').classList.add('hidden');
    selectedFiles = [];
    updateFileList();
    document.getElementById('uploadForm').reset();
    document.getElementById('uploadProgress').classList.add('hidden');
}

// Agregar listener para el cambio de sección
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('uploadSeccionSelect').addEventListener('change', updateUploadButton);
});

async function handleVideoUpload(e) {
    e.preventDefault();
    
    console.log('🚀 [DEBUG] Iniciando upload de videos...');
    
    if (selectedFiles.length === 0) {
        console.warn('⚠️ [DEBUG] No hay archivos seleccionados');
        showNotification('Selecciona al menos un video', 'warning');
        return;
    }
    
    const seccionId = document.getElementById('uploadSeccionSelect').value;
    if (!seccionId) {
        console.warn('⚠️ [DEBUG] No hay sección seleccionada');
        showNotification('Selecciona una sección', 'warning');
        return;
    }
    
    console.log(`📋 [DEBUG] Curso ID: ${CURSO_ID}`);
    console.log(`📋 [DEBUG] Sección ID: ${seccionId}`);
    console.log(`📁 [DEBUG] Archivos a subir: ${selectedFiles.length}`);
    
    // Calcular tamaño total
    const totalSize = selectedFiles.reduce((sum, file) => sum + file.size, 0);
    console.log(`📏 [DEBUG] Tamaño total: ${formatBytes(totalSize)}`);
    
    // Verificar límites antes de enviar: si se detectan límites del servidor, solo advertir
    if (serverLimits && totalSize > serverLimits.effectiveBytes) {
        console.warn(`⚠️ [DEBUG] Tamaño total (${formatBytes(totalSize)}) excede el límite efectivo de PHP (${formatBytes(serverLimits.effectiveBytes)}).`);
        showNotification(`Advertencia: El tamaño total (${formatBytes(totalSize)}) puede exceder los límites del servidor y la subida podría fallar.`, 'warning');
        // Continuamos; el servidor será quien valide definitivamente
    }
    
    const formData = new FormData();
    formData.append('curso_id', CURSO_ID);
    formData.append('seccion_id', seccionId);
    
    selectedFiles.forEach((file, index) => {
        console.log(`📎 [DEBUG] Agregando archivo ${index + 1}: ${file.name} (${formatBytes(file.size)})`);
        formData.append('videos[]', file);
    });
    
    try {
        // Mostrar barra de progreso
        document.getElementById('uploadProgress').classList.remove('hidden');
        document.getElementById('uploadButton').disabled = true;
        
        console.log('📤 [DEBUG] Enviando petición a la API...');
        console.log(`🌐 [DEBUG] URL: api/upload-videos.php`);
        console.log(`📋 [DEBUG] Método: POST`);
        console.log(`📦 [DEBUG] FormData entries:`, Array.from(formData.entries()).map(([key, value]) => 
            key === 'videos[]' ? [key, `File: ${value.name} (${formatBytes(value.size)})`] : [key, value]
        ));
        
        const startTime = Date.now();
        const response = await fetch('api/upload-videos.php', {
            method: 'POST',
            body: formData
        });
        const endTime = Date.now();
        
        console.log(`📥 [DEBUG] Respuesta recibida en ${endTime - startTime}ms`);
        console.log(`📊 [DEBUG] Status: ${response.status} ${response.statusText}`);
        console.log(`📋 [DEBUG] Headers:`, Object.fromEntries(response.headers.entries()));
        
        // Debug: verificar el contenido de la respuesta
        const responseText = await response.text();
        console.log('📄 [DEBUG] Response text length:', responseText.length);
        console.log('📄 [DEBUG] Response text:', responseText);
        
        // Verificar si la respuesta contiene HTML/errores antes del JSON
        if (responseText.includes('<br />') || responseText.includes('<b>')) {
            console.error('❌ [DEBUG] La respuesta contiene HTML/errores de PHP:');
            console.error('🔍 [DEBUG] Esto indica que PHP está mostrando warnings/errores antes del JSON');
            
            // Intentar extraer el JSON después de los errores
            const jsonStart = responseText.indexOf('{');
            if (jsonStart !== -1) {
                const jsonPart = responseText.substring(jsonStart);
                console.log('🔧 [DEBUG] Intentando parsear solo la parte JSON:', jsonPart);
                try {
                    const result = JSON.parse(jsonPart);
                    console.log('✅ [DEBUG] JSON extraído correctamente:', result);
                    handleUploadResult(result);
                    return;
                } catch (extractError) {
                    console.error('❌ [DEBUG] Error al parsear JSON extraído:', extractError);
                }
            }
            
            showNotification('Error del servidor: La respuesta contiene errores de PHP. Revisa la consola para más detalles.', 'error');
            return;
        }
        
        let result;
        try {
            result = JSON.parse(responseText);
            console.log('✅ [DEBUG] JSON parseado correctamente:', result);
        } catch (parseError) {
            console.error('❌ [DEBUG] JSON Parse Error:', parseError);
            console.error('📄 [DEBUG] Response was:', responseText);
            
            showNotification('Error: Respuesta del servidor inválida. Revisa la consola para más detalles.', 'error');
            return;
        }
        
        handleUploadResult(result);
        
    } catch (error) {
        console.error('❌ [DEBUG] Error en la petición:', error);
        console.error('📋 [DEBUG] Error details:', {
            name: error.name,
            message: error.message,
            stack: error.stack
        });
        showNotification('Error de conexión: ' + error.message, 'error');
    } finally {
        document.getElementById('uploadProgress').classList.add('hidden');
        document.getElementById('uploadButton').disabled = false;
        console.log('🏁 [DEBUG] Upload finalizado');
    }
}

function handleUploadResult(result) {
    console.log('📊 [DEBUG] Procesando resultado del upload:', result);
    
    if (result.success) {
        console.log('✅ [DEBUG] Upload exitoso');
        showNotification(`${result.uploaded} video(s) subido(s) exitosamente`, 'success');
        // Tras subida, sincronizar duraciones automáticamente (cliente)
        (async () => {
            try {
                await scanDurations({ force: false });
            } catch (e) { console.warn('[UPLOAD][SCAN] fallo', e); }
        closeUploadModal();
            setTimeout(() => window.location.reload(), 800);
        })();
    } else {
        console.error('❌ [DEBUG] Upload falló:', result.message);
        showNotification(result.message || 'Error al subir los videos', 'error');
        
        if (result.errors && result.errors.length > 0) {
            console.error('📋 [DEBUG] Errores específicos:', result.errors);
            result.errors.forEach((error, index) => {
                console.error(`   ${index + 1}. ${error}`);
            });
        }
    }
}

// FUNCIONES DE CLASES

async function editClass(classId) {
    // Mantener redirección legacy si no existe inline editor
    openInlineEditClass(classId, (document.querySelector(`[data-class-id="${classId}"] h4`)||{}).textContent || '');
}
// Renombrar clase inline en curso.php
async function openInlineEditClass(classId, currentTitle) {
    const row = document.querySelector(`[data-class-id="${classId}"]`);
    if (!row) return;
    const titleEl = row.querySelector('h4');
    if (!titleEl) return;
    const old = titleEl.textContent;
    row.dataset.oldTitle = old;
    titleEl.innerHTML = `
        <input id="inlineEdit-${classId}" class="bg-black/40 border border-white/20 rounded px-2 py-1 text-white text-sm w-72" value="${currentTitle.replace(/"/g, '&quot;')}" />
        <button id="inlineSave-${classId}" class="ml-2 bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs">Guardar</button>
        <button id="inlineCancel-${classId}" class="ml-1 bg-gray-600 hover:bg-gray-700 text-white px-2 py-1 rounded text-xs">Cancelar</button>
    `;
    const input = document.getElementById(`inlineEdit-${classId}`);
    input.focus();
    const saveBtn = document.getElementById(`inlineSave-${classId}`);
    const cancelBtn = document.getElementById(`inlineCancel-${classId}`);
    saveBtn.addEventListener('click', () => saveInlineEditClass(classId));
    cancelBtn.addEventListener('click', () => cancelInlineEditClass(classId));
}

async function saveInlineEditClass(classId) {
    const input = document.getElementById(`inlineEdit-${classId}`);
    if (!input) return;
    const newTitle = input.value.trim();
    if (!newTitle) return;
    try {
        const res = await fetch('api/clases.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: classId, titulo: newTitle })
        });
        const data = await res.json();
        if (data.success) {
            const row = document.querySelector(`[data-class-id="${classId}"]`);
            const titleEl = row.querySelector('h4');
            titleEl.textContent = newTitle;
            showNotification('Clase renombrada', 'success');
        } else {
            showNotification(data.message || 'No se pudo renombrar', 'error');
        }
    } catch (e) {
        showNotification('Error de conexión', 'error');
    }
}

function cancelInlineEditClass(classId) {
    const row = document.querySelector(`[data-class-id="${classId}"]`);
    if (!row) return;
    const titleEl = row.querySelector('h4');
    const oldTitle = row.dataset.oldTitle || '';
    titleEl.textContent = oldTitle;
}

async function resetClassProgress(classId) {
    if (!confirm('¿Reiniciar progreso de esta clase?')) return;
    try {
        await fetch('api/progreso.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ clase_id: classId })
        });
        // Actualizar badge a 0%
        const holder = document.getElementById(`clsProg-${classId}`);
        if (holder) holder.textContent = '0%';
        showNotification('Progreso de la clase reiniciado', 'success');
    } catch (e) {
        showNotification('Error al reiniciar progreso', 'error');
    }
}

async function deleteClass(classId) {
    if (!confirm('¿Estás seguro de que quieres eliminar esta clase?')) {
        return;
    }
    
    const deleteVideo = confirm('¿También quieres eliminar el archivo de video?');
    
    try {
        const response = await fetch('api/clases.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                id: classId,
                deleteVideo: deleteVideo 
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Clase eliminada exitosamente', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(result.message || 'Error al eliminar la clase', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

// UTILIDADES

function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

// Convierte cadenas tipo "128M", "1G" a bytes
function parseSizeString(size) {
    if (!size || typeof size !== 'string') return 0;
    const match = size.trim().match(/^(\d+)\s*([KMG])?$/i);
    if (!match) return 0;
    const value = parseInt(match[1], 10);
    const unit = (match[2] || '').toUpperCase();
    const multipliers = { K: 1024, M: 1024 * 1024, G: 1024 * 1024 * 1024 };
    return value * (multipliers[unit] || 1);
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

// Cerrar modales con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditCourseModal();
        closeSectionModal();
        closeUploadModal();
    }
});
