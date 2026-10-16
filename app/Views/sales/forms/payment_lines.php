<div class="col-12" data-company-collection-payments>
    <div class="d-flex justify-content-between align-items-center"><h3 class="h6">Desglose del cobro (<?= esc($currencyCode) ?>)</h3><button type="button" class="btn btn-outline-dark icon-btn" data-add-payment title="Agregar medio" aria-label="Agregar medio"><i class="bi bi-plus-lg" aria-hidden="true"></i></button></div>
    <p class="small text-secondary">Selecciona medios registrados en la empresa. Los que requieren verificación se aplicarán al confirmarlos. Este recibo no agrega recargos a facturas ya emitidas ni a anticipos.</p>
    <div data-payment-rows></div>
    <noscript>Activa JavaScript para ingresar el desglose del cobro.</noscript>
</div>
<script src="<?= base_url('assets/js/kiosk-payments.js') ?>"></script>
<script>
(() => {
    const root=document.querySelector('[data-company-collection-payments]');if(!root)return;
    const form=root.closest('form');const add=root.querySelector('[data-add-payment]');let payments;
    const point=form.querySelector('[data-collection-point]');
    payments=window.createKioskPayments(root.querySelector('[data-payment-rows]'),<?= json_encode($paymentMethods ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,()=>0,()=>{add.disabled=payments ? !payments.canAdd():false;}, {
        collection:true, partial:true, context:()=>({currency_code:<?= json_encode($currencyCode) ?>,branch_id:<?= json_encode($paymentBranchId ?? null) ?> || point?.selectedOptions[0]?.dataset.branch || null,point_of_sale_id:point?.value || null}),
        gateways:<?= json_encode($gateways ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        checks:<?= json_encode($checks ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    });
    add.addEventListener('click',()=>payments.add());point?.addEventListener('change',()=>payments.refresh());
    form.addEventListener('formdata',event=>payments.appendTo(event.formData,'payments'));
    form.addEventListener('submit',event=>{try{payments.validate();}catch(error){event.preventDefault();alert(error.message);}});
    payments.add();
})();
</script>
