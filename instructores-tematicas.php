<?php
require_once 'config/database.php';
require_once 'config/config.php';

$db = getDatabase();

// Obtener instructores y temáticas
$instructores = $db->query("SELECT * FROM instructores ORDER BY nombre")->fetchAll();
$tematicas = $db->query("SELECT * FROM tematicas ORDER BY nombre")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CursosMy - Instructores & Temáticas</title>
    <link href="css/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .glass-dark {
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-purple-900 via-blue-900 to-indigo-900 min-h-screen">
    <!-- Navegación -->
    <nav class="m-4">
        <div class="glass-dark rounded-xl px-4 py-3 md:px-6 md:py-4 flex items-center justify-between shadow-lg sticky top-4 z-50">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-600 to-indigo-600 flex items-center justify-center ring-1 ring-white/20">
                    <i class="fas fa-graduation-cap text-white"></i>
                </div>
                <div class="flex items-center gap-2">
                    <a href="index.php" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-purple-500/40 bg-black/20 text-white hover:bg-black/30">
                        <i class="fas fa-home"></i><span class="hidden sm:inline">Dashboard</span>
                    </a>
                    <a href="instructores-tematicas.php" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-purple-500/40 bg-black/20 text-white hover:bg-black/30">
                        <i class="fas fa-users"></i><span class="hidden sm:inline">Instructores & Temáticas</span>
                    </a>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="index.php" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-purple-500/40 bg-black/20 text-purple-200 hover:bg-black/30">
                    <i class="fas fa-arrow-left"></i><span class="hidden sm:inline">Volver</span>
                </a>
                <button onclick="openGlobalSearchModal()" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-purple-400/40 bg-black/20 text-purple-200 hover:bg-black/30">
                    <i class="fas fa-search"></i><span class="hidden sm:inline">Buscador Global</span>
                </button>
            </div>
        </div>
    </nav>

    <div class="mx-4 mb-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Sección de Instructores -->
            <div class="glass-dark rounded-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-white">
                        <i class="fas fa-user-tie mr-2"></i>Instructores
                    </h2>
                    <button onclick="openInstructorModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>Nuevo Instructor
                    </button>
                </div>

                <div class="space-y-4 max-h-96 overflow-y-auto">
                    <?php if (empty($instructores)): ?>
                        <p class="text-gray-400 text-center py-8">No hay instructores registrados</p>
                    <?php else: ?>
                        <?php foreach ($instructores as $instructor): ?>
                            <div class="bg-black/20 rounded-lg p-4 border border-white/10">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <h3 class="text-white font-semibold text-lg"><?php echo htmlspecialchars($instructor['nombre']); ?></h3>
                                        <?php if ($instructor['email']): ?>
                                            <p class="text-gray-300 text-sm">
                                                <i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($instructor['email']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($instructor['bio']): ?>
                                            <p class="text-gray-400 text-sm mt-2"><?php echo htmlspecialchars($instructor['bio']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex space-x-2 ml-4">
                                        <button onclick="editInstructor(<?php echo $instructor['id']; ?>)" 
                                                class="bg-green-600 hover:bg-green-700 text-white p-2 rounded transition-colors">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteInstructor(<?php echo $instructor['id']; ?>)" 
                                                class="bg-red-600 hover:bg-red-700 text-white p-2 rounded transition-colors">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sección de Temáticas -->
            <div class="glass-dark rounded-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-white">
                        <i class="fas fa-tags mr-2"></i>Temáticas
                    </h2>
                    <button onclick="openTematicaModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>Nueva Temática
                    </button>
                </div>

                <div class="space-y-4 max-h-96 overflow-y-auto">
                    <?php if (empty($tematicas)): ?>
                        <p class="text-gray-400 text-center py-8">No hay temáticas registradas</p>
                    <?php else: ?>
                        <?php foreach ($tematicas as $tematica): ?>
                            <div class="bg-black/20 rounded-lg p-4 border border-white/10">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <h3 class="text-white font-semibold text-lg"><?php echo htmlspecialchars($tematica['nombre']); ?></h3>
                                        <?php if ($tematica['descripcion']): ?>
                                            <p class="text-gray-400 text-sm mt-2"><?php echo htmlspecialchars($tematica['descripcion']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex space-x-2 ml-4">
                                        <button onclick="editTematica(<?php echo $tematica['id']; ?>)" 
                                                class="bg-green-600 hover:bg-green-700 text-white p-2 rounded transition-colors">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteTematica(<?php echo $tematica['id']; ?>)" 
                                                class="bg-red-600 hover:bg-red-700 text-white p-2 rounded transition-colors">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Instructor -->
    <div id="instructorModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="glass-dark rounded-lg p-6 w-full max-w-md">
                <h2 id="instructorModalTitle" class="text-xl font-bold text-white mb-4">
                    <i class="fas fa-user-tie mr-2"></i>Nuevo Instructor
                </h2>
                
                <form id="instructorForm">
                    <input type="hidden" id="instructorId" name="id">
                    
                    <div class="mb-4">
                        <label class="block text-white text-sm font-bold mb-2">Nombre *</label>
                        <input type="text" id="instructorNombre" name="nombre" required 
                               class="w-full bg-black/20 border border-white/20 rounded-lg px-4 py-2 text-white placeholder-gray-400 focus:border-blue-500 focus:outline-none">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-white text-sm font-bold mb-2">Email</label>
                        <input type="email" id="instructorEmail" name="email" 
                               class="w-full bg-black/20 border border-white/20 rounded-lg px-4 py-2 text-white placeholder-gray-400 focus:border-blue-500 focus:outline-none">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-white text-sm font-bold mb-2">Biografía</label>
                        <textarea id="instructorBio" name="bio" rows="3" 
                                  class="w-full bg-black/20 border border-white/20 rounded-lg px-4 py-2 text-white placeholder-gray-400 focus:border-blue-500 focus:outline-none"></textarea>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-save mr-2"></i>Guardar
                        </button>
                        <button type="button" onclick="closeInstructorModal()" 
                                class="bg-gray-600 hover:bg-gray-700 text-white py-2 px-4 rounded-lg transition-colors">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Temática -->
    <div id="tematicaModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="glass-dark rounded-lg p-6 w-full max-w-md">
                <h2 id="tematicaModalTitle" class="text-xl font-bold text-white mb-4">
                    <i class="fas fa-tags mr-2"></i>Nueva Temática
                </h2>
                
                <form id="tematicaForm">
                    <input type="hidden" id="tematicaId" name="id">
                    
                    <div class="mb-4">
                        <label class="block text-white text-sm font-bold mb-2">Nombre *</label>
                        <input type="text" id="tematicaNombre" name="nombre" required 
                               class="w-full bg-black/20 border border-white/20 rounded-lg px-4 py-2 text-white placeholder-gray-400 focus:border-green-500 focus:outline-none">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-white text-sm font-bold mb-2">Descripción</label>
                        <textarea id="tematicaDescripcion" name="descripcion" rows="3" 
                                  class="w-full bg-black/20 border border-white/20 rounded-lg px-4 py-2 text-white placeholder-gray-400 focus:border-green-500 focus:outline-none"></textarea>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-save mr-2"></i>Guardar
                        </button>
                        <button type="button" onclick="closeTematicaModal()" 
                                class="bg-gray-600 hover:bg-gray-700 text-white py-2 px-4 rounded-lg transition-colors">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/instructores-tematicas.js"></script>
    <script>
        // Modal búsqueda global (misma UX que dashboard)
        function openGlobalSearchModal() {
            let modal = document.getElementById('globalSearchModal');
            if (!modal) {
                const wrapper = document.createElement('div');
                wrapper.id = 'globalSearchModal';
                wrapper.className = 'fixed inset-0 bg-black/60 z-50 flex items-start justify-center p-6';
                wrapper.innerHTML = `
                    <div class=\"glass-dark rounded-lg p-6 w-full max-w-3xl mt-10\">
                        <div class=\"flex items-center justify-between mb-4\">
                            <h3 class=\"text-xl font-bold text-white\"><i class=\"fas fa-search mr-2\"></i>Buscar en todos los cursos</h3>
                            <button class=\"bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded\" onclick=\"closeGlobalSearchModal()\"><i class=\"fas fa-times\"></i></button>
                        </div>
                        <input id=\"globalSearchInput\" type=\"text\" placeholder=\"Escribe para buscar en clases, notas, comentarios y adjuntos...\" class=\"w-full bg-black/20 border border-white/20 rounded px-3 py-2 text-white\" />
                        <div id=\"globalSearchResults\" class=\"mt-4 max-h-[60vh] overflow-y-auto space-y-2\"></div>
                    </div>`;
                document.body.appendChild(wrapper);
                wrapper.addEventListener('click', (e) => { if (e.target === wrapper) closeGlobalSearchModal(); });
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeGlobalSearchModal(); });
                document.getElementById('globalSearchInput').addEventListener('input', () => performGlobalSearch(document.getElementById('globalSearchInput').value.trim().toLowerCase()));
            } else {
                modal.classList.remove('hidden');
            }
            setTimeout(() => document.getElementById('globalSearchInput').focus(), 50);
        }
        function closeGlobalSearchModal() {
            const modal = document.getElementById('globalSearchModal');
            if (modal) modal.classList.add('hidden');
        }
        function formatTimeLocal(s) {
            s = Math.max(0, parseInt(s||0,10)); const h=Math.floor(s/3600), m=Math.floor((s%3600)/60), sec=s%60;
            const hh=h.toString().padStart(2,'0'), mm=m.toString().padStart(2,'0'), ss=sec.toString().padStart(2,'0');
            return h>0?`${hh}:${mm}:${ss}`:`${mm}:${ss}`;
        }
        function appendResultLocal(container, tipo, texto, onClick) {
            const row = document.createElement('div');
            row.className = 'flex items-center justify-between bg-black/30 border border-white/10 rounded px-3 py-2 text-sm text-gray-200 hover:bg-black/40 cursor-pointer';
            let strong = '<strong class="text-purple-300">';
            if (tipo==='Nota') strong='<strong class="text-orange-400" style="color:#fb923c">';
            else if (tipo==='Clase') strong='<strong class="text-green-400" style="color:#4ade80">';
            else if (tipo==='Adjunto') strong='<strong class="text-blue-400" style="color:#60a5fa">';
            else if (tipo==='Comentario') strong='<strong class="text-pink-400" style="color:#f472b6">';
            row.innerHTML = `<span>${strong}${tipo}:</strong> ${texto}</span><i class=\"fas fa-arrow-right text-white/60\"></i>`;
            row.addEventListener('click', onClick);
            container.appendChild(row);
        }
        async function performGlobalSearch(term) {
            const results = document.getElementById('globalSearchResults');
            results.innerHTML = '';
            if (!term) return;
            try {
                const res = await fetch(`api/search.php?q=${encodeURIComponent(term)}`);
                const json = await res.json();
                if (!json.success) return;
                (json.data || []).forEach(item => {
                    if (item.type==='Clase') appendResultLocal(results,'Clase', item.label, () => window.location.href=`reproductor.php?clase=${item.clase_id}`);
                    else if (item.type==='Nota') appendResultLocal(results,'Nota', `${formatTimeLocal(item.time)} • ${item.label}`, () => window.location.href=`reproductor.php?clase=${item.clase_id}&t=${item.time}`);
                    else if (item.type==='Comentario') appendResultLocal(results,'Comentario', item.label, () => window.location.href=`reproductor.php?clase=${item.clase_id}`);
                    else if (item.type==='Adjunto') appendResultLocal(results,'Adjunto', item.label, () => window.location.href=`reproductor.php?clase=${item.clase_id}`);
                });
            } catch(_) {}
        }
    </script>
</body>
</html>
