<?php
/**
 * tecnico/ticket_nuevo.php
 * Formulario para que el técnico cree un nuevo ticket.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Tecnico', '..');

$error   = '';
$success = '';

// Cargar ubicaciones disponibles
$ubicaciones = $conn->query("SELECT ID, Nombre FROM Ubicaciones ORDER BY Nombre ASC");
$ubicacionesArr = [];
if ($ubicaciones) {
    while ($u = $ubicaciones->fetch_assoc()) {
        $ubicacionesArr[] = $u;
    }
}

// Cargar todos los bienes
$bienes = $conn->query(
    "SELECT i.Cod_bien, i.Descripcion, i.Ubicacion_ID 
     FROM Inventario i 
     ORDER BY i.Cod_bien ASC"
);
$bienesArr = [];
if ($bienes) {
    while ($b = $bienes->fetch_assoc()) {
        $bienesArr[] = $b;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codBien     = trim($_POST['cod_bien'] ?? '');
    $desFalla    = trim($_POST['des_falla']         ?? '');
    $prioridad   = trim($_POST['prioridad']         ?? 'Baja');
    $sugerencias = trim($_POST['sugerencias']       ?? '');

    if (empty($codBien) || empty($desFalla)) {
        $error = 'Debes seleccionar un bien y describir la falla.';
    } elseif (mb_strlen($desFalla) > 100) {
        $error = 'La descripción de la falla no puede superar los 100 caracteres.';
    } elseif (mb_strlen($sugerencias) > 100) {
        $error = 'Las sugerencias no pueden superar los 100 caracteres.';
    } elseif (!in_array($prioridad, ['Baja','Media','Urgente'])) {
        $error = 'Prioridad no válida.';
    } else {
        // Verificar que el bien exista en el inventario
        $chkB = $conn->prepare("SELECT Cod_bien FROM Inventario WHERE Cod_bien = ? LIMIT 1");
        $chkB->bind_param('s', $codBien);
        $chkB->execute();
        $resB = $chkB->get_result();
        $chkB->close();

        if ($resB->num_rows === 0) {
            $error = 'El código de bien seleccionado no es válido.';
        } else {
            $nombre = $_SESSION['nombre'];
            $stmt   = $conn->prepare(
                "INSERT INTO Tickets (Cod_bien, Des_Falla, Prioridad, Nombre, Sugerencias)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('sssss', $codBien, $desFalla, $prioridad, $nombre, $sugerencias);
            $stmt->execute();
            $newId = $conn->insert_id;
            $stmt->close();

            // Registrar en historial (estado inicial = Inicio)
            $stmtH = $conn->prepare(
                "INSERT INTO Tickets_Historial (Tck_ID, Est_Ac, Est_An) VALUES (?, 'Inicio', 'N/A')"
            );
            $stmtH->bind_param('i', $newId);
            $stmtH->execute();
            $stmtH->close();

            header('Location: tickets.php?ok=created');
            exit;
        }
    }
}

$pageTitle = 'Nuevo ticket';
$basePath  = '..';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Crear nuevo ticket</h1>
        <p>Reporta una falla o solicitud de soporte técnico.</p>
    </div>
    <a href="tickets.php" class="btn btn-secondary">← Volver a tickets</a>
</div>

<div style="max-width:680px">
    <div class="bento-card">
        <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <!-- Selector de Ubicación -->
            <div class="form-group">
                <label class="form-label" for="ubicacionSelect">Ubicación (Sala) *</label>
                <select id="ubicacionSelect" class="form-control" required>
                    <option value="">-- Selecciona una ubicación --</option>
                    <?php foreach ($ubicacionesArr as $u): ?>
                        <option value="<?= $u['ID'] ?>"><?= htmlspecialchars($u['Nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="form-hint">Selecciona la ubicación donde se encuentra el bien.</p>
            </div>

            <!-- Selector de bien con búsqueda por código de bien -->
            <div class="form-group">
                <label class="form-label" for="codBienSearch">Código de bien *</label>
                <div class="searchable-dropdown" id="bienDropdown">
                    <input type="hidden" name="cod_bien" id="codBienValue" value="">
                    <input type="text" id="codBienSearch" class="form-control" placeholder="Selecciona primero la ubicación..." autocomplete="off" disabled required>
                    <div class="dropdown-menu" id="suggestionsMenu" style="max-height: 220px; overflow-y: auto;">
                        <ul class="dropdown-list" id="suggestionsList">
                            <!-- Poblado dinámicamente -->
                        </ul>
                    </div>
                </div>
                <p class="form-hint">Escribe el código de bien para buscar y seleccionar entre los equipos de la ubicación seleccionada.</p>
            </div>

            <div class="form-group">
                <label class="form-label" for="des_falla">Descripción de la falla *</label>
                <input type="text" id="des_falla" name="des_falla" class="form-control"
                       placeholder="Describe brevemente el problema (máx. 100 caracteres)..." maxlength="100" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="prioridad">Prioridad *</label>
                <select id="prioridad" name="prioridad" class="form-control">
                    <option value="Baja">Baja</option>
                    <option value="Media">Media</option>
                    <option value="Urgente">Urgente</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="sugerencias">Sugerencias (opcional)</label>
                <input type="text" id="sugerencias" name="sugerencias" class="form-control"
                       placeholder="Alguna observación o sugerencia para el técnico (máx. 100 caracteres)..." maxlength="100">
            </div>

            <div style="display:flex;gap:0.75rem;margin-top:1.5rem">
                <button type="submit" class="btn btn-primary">Enviar ticket</button>
                <a href="tickets.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
const bienes = <?= json_encode($bienesArr) ?>;

document.addEventListener('DOMContentLoaded', () => {
    const ubicacionSelect = document.getElementById('ubicacionSelect');
    const codBienSearch = document.getElementById('codBienSearch');
    const codBienValue = document.getElementById('codBienValue');
    const suggestionsMenu = document.getElementById('suggestionsMenu');
    const suggestionsList = document.getElementById('suggestionsList');

    if (!ubicacionSelect || !codBienSearch || !codBienValue || !suggestionsMenu || !suggestionsList) return;

    function showSuggestions() {
        const uId = ubicacionSelect.value;
        const query = codBienSearch.value.toLowerCase().trim();
        
        suggestionsList.innerHTML = '';
        if (!uId) {
            suggestionsMenu.style.display = 'none';
            return;
        }

        const filtered = bienes.filter(b => b.Ubicacion_ID == uId && b.Cod_bien.toLowerCase().includes(query));

        if (filtered.length === 0) {
            const li = document.createElement('li');
            li.className = 'no-results';
            li.style.padding = '0.75rem 1rem';
            li.style.color = 'var(--text-muted)';
            li.textContent = 'No se encontraron bienes con ese código en esta ubicación';
            suggestionsList.appendChild(li);
        } else {
            filtered.forEach(b => {
                const li = document.createElement('li');
                li.style.padding = '0.75rem 1rem';
                li.style.cursor = 'pointer';
                li.style.transition = 'background-color 0.2s';
                li.innerHTML = `<strong>[${b.Cod_bien}]</strong> ${b.Descripcion}`;
                
                li.addEventListener('click', () => {
                    codBienSearch.value = b.Cod_bien;
                    codBienValue.value = b.Cod_bien;
                    suggestionsMenu.style.display = 'none';
                });
                suggestionsList.appendChild(li);
            });
        }
        suggestionsMenu.style.display = 'block';
    }

    ubicacionSelect.addEventListener('change', () => {
        const uId = ubicacionSelect.value;
        codBienSearch.value = '';
        codBienValue.value = '';
        suggestionsMenu.style.display = 'none';
        
        if (uId) {
            codBienSearch.disabled = false;
            codBienSearch.placeholder = 'Escribe el código del bien...';
        } else {
            codBienSearch.disabled = true;
            codBienSearch.placeholder = 'Selecciona primero la ubicación...';
        }
    });

    codBienSearch.addEventListener('input', () => {
        codBienValue.value = ''; 
        showSuggestions();
    });

    codBienSearch.addEventListener('focus', () => {
        if (!codBienSearch.disabled) {
            showSuggestions();
        }
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#bienDropdown') && e.target !== codBienSearch) {
            suggestionsMenu.style.display = 'none';
        }
    });

    const form = codBienSearch.closest('form');
    if (form) {
        form.addEventListener('submit', (e) => {
            if (!codBienValue.value) {
                e.preventDefault();
                alert('Debes seleccionar un bien válido de la lista de sugerencias.');
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
