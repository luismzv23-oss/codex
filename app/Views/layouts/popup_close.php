<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Guardado</title>
</head>
<body>
    <script>
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({
                type: 'codex-popup-saved',
                entity: <?= json_encode($entity ?? null) ?>,
                action: <?= json_encode($action ?? null) ?>,
                item: <?= json_encode($item ?? null) ?>,
                redirectUrl: <?= json_encode($redirectUrl, JSON_UNESCAPED_SLASHES) ?>,
                message: <?= json_encode($message, JSON_UNESCAPED_SLASHES) ?>
            }, window.location.origin);
        } else {
            window.location.href = <?= json_encode($redirectUrl, JSON_UNESCAPED_SLASHES) ?>;
        }
    </script>
</body>
</html>
