<?php
/** Cierre del layout de panel. $scripts (opcional): lista de archivos JS de assets/js a incluir. */
$scripts = $scripts ?? [];
?>
        </main>
    </div>
</div>

<!-- Ventana de confirmación reutilizable: cualquier <form data-confirmar="..."> la usa -->
<div class="modal fade" id="modal-confirmar" tabindex="-1" aria-labelledby="modal-confirmar-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="modal-confirmar-titulo">Confirmar</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="modal-confirmar-texto"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="modal-confirmar-si">Sí, continuar</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?php foreach ($scripts as $js): ?>
    <script src="<?= asset('js/' . $js) ?>"></script>
<?php endforeach; ?>
</body>
</html>
