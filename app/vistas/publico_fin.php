<?php
/** Pie de página del sitio público con la información de contacto. */
$g = config('gimnasio');
?>
</main>

<footer class="pie">
    <div class="envoltura">
        <div class="pie-rejilla">
            <div>
                <a class="marca" href="<?= url('index.php') ?>">
                    <img src="<?= asset('img/logo.svg') ?>" alt="" width="30" height="30">
                    <span>MaxisGym</span>
                </a>
                <p class="pie-texto">Pesas, cardio y clases grupales, con tu membresía y tus pagos siempre a la mano.</p>
            </div>
            <div>
                <h2 class="pie-titulo">Visítanos</h2>
                <ul class="pie-lista">
                    <li><i class="bi bi-geo-alt" aria-hidden="true"></i>
                        <a href="<?= e(enlace_mapa()) ?>" target="_blank" rel="noopener"><?= e($g['direccion']) ?></a></li>
                    <?php foreach ($g['horario'] as $linea): ?>
                        <li><i class="bi bi-clock" aria-hidden="true"></i><span><?= e($linea) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <h2 class="pie-titulo">Contacto</h2>
                <ul class="pie-lista">
                    <li><i class="bi bi-telephone" aria-hidden="true"></i>
                        <a href="tel:<?= e(preg_replace('/\s+/', '', $g['telefono'])) ?>"><?= e($g['telefono']) ?></a></li>
                    <li><i class="bi bi-envelope" aria-hidden="true"></i>
                        <a href="mailto:<?= e($g['email']) ?>"><?= e($g['email']) ?></a></li>
                    <li><i class="bi bi-chat-left-text" aria-hidden="true"></i>
                        <a href="<?= url('contacto.php') ?>">Envíanos un mensaje</a></li>
                </ul>
            </div>
            <div>
                <h2 class="pie-titulo">Sitio</h2>
                <ul class="pie-lista">
                    <li><a href="<?= url('acerca.php') ?>">Acerca de</a></li>
                    <li><a href="<?= url('planes.php') ?>">Planes</a></li>
                    <li><a href="<?= url('login.php') ?>">Iniciar sesión</a></li>
                    <li><a href="<?= url('registro.php') ?>">Crear cuenta</a></li>
                </ul>
            </div>
        </div>
        <p class="pie-nombre" aria-hidden="true">MaxisGym</p>
        <div class="pie-legal">
            <span>© <?= date('Y') ?> MaxisGym. Proyecto académico, FIME – UANL.</span>
        </div>
    </div>
</footer>

<script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/sitio.js') ?>"></script>
</body>
</html>
