<?php
/**
 * includes/footer.php
 * ---------------------------------------------------------
 * Pie de página HTML común. Cierra los tags abiertos en
 * header.php y carga el script de JavaScript del sistema.
 * ---------------------------------------------------------
 */
?>
</main><!-- /main-container -->

<!-- ===== PIE DE PÁGINA ===== -->
<footer class="site-footer">
    <span><?= APP_NAME ?> &mdash; v<?= APP_VERSION ?></span>
    <span>Todos los derechos reservados &copy; <?= date('Y') ?></span>
</footer>

<!-- Script principal: dark mode, dropdowns y comportamientos UI -->
<script src="<?= $basePath ?>/assets/js/app.js?v=<?= time() ?>"></script>
</body>
</html>
