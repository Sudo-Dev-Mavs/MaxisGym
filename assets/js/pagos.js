/**
 * Registrar pagos: elegir socio con el buscador AJAX, rellenar el monto con
 * el precio del plan y enviar el pago con fetch sin recargar la página.
 */
(function () {
    const form = document.getElementById('form-pago');
    if (!form) return;

    const campoSocio = document.getElementById('socio_id');
    const tarjeta = document.getElementById('socio-elegido');
    const buscar = document.getElementById('buscar-socio');
    const plan = document.getElementById('plan_id');
    const monto = document.getElementById('monto');
    const alerta = document.getElementById('alerta-pago');
    const boton = document.getElementById('boton-pago');

    // Al cambiar de plan, el monto toma el precio del plan
    plan.addEventListener('change', () => {
        monto.value = plan.selectedOptions[0]?.dataset.precio || '';
    });
    if (!monto.value) monto.value = plan.selectedOptions[0]?.dataset.precio || '';

    function elegir(s) {
        campoSocio.value = s.id;
        tarjeta.querySelector('[data-campo="nombre"]').textContent = s.nombre;
        const detalle = tarjeta.querySelector('[data-campo="detalle"]');
        detalle.textContent = '#' + s.id + ', ' + s.email;
        detalle.append(document.createElement('br'),
            document.createTextNode(s.plan ? s.plan + ': ' + s.estado + (s.vence ? ', hasta ' + s.vence : '') : 'Sin membresía'));
        tarjeta.hidden = false;
        buscar.closest('.buscador').hidden = true;
        // Sugiere el mismo plan que tenía
        if (s.plan_id && plan.querySelector('option[value="' + s.plan_id + '"]')) {
            plan.value = String(s.plan_id);
            plan.dispatchEvent(new Event('change'));
        }
        limpiarErrores();
    }

    document.getElementById('cambiar-socio').addEventListener('click', () => {
        campoSocio.value = '';
        tarjeta.hidden = true;
        buscar.closest('.buscador').hidden = false;
        buscar.focus();
    });
    if (campoSocio.value) buscar.closest('.buscador').hidden = true;

    buscadorSocios({
        input: buscar,
        lista: document.getElementById('resultados-socio'),
        api: form.dataset.api,
        alElegir: elegir,
    });

    function limpiarErrores() {
        form.querySelectorAll('[data-error]').forEach((el) => { el.textContent = ''; });
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    }

    function mostrarAlerta(tipo, texto) {
        const div = document.createElement('div');
        div.className = 'alert alert-' + tipo;
        div.setAttribute('role', 'status');
        div.textContent = texto;
        alerta.replaceChildren(div);
    }

    function agregarFila(p) {
        const tbody = document.getElementById('pagos-recientes');
        const tr = document.createElement('tr');
        tr.className = 'fila-nueva';
        const celda = (principal, secundario, clase) => {
            const td = document.createElement('td');
            if (clase) td.className = clase;
            td.append(document.createTextNode(principal));
            if (secundario) {
                const s = document.createElement('span');
                s.className = 'd-block small text-body-secondary';
                s.textContent = secundario;
                td.append(s);
            }
            return td;
        };
        tr.append(
            celda(p.socio, '#' + p.socio_id + ', pagó el ' + p.fecha),
            celda(p.plan, p.periodo),
            celda(p.metodo, 'Registró: ' + p.registro),
            celda(p.monto, '', 'text-end fw-semibold'),
        );
        tbody.prepend(tr);
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        limpiarErrores();
        boton.disabled = true;
        boton.textContent = 'Registrando…';
        const r = await peticion(form.action, { method: 'POST', body: new FormData(form) });
        boton.disabled = false;
        boton.textContent = 'Registrar pago';

        if (r.ok) {
            mostrarAlerta('success', r.mensaje);
            if (r.pago) agregarFila(r.pago);
            document.getElementById('cambiar-socio').click();
            form.querySelector('#metodo_pago_id').value = '';
            return;
        }
        mostrarAlerta('danger', r.mensaje || 'No se pudo registrar el pago.');
        Object.entries(r.errores || {}).forEach(([campo, texto]) => {
            const destino = form.querySelector('[data-error="' + campo + '"]');
            if (destino) destino.textContent = texto;
            const control = form.querySelector('[name="' + campo + '"]');
            if (control && control.type !== 'hidden') control.classList.add('is-invalid');
            if (campo === 'socio_id') buscar.classList.add('is-invalid');
        });
    });
})();
