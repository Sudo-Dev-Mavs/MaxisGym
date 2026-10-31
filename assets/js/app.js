/**
 * JavaScript común de MaxisGym.
 * Por ahora sólo contiene la función para peticiones AJAX con token CSRF;
 * cada pantalla agrega su propio comportamiento.
 */

/**
 * Hace una petición AJAX (fetch) que envía el token CSRF y espera JSON.
 *   const r = await peticion('api/buscar_socios.php?q=juan');
 *   const r = await peticion('api/pagos.php', { method: 'POST', body: new FormData(form) });
 */
async function peticion(url, opciones = {}) {
    const meta = document.querySelector('meta[name="csrf-token"]');
    const cabeceras = {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        ...(meta ? { 'X-CSRF-Token': meta.content } : {}),
        ...(opciones.headers || {}),
    };
    const respuesta = await fetch(url, { credentials: 'same-origin', ...opciones, headers: cabeceras });
    let datos;
    try {
        datos = await respuesta.json();
    } catch (e) {
        datos = { ok: false, mensaje: 'Respuesta inesperada del servidor.' };
    }
    if (!respuesta.ok && datos.ok === undefined) {
        datos.ok = false;
    }
    return datos;
}

/**
 * Confirmación antes de enviar formularios peligrosos (eliminar, dar de baja).
 * Uso: <form method="post" data-confirmar="¿Eliminar el plan Básico?" data-boton="Eliminar">
 */
document.addEventListener('submit', (evento) => {
    const form = evento.target;
    if (!form.matches('form[data-confirmar]') || form.dataset.confirmado === '1') {
        return;
    }
    const modalEl = document.getElementById('modal-confirmar');
    if (!modalEl || !window.bootstrap) {
        return; // sin modal disponible, el formulario se envía normal
    }
    evento.preventDefault();
    document.getElementById('modal-confirmar-texto').textContent = form.dataset.confirmar;
    const boton = document.getElementById('modal-confirmar-si');
    boton.textContent = form.dataset.boton || 'Sí, continuar';
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    boton.onclick = () => {
        form.dataset.confirmado = '1';
        modal.hide();
        form.requestSubmit ? form.requestSubmit() : form.submit();
    };
    modal.show();
});
