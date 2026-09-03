<?php
/**
 * admin/inventario_form.php
 * Formulario dual: CREAR un nuevo bien o EDITAR uno existente.
 * Incluye cálculo automático de Valor Total y Dropdown buscador para Ubicaciones.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Administrador', '..');

$id      = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit  = $id > 0;
$error   = '';

$bien = [
    'Cod_bien' => '', 'Cantidad' => 1, 'Num_Bien' => '',
    'Descripcion' => '', 'Inv_Codigo' => '', 'Inv_Concepto' => '',
    'Valor_Unitario' => '0.00', 'Valor_Total' => '0.00', 'Ubicacion_ID' => 0
];

// Cargar ubicaciones para el searchable dropdown
$ubicaciones = [];
$resUbis = $conn->query("SELECT ID, Nombre FROM Ubicaciones ORDER BY Nombre ASC");
while ($row = $resUbis->fetch_assoc()) { $ubicaciones[] = $row; }

// Si es edición, cargar datos
if ($isEdit) {
    $stmt = $conn->prepare("SELECT * FROM Inventario WHERE ID = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) { header('Location: inventario.php'); exit; }
    $bien = $res->fetch_assoc();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codBien       = trim($_POST['cod_bien'] ?? '');
    $cantidad      = (int)($_POST['cantidad'] ?? 1);
    $numBien       = trim($_POST['num_bien'] ?? '');
    $descripcion   = trim($_POST['descripcion'] ?? '');
    $invCodigo     = trim($_POST['inv_codigo'] ?? '');
    $invConcepto   = trim($_POST['inv_concepto'] ?? '');
    $valorUnitario = (float)($_POST['valor_unitario'] ?? 0);
    $valorTotal    = (float)($_POST['valor_total'] ?? ($cantidad * $valorUnitario)); // Calculado en backend tmb
    $ubicacionId   = (int)($_POST['ubicacion_id'] ?? 0);

    if (empty($codBien) || empty($descripcion)) {
        $error = 'El código del bien y la descripción son obligatorios.';
    } else {
        // Chequear Cod_bien único
        if ($isEdit) {
            $chk = $conn->prepare("SELECT ID FROM Inventario WHERE Cod_bien = ? AND ID != ? LIMIT 1");
            $chk->bind_param('si', $codBien, $id);
        } else {
            $chk = $conn->prepare("SELECT ID FROM Inventario WHERE Cod_bien = ? LIMIT 1");
            $chk->bind_param('s', $codBien);
        }
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = 'Ese código de bien ya está en uso.';
        }
        $chk->close();

        if (empty($error)) {
            $ubiVal = $ubicacionId > 0 ? $ubicacionId : null;

            if ($isEdit) {
                $stmt = $conn->prepare(
                    "UPDATE Inventario SET Cod_bien=?, Cantidad=?, Num_Bien=?, Descripcion=?, Inv_Codigo=?, Inv_Concepto=?, Valor_Unitario=?, Valor_Total=?, Ubicacion_ID=? WHERE ID=?"
                );
                $stmt->bind_param('sissssddii', $codBien, $cantidad, $numBien, $descripcion, $invCodigo, $invConcepto, $valorUnitario, $valorTotal, $ubiVal, $id);
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO Inventario (Cod_bien, Cantidad, Num_Bien, Descripcion, Inv_Codigo, Inv_Concepto, Valor_Unitario, Valor_Total, Ubicacion_ID) VALUES (?,?,?,?,?,?,?,?,?)"
                );
                $stmt->bind_param('sissssddi', $codBien, $cantidad, $numBien, $descripcion, $invCodigo, $invConcepto, $valorUnitario, $valorTotal, $ubiVal);
            }
            $stmt->execute();
            $stmt->close();
            header('Location: inventario.php?ok=' . ($isEdit ? 'edit' : 'add'));
            exit;
        }
    }
    // Reflejar valores si hay error
    $bien = [
        'Cod_bien' => $codBien, 'Cantidad' => $cantidad, 'Num_Bien' => $numBien,
        'Descripcion' => $descripcion, 'Inv_Codigo' => $invCodigo, 'Inv_Concepto' => $invConcepto,
        'Valor_Unitario' => $valorUnitario, 'Valor_Total' => $valorTotal, 'Ubicacion_ID' => $ubicacionId
    ];
}

$pageTitle = $isEdit ? 'Editar bien' : 'Agregar bien';
$basePath  = '..';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= $isEdit ? 'Editar bien' : 'Agregar nuevo bien' ?></h1>
    </div>
    <a href="inventario.php" class="btn btn-secondary">← Volver</a>
</div>

<div style="max-width:800px">
    <div class="bento-card">
        <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <!-- Primera fila -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="form-group">
                    <label class="form-label" for="cod_bien">Código del bien *</label>
                    <input type="text" id="cod_bien" name="cod_bien" class="form-control"
                           value="<?= htmlspecialchars($bien['Cod_bien']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="num_bien">Nº de Bien</label>
                    <input type="text" id="num_bien" name="num_bien" class="form-control"
                           value="<?= htmlspecialchars($bien['Num_Bien']) ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="descripcion">Descripción *</label>
                <input type="text" id="descripcion" name="descripcion" class="form-control"
                       value="<?= htmlspecialchars($bien['Descripcion']) ?>" required>
            </div>

            <!-- Fila Ubicación y Cantidad -->
            <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem">
                <div class="form-group">
                    <label class="form-label">Ubicación / Sala</label>
                    <!-- Searchable Dropdown -->
                    <div class="searchable-dropdown" id="ubiDropdown">
                        <input type="hidden" name="ubicacion_id" id="ubiIdInput" value="<?= $bien['Ubicacion_ID'] ?>">
                        <button type="button" class="dropdown-toggle" id="ubiToggle">
                            <?php
                                $nombreSeleccionado = 'Selecciona una ubicación...';
                                foreach ($ubicaciones as $u) {
                                    if ($u['ID'] == $bien['Ubicacion_ID']) {
                                        $nombreSeleccionado = htmlspecialchars($u['Nombre']);
                                        break;
                                    }
                                }
                                echo $nombreSeleccionado;
                            ?>
                        </button>
                        <div class="dropdown-menu">
                            <input type="text" class="dropdown-search" placeholder="Buscar sala...">
                            <ul class="dropdown-list">
                                <li data-value="0">-- Sin ubicación --</li>
                                <?php foreach ($ubicaciones as $u): ?>
                                <li data-value="<?= $u['ID'] ?>"><?= htmlspecialchars($u['Nombre']) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="cantidad">Cantidad</label>
                    <input type="number" id="cantidad" name="cantidad" class="form-control"
                           value="<?= htmlspecialchars($bien['Cantidad']) ?>" min="1" oninput="calcTotal()">
                </div>
            </div>

            <!-- Fila Inventario Concepto y Código -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="form-group">
                    <label class="form-label" for="inv_codigo">Inv. Código</label>
                    <input type="text" id="inv_codigo" name="inv_codigo" class="form-control"
                           value="<?= htmlspecialchars($bien['Inv_Codigo']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="inv_concepto">Inv. Concepto</label>
                    <input type="text" id="inv_concepto" name="inv_concepto" class="form-control"
                           value="<?= htmlspecialchars($bien['Inv_Concepto']) ?>">
                </div>
            </div>

            <!-- Fila Valores -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="form-group">
                    <label class="form-label" for="valor_unitario">Valor Unitario</label>
                    <input type="number" step="0.01" id="valor_unitario" name="valor_unitario" class="form-control"
                           value="<?= htmlspecialchars($bien['Valor_Unitario']) ?>" oninput="calcTotal()">
                </div>
                <div class="form-group">
                    <label class="form-label" for="valor_total">Valor Total</label>
                    <input type="number" step="0.01" id="valor_total" name="valor_total" class="form-control"
                           value="<?= htmlspecialchars($bien['Valor_Total']) ?>" readonly style="opacity:0.8;background:var(--bg-card)">
                </div>
            </div>

            <div style="display:flex;gap:0.75rem;margin-top:1.5rem">
                <button type="submit" class="btn btn-primary">
                    <?= $isEdit ? 'Guardar cambios' : 'Agregar bien' ?>
                </button>
                <a href="inventario.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
// Cálculo automático de valor total
function calcTotal() {
    let cant = parseInt(document.getElementById('cantidad').value) || 0;
    let unit = parseFloat(document.getElementById('valor_unitario').value) || 0;
    let total = cant * unit;
    document.getElementById('valor_total').value = total.toFixed(2);
}

// Lógica básica para el Searchable Dropdown de Ubicación (replicando la lógica de ticket_nuevo)
document.addEventListener('DOMContentLoaded', () => {
    const dropdown = document.getElementById('ubiDropdown');
    if (!dropdown) return;

    const toggle = document.getElementById('ubiToggle');
    const menu   = dropdown.querySelector('.dropdown-menu');
    const search = dropdown.querySelector('.dropdown-search');
    const list   = dropdown.querySelector('.dropdown-list');
    const input  = document.getElementById('ubiIdInput');

    toggle.addEventListener('click', () => {
        const isOpen = menu.style.display === 'block';
        document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
        if (!isOpen) {
            menu.style.display = 'block';
            search.focus();
            search.value = '';
            Array.from(list.children).forEach(li => li.style.display = 'block');
        }
    });

    search.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase();
        Array.from(list.children).forEach(li => {
            if(li.classList.contains('no-results')) return;
            li.style.display = li.textContent.toLowerCase().includes(query) ? 'block' : 'none';
        });
    });

    list.addEventListener('click', (e) => {
        const li = e.target.closest('li');
        if (!li || li.classList.contains('no-results')) return;
        input.value = li.dataset.value;
        toggle.textContent = li.textContent;
        menu.style.display = 'none';
    });

    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target)) menu.style.display = 'none';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
