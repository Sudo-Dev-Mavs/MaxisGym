/** Alta de socios: el monto a cobrar muestra el precio del plan elegido (el servidor cobra ese mismo precio). */
(function () {
    const plan = document.getElementById('plan_id');
    const monto = document.getElementById('monto');
    if (!plan || !monto) return;
    const mostrarMonto = () => {
        const precio = plan.selectedOptions[0]?.dataset.precio;
        monto.value = precio ? Number(precio).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
    };
    plan.addEventListener('change', mostrarMonto);
    mostrarMonto();
})();
