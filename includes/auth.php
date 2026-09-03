<?php
/**
 * includes/auth.php
 * ---------------------------------------------------------
 * Guard de sesión y control de roles.
 * Se incluye al inicio de cada página protegida.
 *
 * Uso:
 *   require_once __DIR__ . '/../includes/auth.php';
 *   requireRole('Administrador');   // Solo admins
 *   requireRole(['Tecnico','Operador']); // Múltiples roles
 * ---------------------------------------------------------
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Redirige al login si el usuario no ha iniciado sesión.
 * Opcionalmente verifica que el rol coincida.
 *
 * @param string|array $roles  Rol(es) permitido(s). Vacío = cualquier usuario autenticado.
 * @param string       $base   Ruta relativa a la raíz del proyecto (para construir la URL de login)
 */
function requireRole($roles = [], string $base = '') {
    // Si no hay sesión activa, redirigir al login
    if (empty($_SESSION['cedula'])) {
        header('Location: ' . $base . '/index.php?msg=session');
        exit;
    }

    // Si se especificaron roles, verificar que el usuario los tenga
    if (!empty($roles)) {
        $roles = (array) $roles; // Convertir a array si es string
        if (!in_array($_SESSION['rol'], $roles)) {
            // Acceso denegado: redirigir al dashboard según el rol del usuario
            header('Location: ' . $base . '/index.php?msg=forbidden');
            exit;
        }
    }
}

/**
 * Devuelve la URL del dashboard correspondiente al rol del usuario en sesión.
 * Útil para redirigir tras el login.
 */
function dashboardUrl(): string {
    switch ($_SESSION['rol'] ?? '') {
        case 'Administrador': return 'admin/dashboard.php';
        case 'Tecnico':       return 'tecnico/dashboard.php';
        case 'Operador':      return 'operador/dashboard.php';
        default:              return 'index.php';
    }
}

/**
 * Verifica si el usuario en sesión tiene alguno de los roles indicados.
 *
 * @param string|array $roles
 * @return bool
 */
function hasRole($roles): bool {
    return in_array($_SESSION['rol'] ?? '', (array) $roles);
}
