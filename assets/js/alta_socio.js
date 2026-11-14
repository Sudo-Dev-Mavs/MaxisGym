/** Alta de socios: al elegir el plan, el monto toma su precio. */
(function () {
    const plan = document.getElementById('plan_id');
    const monto = document.getElementById('monto');
    if (!plan || !monto) return;
    plan.addEventListener('change', () => {
        monto.value = plan.selectedOptions[0]?.dataset.precio || '';
    });
})();
