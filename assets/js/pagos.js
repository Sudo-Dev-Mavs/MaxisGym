/**
 * Registrar pagos: elegir socio con el buscador AJAX, mostrar qué se le puede
 * cobrar (inscribir, renovar, mejorar de plan o nada) y enviar el pago con
 * fetch sin recargar la página.
 */
(function () {
    const form = document.getElementById('form-pago');
    if (!form) return;

    const campoSocio = document.getElementById('socio_id');
    const tarjeta = document.getElementById('socio-elegido');
    const buscar = document.getElementById('buscar-socio');
    const plan = document.getElementById('plan_id');
    const monto = document.getElementById('monto');
    const ayudaMonto = document.getElementById('ayuda-monto');
    const aviso = document.getElementById('aviso-pago');
    const alerta = document.getElementById('alerta-pago');
    const boton = document.getElementById('boton-pago');

    const numero = (n) => Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // El monto no se captura: lo calcula el servidor (precio del plan, menos el crédito si es una mejora)
    const mostrarMonto = () => {
        const op = plan.selectedOptions[0];
        monto.value = op?.dataset.monto ? numero(op.dataset.monto) : '';
        const credito = Number(op?.dataset.credito || 0);
        ayudaMonto.textContent = !op?.dataset.monto ? ''
            : credito > 0
                ? 'Precio $' + numero(op.dataset.precio) + ' menos $' + numero(credito) + ' que no ha usado de su ' + plan.dataset.mejoraDe + '.'
                : 'Es el precio del plan elegido.';
    };
    plan.addEventListener('change', mostrarMonto);
    mostrarMonto();

    // Rehace la lista de planes con lo que se le puede cobrar al socio elegido
    function aplicarOpciones(op) {
        const anterior = plan.value;
        plan.replaceChildren(...op.planes.map((p) => {
            const o = new Option(p.nombre + ' ($' + numero(p.precio) + ', ' + p.duracion + ' días)', p.id);
            o.dataset.precio = p.precio;
            o.dataset.monto = p.monto;
            o.dataset.credito = p.credito;
            return o;
        }));
        if (!op.planes.length) plan.append(new Option('Sin planes disponibles', ''));
        const preferido = String(op.sugerido ?? anterior);
        if (plan.querySelector('option[value="' + preferido + '"]')) plan.value = preferido;
        plan.dataset.mejoraDe = op.mejora ? op.mejora.plan : '';

        const bloqueado = op.modo === 'bloqueado' || !op.planes.length;
        plan.disabled = bloqueado;
        boton.disabled = bloqueado;
        aviso.textContent = op.aviso;
        aviso.hidden = !op.aviso;
        aviso.className = 'alert small py-2 mb-3 ' + (op.modo === 'bloqueado' ? 'alert-warning' : 'alert-info');
        mostrarMonto();
    }

    let ultimaConsulta = 0;
    async function cargarOpciones(socioId) {
        const consulta = ++ultimaConsulta;
        plan.disabled = true;
        boton.disabled = true;
        const r = await peticion(form.dataset.opciones + (socioId ? '?socio=' + encodeURIComponent(socioId) : ''));
        if (consulta !== ultimaConsulta) return; // mientras tanto se eligió otro socio
        if (r.ok) {
            aplicarOpciones(r.opciones);
        } else {
            mostrarAlerta('danger', r.mensaje || 'No se pudieron cargar los planes de este socio.');
            plan.disabled = false;
            boton.disabled = false;
        }
    }

    function elegir(s) {
        campoSocio.value = s.id;
        tarjeta.querySelector('[data-campo="nombre"]').textContent = s.nombre;
        const detalle = tarjeta.querySelector('[data-campo="detalle"]');
        detalle.textContent = '#' + s.id + ', ' + s.email;
        detalle.append(document.createElement('br'),
            document.createTextNode(s.plan ? s.plan + ': ' + s.estado + (s.vence ? ', hasta ' + s.vence : '') : 'Sin membresía'));
        tarjeta.hidden = false;
        buscar.closest('.buscador').hidden = true;
        limpiarErrores();
        cargarOpciones(s.id);
    }

    document.getElementById('cambiar-socio').addEventListener('click', () => {
        campoSocio.value = '';
        tarjeta.hidden = true;
        buscar.closest('.buscador').hidden = false;
        buscar.focus();
        cargarOpciones(null);
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
        boton.disabled = plan.disabled;
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
