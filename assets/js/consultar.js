/**
 * Consultar socios: muestra la tabla de resultados mientras se escribe (AJAX).
 */
(function () {
    const form = document.getElementById('form-consulta');
    if (!form) return;
    const tbody = document.getElementById('resultados-consulta');
    const resumen = document.getElementById('resumen-consulta');
    const urlPagos = form.dataset.pagos;

    const clasesEstado = {
        'activa': 'text-bg-success', 'vencida': 'text-bg-danger', 'cancelada': 'text-bg-secondary',
        'pendiente': 'text-bg-info', 'sin membresía': 'text-bg-light border',
    };

    function td(texto, secundario) {
        const c = document.createElement('td');
        c.append(document.createTextNode(texto));
        if (secundario) {
            const s = document.createElement('span');
            s.className = 'd-block small text-body-secondary';
            s.textContent = secundario;
            c.append(s);
        }
        return c;
    }

    function pintar(socios, total, q, error) {
        tbody.replaceChildren();
        if (error) { resumen.textContent = error; return; }
        if (q === '') { resumen.textContent = 'Escribe para buscar. Los resultados aparecen mientras escribes.'; return; }
        resumen.textContent = total + (total === 1 ? ' resultado' : ' resultados') + ' para "' + q + '".';

        socios.forEach((s) => {
            const tr = document.createElement('tr');
            const nombre = document.createElement('td');
            const fuerte = document.createElement('strong');
            fuerte.textContent = s.nombre;
            nombre.append(fuerte);

            const estado = document.createElement('td');
            const insignia = document.createElement('span');
            insignia.className = 'badge ' + (clasesEstado[s.estado] || 'text-bg-light');
            insignia.textContent = s.estado.charAt(0).toUpperCase() + s.estado.slice(1);
            estado.append(insignia);

            tr.append(td(String(s.id).padStart(3, '0')), nombre, td(s.email), td(s.telefono || '—'),
                td(s.plan || '—', s.vence ? 'hasta ' + s.vence : ''), estado);

            if (urlPagos) {
                const acc = document.createElement('td');
                acc.className = 'text-end';
                const a = document.createElement('a');
                a.className = 'btn btn-sm btn-outline-dark';
                a.href = urlPagos + '?socio=' + encodeURIComponent(s.id);
                a.textContent = 'Registrar pago';
                acc.append(a);
                tr.append(acc);
            }
            tbody.append(tr);
        });
    }

    const input = document.getElementById('q');
    buscadorSocios({ input, lista: null, api: form.dataset.api, alRecibir: pintar });

    // Actualiza la URL para que "atrás" y recargar conserven la búsqueda
    input.addEventListener('input', () => {
        const url = new URL(window.location.href);
        input.value.trim() ? url.searchParams.set('q', input.value.trim()) : url.searchParams.delete('q');
        history.replaceState(null, '', url);
    });
    form.addEventListener('submit', (e) => e.preventDefault());
})();
