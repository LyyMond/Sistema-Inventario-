/**
 * assets/js/app.js
 * =========================================================
 * Script principal del sistema.
 * - Persistencia del tema claro/oscuro en localStorage
 * - Dropdown con búsqueda para selección de bienes
 * - Búsqueda en tablas en tiempo real
 * - Modal de confirmación de eliminación
 * =========================================================
 */

/* =========================================================
   1. TEMA CLARO / OSCURO
   ========================================================= */
(function () {
    const html        = document.documentElement;
    const btn         = document.getElementById('themeToggle');
    const iconSun     = document.getElementById('iconSun');
    const iconMoon    = document.getElementById('iconMoon');
    const themeText   = document.getElementById('themeText');
    const STORAGE_KEY = 'sgt_theme';

    // Aplicar tema guardado al cargar la página
    const saved = localStorage.getItem(STORAGE_KEY) || 'light';
    applyTheme(saved);

    if (btn) {
        btn.addEventListener('click', function () {
            const current = html.getAttribute('data-theme');
            applyTheme(current === 'dark' ? 'light' : 'dark');
        });
    }

    function applyTheme(theme) {
        html.setAttribute('data-theme', theme);
        localStorage.setItem(STORAGE_KEY, theme);
        if (iconSun && iconMoon) {
            if (theme === 'dark') {
                iconSun.classList.add('hidden');
                iconMoon.classList.remove('hidden');
                if (themeText) themeText.textContent = 'Modo Oscuro';
            } else {
                iconSun.classList.remove('hidden');
                iconMoon.classList.add('hidden');
                if (themeText) themeText.textContent = 'Modo Claro';
            }
        }
    }
})();


/* =========================================================
   2. DROPDOWN CON BÚSQUEDA (selector de bienes)
   ========================================================= */
document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('.searchable-dropdown').forEach(function (wrapper) {
        const toggle    = wrapper.querySelector('.dropdown-toggle');
        const menu      = wrapper.querySelector('.dropdown-menu');
        const searchBox = wrapper.querySelector('.dropdown-search');
        const list      = wrapper.querySelector('.dropdown-list');
        const hiddenInput = wrapper.querySelector('input[type="hidden"]');

        if (!toggle || !menu || !list) return;

        // Abrir / cerrar al hacer clic en el toggle
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = wrapper.classList.toggle('open');
            if (isOpen && searchBox) {
                searchBox.value = '';
                filterItems('');
                searchBox.focus();
            }
        });

        // Filtrar ítems según lo que escribe el usuario
        if (searchBox) {
            searchBox.addEventListener('input', function () {
                filterItems(this.value.trim().toLowerCase());
            });
        }

        // Seleccionar un ítem de la lista
        list.addEventListener('click', function (e) {
            const li = e.target.closest('li[data-value]');
            if (!li || li.classList.contains('no-results')) return;

            const value = li.getAttribute('data-value');
            const label = li.textContent.trim();

            toggle.textContent = label;
            if (hiddenInput) hiddenInput.value = value;

            // Marcar como seleccionado
            list.querySelectorAll('li').forEach(function (i) { i.classList.remove('selected'); });
            li.classList.add('selected');

            wrapper.classList.remove('open');
        });

        // Cerrar al hacer clic fuera
        document.addEventListener('click', function (e) {
            if (!wrapper.contains(e.target)) {
                wrapper.classList.remove('open');
            }
        });

        function filterItems(query) {
            const items   = list.querySelectorAll('li[data-value]');
            let   visible = 0;
            items.forEach(function (li) {
                const text = li.textContent.toLowerCase();
                const show = !query || text.includes(query);
                li.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            // Mostrar mensaje "Sin resultados"
            let noResult = list.querySelector('.no-results');
            if (visible === 0) {
                if (!noResult) {
                    noResult = document.createElement('li');
                    noResult.className = 'no-results';
                    noResult.textContent = 'Sin resultados';
                    list.appendChild(noResult);
                }
                noResult.style.display = '';
            } else if (noResult) {
                noResult.style.display = 'none';
            }
        }
    });


    /* =========================================================
       3. BÚSQUEDA EN TIEMPO REAL EN TABLAS
       ========================================================= */
    document.querySelectorAll('[data-search-table]').forEach(function (input) {
        const tableId = input.getAttribute('data-search-table');
        const table   = document.getElementById(tableId);
        if (!table) return;

        input.addEventListener('input', function () {
            const query = this.value.trim().toLowerCase();
            table.querySelectorAll('tbody tr').forEach(function (row) {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    });


    /* =========================================================
       4. MODAL DE CONFIRMACIÓN DE ELIMINACIÓN
       ========================================================= */
    const modalOverlay = document.getElementById('deleteModal');
    const modalMessage = document.getElementById('deleteModalMsg');
    const confirmBtn   = document.getElementById('deleteConfirmBtn');
    const cancelBtn    = document.getElementById('deleteCancelBtn');

    if (modalOverlay) {
        let deleteTarget = null;

        // Botones "Eliminar" que abren el modal
        document.querySelectorAll('[data-delete-url]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                deleteTarget = this.getAttribute('data-delete-url');
                const name   = this.getAttribute('data-delete-name') || 'este elemento';
                if (modalMessage) modalMessage.textContent = '¿Estás seguro de que deseas eliminar ' + name + '? Esta acción no se puede deshacer.';
                modalOverlay.classList.add('active');
            });
        });

        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                if (deleteTarget) window.location.href = deleteTarget;
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                modalOverlay.classList.remove('active');
                deleteTarget = null;
            });
        }

        // Cerrar al hacer clic en el overlay
        modalOverlay.addEventListener('click', function (e) {
            if (e.target === modalOverlay) {
                modalOverlay.classList.remove('active');
                deleteTarget = null;
            }
        });
    }


    /* =========================================================
       5. AUTO-HIDE DE ALERTAS (6 SEGUNDOS)
       ========================================================= */
    document.querySelectorAll('.alert').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 500);
        }, 6000);
    });


    /* =========================================================
       6. PESTAÑAS DE REGISTRO (Login page)
       ========================================================= */
    document.querySelectorAll('.tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = this.getAttribute('data-tab');

            // Desactivar todos los botones y contenidos
            document.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
            document.querySelectorAll('.tab-content').forEach(function (c) { c.classList.remove('active'); });

            // Activar el seleccionado
            this.classList.add('active');
            const content = document.getElementById(target);
            if (content) content.classList.add('active');
        });
    });

    /* =========================================================
       7. MOSTRAR / OCULTAR CONTRASEÑA
       ========================================================= */
    document.querySelectorAll('.password-toggle-btn').forEach(function (button) {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = e.currentTarget.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;

            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';

            // Alternar los iconos de ojo
            const openEye = e.currentTarget.querySelector('.eye-open');
            const closedEye = e.currentTarget.querySelector('.eye-closed');
            if (openEye && closedEye) {
                if (isPassword) {
                    openEye.style.display = 'none';
                    closedEye.style.display = 'block';
                } else {
                    openEye.style.display = 'block';
                    closedEye.style.display = 'none';
                }
            }
        });
    });

});

