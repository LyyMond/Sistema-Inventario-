<?php
/**
 * includes/header.php
 * ---------------------------------------------------------
 * Encabezado HTML común para todas las páginas del sistema.
 * Genera el <head>, la barra de navegación adaptada al rol,
 * y el botón de alternancia de modo claro/oscuro.
 *
 * Variables esperadas antes de incluir este archivo:
 *   $pageTitle  (string) — Título de la página actual
 *   $basePath   (string) — Ruta relativa a la raíz, ej: '..' para subcarpetas
 * ---------------------------------------------------------
 */

// Asegurarse de que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$pageTitle = $pageTitle ?? 'Sistema de Tickets';
$basePath  = $basePath  ?? '.';
$rol       = $_SESSION['rol']    ?? '';
$nombre    = $_SESSION['nombre'] ?? 'Usuario';
$cedula    = $_SESSION['cedula'] ?? '';
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema institucional de gestión de tickets e inventario">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/style.css?v=<?= time() ?>">
</head>
<body>

<!-- ===== BARRA DE NAVEGACIÓN PRINCIPAL ===== -->
<header class="navbar">
    <div class="navbar-brand">
        <!-- Icono institucional SVG embebido (sin dependencias externas) -->
        <svg class="brand-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M9 12h6M9 16h6M9 8h6M5 4h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <circle cx="12" cy="3" r="1.5" fill="currentColor"/>
        </svg>
        <span class="brand-text"><?= APP_NAME ?></span>
    </div>

    <!-- Navegación según el rol del usuario -->
    <nav class="navbar-nav">
        <?php if ($rol === 'Administrador'): ?>
            <a href="<?= $basePath ?>/admin/dashboard.php"   class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], 'dashboard.php') !== false ? 'active' : '' ?>">Inicio</a>
            <a href="<?= $basePath ?>/admin/inventario.php"  class="nav-link <?= (strpos($_SERVER['SCRIPT_NAME'], 'inventario.php') !== false || strpos($_SERVER['SCRIPT_NAME'], 'inventario_form.php') !== false) ? 'active' : '' ?>">Inventario</a>
            <a href="<?= $basePath ?>/admin/usuarios.php"    class="nav-link <?= (strpos($_SERVER['SCRIPT_NAME'], 'usuarios.php') !== false || strpos($_SERVER['SCRIPT_NAME'], 'usuarios_form.php') !== false) ? 'active' : '' ?>">Usuarios</a>
            <a href="<?= $basePath ?>/admin/historial.php"   class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], 'historial.php') !== false ? 'active' : '' ?>">Historial</a>

        <?php elseif ($rol === 'Tecnico'): ?>
            <a href="<?= $basePath ?>/tecnico/dashboard.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], 'dashboard.php') !== false ? 'active' : '' ?>">Inicio</a>
            <a href="<?= $basePath ?>/tecnico/tickets.php"   class="nav-link <?= (strpos($_SERVER['SCRIPT_NAME'], 'tecnico/tickets.php') !== false || strpos($_SERVER['SCRIPT_NAME'], 'ticket_estado.php') !== false || (strpos($_SERVER['SCRIPT_NAME'], 'ticket_historial.php') !== false && strpos($_SERVER['REQUEST_URI'], '/tecnico/') !== false)) ? 'active' : '' ?>">Tickets</a>
            <a href="<?= $basePath ?>/tecnico/historial.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], 'historial.php') !== false ? 'active' : '' ?>">Historial</a>

        <?php elseif ($rol === 'Operador'): ?>
            <a href="<?= $basePath ?>/operador/dashboard.php"      class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], 'dashboard.php') !== false ? 'active' : '' ?>">Inicio</a>
            <a href="<?= $basePath ?>/operador/ticket_nuevo.php"   class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], 'ticket_nuevo.php') !== false ? 'active' : '' ?>">Nuevo Ticket</a>
            <a href="<?= $basePath ?>/operador/tickets.php"        class="nav-link <?= (strpos($_SERVER['SCRIPT_NAME'], 'operador/tickets.php') !== false || (strpos($_SERVER['SCRIPT_NAME'], 'ticket_historial.php') !== false && strpos($_SERVER['REQUEST_URI'], '/operador/') !== false)) ? 'active' : '' ?>">Mis Tickets</a>
            <a href="<?= $basePath ?>/operador/historial.php"      class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], 'historial.php') !== false ? 'active' : '' ?>">Historial</a>
        <?php endif; ?>
        <a href="<?= $basePath ?>/perfil.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], 'perfil.php') !== false ? 'active' : '' ?>">Mi Perfil</a>
    </nav>

    <!-- Controles de usuario y tema -->
    <div class="navbar-actions">
        <span class="user-badge">
            <span class="role-pill role-<?= strtolower($rol) ?>"><?= htmlspecialchars($rol === 'Tecnico' ? 'Técnico' : $rol) ?></span>
            <?= htmlspecialchars($nombre) ?>
        </span>

        <!-- Botón toggle dark/light mode -->
        <button id="themeToggle" class="theme-toggle-btn" title="Cambiar tema" aria-label="Alternar modo oscuro/claro">
            <svg id="iconSun" class="theme-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2"/>
                <path d="M12 2v2M12 20v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M2 12h2M20 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <svg id="iconMoon" class="theme-icon hidden" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span id="themeText">Modo Claro</span>
        </button>

        <a href="<?= $basePath ?>/logout.php" class="btn btn-danger">Salir</a>
    </div>
</header>

<!-- ===== CONTENEDOR PRINCIPAL ===== -->
<main class="main-container">
