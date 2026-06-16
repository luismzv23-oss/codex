<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cliente Guardado</title>
</head>
<body>
    <script>
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({
                type: 'codex-customer-created',
                customer: <?= json_encode($customer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
                message: <?= json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
            }, window.location.origin);
        }
    </script>
</body>
</html>
