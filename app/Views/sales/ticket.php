<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Ticket registrado</title></head><body>
<script src="<?= base_url('assets/js/qrcode-generator.js') ?>"></script>
<script src="<?= base_url('assets/js/kiosk-ticket-design.js') ?>"></script>
<script>
const markup = window.renderKioskTicket(
    <?= json_encode($ticketSettings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    <?= json_encode($ticketData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
);
document.open(); document.write(markup); document.close();
</script><noscript>Activa JavaScript para visualizar el ticket.</noscript></body></html>
