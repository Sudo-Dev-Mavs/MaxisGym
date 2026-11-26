/**
 * Consultar socios: buscador con sugerencias (combobox).
 *
 * - Mientras se escribe, una lista desplegable muestra los socios que
 *   coinciden ("ju" → Juan, Juventino...), con lo escrito resaltado.
 * - Elegir uno (clic, o flechas + Enter) muestra su ficha debajo.
 * - Enter sin elegir (o el botón "Ver todos") muestra todas las
 *   coincidencias en la tabla, p. ej. todos los que se llaman igual.
 *
 * Los datos se insertan con textContent (nunca innerHTML) para evitar XSS.
 */
(function () {
    const form = document.getElementById('form-consulta');
    if (!form) return;

    const input = document.getElementById('q');
    const lista = document.getElementById('sugerencias');
    const resumen = document.getElementById('resumen-consulta');
    const ficha = document.getElementById('ficha-socio');
    const tabla = document.getElementById('tabla-consulta');
    const tbody = document.getElementById('resultados-consulta');
    const api = form.dataset.api;
    const urlPagos = form.dataset.pagos;
    const AYUDA = 'Escribe y elige un socio de la lista para ver su ficha. Presiona Enter para ver todas las coincidencias.';

    const clasesEstado = {
        'activa': 'text-bg-success', 'vencida': 'text-bg-danger', 'cancelada': 'text-bg-secondary',
        'pendiente': 'text-bg-info', 'sin membresía': 'text-bg-light border',
    };

    let temporizador = null;
    let ultimaConsulta = '';
    let sugerencias = [];   // socios mostrados en la lista
    let activa = -1;        // índice de la opción resaltada con el teclado

    /* ---------- utilidades ---------- */

    function el(etiqueta, clase, texto) {
        const n = document.createElement(etiqueta);
        if (clase) n.className = clase;
        if (texto !== undefined && texto !== null) n.textContent = texto;
        return n;
    }

    function insignia(estado) {
        return el('span', 'badge ' + (clasesEstado[estado] || 'text-bg-light'),
            estado.charAt(0).toUpperCase() + estado.slice(1));
    }

    const plural = (n) => n + (n === 1 ? ' socio coincide' : ' socios coinciden');

    /**
     * Devuelve un fragmento con las palabras buscadas envueltas en <mark>.
     * Ignora mayúsculas y acentos: "maria" resalta "María".
     * Prefiere coincidencias al inicio de una palabra ("ju" en "Juan");
     * con soloInicios no resalta las de en medio (evita "ex[a]mple.com").
     */
    function resaltar(texto, consulta, soloInicios = false) {
        const sinAcento = (c) => c.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
        const base = [...texto].map(sinAcento);        // una entrada por carácter original
        const plano = base.join('');
        const marcas = new Array(texto.length).fill(false);
        // Posición en el texto original de cada carácter de "plano"
        const origen = [];
        base.forEach((b, i) => { for (let k = 0; k < b.length; k++) origen.push(i); });

        sinAcento(consulta).split(/\s+/).filter(Boolean).forEach((palabra) => {
            let i = -1;
            for (let k = plano.indexOf(palabra); k >= 0; k = plano.indexOf(palabra, k + 1)) {
                if (k === 0 || !/[a-z0-9ñ]/.test(plano[k - 1])) { i = k; break; }
            }
            if (i < 0 && !soloInicios) i = plano.indexOf(palabra);
            if (i < 0) return;
            for (let k = i; k < i + palabra.length; k++) marcas[origen[k]] = true;
        });

        const frag = document.createDocumentFragment();
        const chars = [...texto];
        let tramo = '';
        let marcado = false;
        const cerrar = () => {
            if (!tramo) return;
            frag.append(marcado ? el('mark', '', tramo) : document.createTextNode(tramo));
            tramo = '';
        };
        chars.forEach((c, i) => {
            if (marcas[i] !== marcado) { cerrar(); marcado = marcas[i]; }
            tramo += c;
        });
        cerrar();
        return frag;
    }

    /* ---------- lista de sugerencias ---------- */

    function abrir() {
        lista.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    function cerrar() {
        lista.hidden = true;
        lista.replaceChildren();
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        sugerencias = [];
        activa = -1;
    }

    function marcarActiva(i) {
        const opciones = lista.querySelectorAll('[role="option"]');
        if (!opciones.length) return;
        activa = (i + opciones.length) % opciones.length;
        opciones.forEach((o, k) => {
            o.classList.toggle('active', k === activa);
            o.setAttribute('aria-selected', k === activa ? 'true' : 'false');
        });
        input.setAttribute('aria-activedescendant', opciones[activa].id);
        opciones[activa].scrollIntoView({ block: 'nearest' });
    }

    function pintarSugerencias(socios, total, q) {
        lista.replaceChildren();
        sugerencias = socios;
        activa = -1;

        if (!socios.length) {
            lista.append(el('div', 'list-group-item text-body-secondary small', 'Ningún socio activo coincide con "' + q + '".'));
            abrir();
            return;
        }

        socios.forEach((s, i) => {
            const op = el('div', 'list-group-item list-group-item-action sugerencia');
            op.id = 'sugerencia-' + i;
            op.setAttribute('role', 'option');
            op.setAttribute('aria-selected', 'false');

            const texto = el('div', 'sugerencia-texto');
            const nombre = el('strong');
            nombre.append(resaltar(s.nombre, q));
            const detalle = el('span', 'd-block small text-body-secondary');
            detalle.append('#' + String(s.id).padStart(3, '0') + ' · ', resaltar(s.email, q, true));
            texto.append(nombre, detalle);

            op.append(texto, insignia(s.estado));
            // mousedown (no click) para que el input no pierda el foco antes de elegir
            op.addEventListener('mousedown', (e) => { e.preventDefault(); elegir(s); });
            op.addEventListener('mousemove', () => { if (activa !== i) marcarActiva(i); });
            lista.append(op);
        });

        const pie = el('button', 'list-group-item list-group-item-action sugerencia-pie',
            total > socios.length ? 'Ver los ' + total + ' resultados' : 'Ver todos en tabla');
        pie.type = 'button';
        pie.tabIndex = -1;
        pie.addEventListener('mousedown', (e) => { e.preventDefault(); verTodos(); });
        lista.append(pie);
        abrir();
    }

    async function sugerir() {
        const q = input.value.trim();
        ultimaConsulta = q;
        if (q === '') { cerrar(); return; }
        const r = await peticion(api + '?q=' + encodeURIComponent(q));
        if (q !== ultimaConsulta) return; // llegó tarde una respuesta vieja
        if (!r.ok) { cerrar(); resumen.textContent = r.mensaje || 'No se pudo buscar.'; return; }
        pintarSugerencias(r.socios, r.total, q);
    }

    /* ---------- ficha de un socio ---------- */

    function dato(etiqueta, valor, enlace) {
        const caja = el('div', 'ficha-dato');
        caja.append(el('dt', '', etiqueta));
        const dd = el('dd');
        if (valor && enlace) {
            const a = el('a', '', valor);
            a.href = enlace;
            dd.append(a);
        } else {
            dd.textContent = valor || '—';
        }
        caja.append(dd);
        return caja;
    }

    function elegir(s) {
        cerrar();
        input.value = s.nombre;
        actualizarUrl(s.nombre);
        tabla.hidden = true;
        resumen.textContent = 'Ficha de ' + s.nombre + '. Escribe otro nombre para buscar de nuevo.';

        const tarjeta = el('div', 'tarjeta tarjeta-auto ficha');

        const cabeza = el('div', 'ficha-cabeza');
        const iniciales = s.nombre.split(/\s+/).slice(0, 2).map((p) => p.charAt(0)).join('').toUpperCase();
        const avatar = el('span', 'ficha-avatar', iniciales);
        avatar.setAttribute('aria-hidden', 'true');
        const titulo = el('div', 'flex-grow-1');
        titulo.append(el('h2', 'ficha-nombre', s.nombre),
            el('span', 'small text-body-secondary', '#' + String(s.id).padStart(3, '0') + ' · Socio desde ' + s.alta));
        cabeza.append(avatar, titulo, insignia(s.estado));

        const datos = el('dl', 'ficha-datos');
        datos.append(
            dato('Correo', s.email, 'mailto:' + s.email),
            dato('Teléfono', s.telefono, s.telefono ? 'tel:' + s.telefono.replace(/\s+/g, '') : ''),
            dato('Plan', s.plan),
            dato('Vigencia', s.inicio && s.vence ? 'Del ' + s.inicio + ' al ' + s.vence : null),
        );

        const acciones = el('div', 'ficha-acciones');
        if (urlPagos) {
            const pagar = el('a', 'btn btn-seguridad');
            pagar.href = urlPagos + '?socio=' + encodeURIComponent(s.id);
            pagar.append(el('i', 'bi bi-cash-coin'), ' Registrar pago');
            pagar.firstChild.setAttribute('aria-hidden', 'true');
            acciones.append(pagar);
        }
        const otro = el('button', 'btn btn-outline-dark', 'Buscar otro socio');
        otro.type = 'button';
        otro.addEventListener('click', limpiar);
        acciones.append(otro);

        tarjeta.append(cabeza, datos, acciones);
        ficha.replaceChildren(tarjeta);
        ficha.hidden = false;
    }

    function limpiar() {
        input.value = '';
        actualizarUrl('');
        ficha.hidden = true;
        ficha.replaceChildren();
        tabla.hidden = true;
        tbody.replaceChildren();
        resumen.textContent = AYUDA;
        input.focus();
    }

    /* ---------- tabla con todas las coincidencias ---------- */

    function td(texto, secundario) {
        const c = el('td', '', texto);
        if (secundario) c.append(el('span', 'd-block small text-body-secondary', secundario));
        return c;
    }

    async function verTodos() {
        const q = input.value.trim();
        cerrar();
        if (q === '') { input.focus(); return; }
        ultimaConsulta = q;
        const r = await peticion(api + '?limite=50&q=' + encodeURIComponent(q));
        if (q !== ultimaConsulta) return;
        if (!r.ok) { resumen.textContent = r.mensaje || 'No se pudo buscar.'; return; }

        actualizarUrl(q);
        ficha.hidden = true;
        tbody.replaceChildren();
        resumen.textContent = plural(r.total) + ' con "' + q + '".'
            + (r.total > r.socios.length ? ' Se muestran los primeros ' + r.socios.length + '; escribe más para acotar.' : '');

        if (!r.socios.length) {
            const fila = el('tr');
            const c = el('td', 'text-body-secondary', 'Ningún socio activo coincide.');
            c.colSpan = urlPagos ? 7 : 6;
            fila.append(c);
            tbody.append(fila);
        }

        r.socios.forEach((s) => {
            const tr = el('tr');
            const nombre = el('td');
            const ver = el('button', 'btn btn-link p-0 fw-semibold text-reset text-start', s.nombre);
            ver.type = 'button';
            ver.title = 'Ver ficha';
            ver.addEventListener('click', () => elegir(s));
            nombre.append(ver);

            const estado = el('td');
            estado.append(insignia(s.estado));

            tr.append(td(String(s.id).padStart(3, '0')), nombre, td(s.email), td(s.telefono || '—'),
                td(s.plan || '—', s.vence ? 'hasta ' + s.vence : ''), estado);

            if (urlPagos) {
                const acc = el('td', 'text-end');
                const a = el('a', 'btn btn-sm btn-outline-dark', 'Registrar pago');
                a.href = urlPagos + '?socio=' + encodeURIComponent(s.id);
                acc.append(a);
                tr.append(acc);
            }
            tbody.append(tr);
        });
        tabla.hidden = false;
    }

    // Actualiza la URL para que "atrás" y recargar conserven la búsqueda
    function actualizarUrl(q) {
        const url = new URL(window.location.href);
        q ? url.searchParams.set('q', q) : url.searchParams.delete('q');
        history.replaceState(null, '', url);
    }

    /* ---------- eventos ---------- */

    input.addEventListener('input', () => {
        clearTimeout(temporizador);
        if (input.value.trim() === '') {
            cerrar();
            ultimaConsulta = '';
            return;
        }
        temporizador = setTimeout(sugerir, 200);
    });

    input.addEventListener('keydown', (e) => {
        const abierta = !lista.hidden && sugerencias.length;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (!lista.hidden) marcarActiva(activa + 1); else sugerir();
        } else if (e.key === 'ArrowUp' && abierta) {
            e.preventDefault();
            marcarActiva(activa - 1);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(temporizador);
            if (abierta && activa >= 0) elegir(sugerencias[activa]);
            else verTodos();
        } else if (e.key === 'Escape' && !lista.hidden) {
            e.preventDefault(); // en type="search" Escape también borraría el texto
            cerrar();
        }
    });

    // Al volver a enfocar el campo con texto, reabre las sugerencias
    // (no si ya se ve una ficha o la tabla, p. ej. al llegar con ?q= desde el panel)
    input.addEventListener('focus', () => {
        if (input.value.trim() && lista.hidden && ficha.hidden && tabla.hidden) sugerir();
    });
    input.addEventListener('blur', () => setTimeout(cerrar, 120));

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        clearTimeout(temporizador);
        verTodos();
    });
})();
