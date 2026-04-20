<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>400 — Solicitud incorrecta | Codex ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: linear-gradient(135deg, #0f0c29 0%, #1a1a3e 50%, #24243e 100%);
            color: #e0e0e0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
        }
        .error-container {
            text-align: center; max-width: 540px; padding: 2rem;
            animation: fadeUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .error-illustration {
            max-width: 380px; width: 100%; margin-bottom: 2rem; border-radius: 16px;
            filter: drop-shadow(0 8px 32px rgba(234, 179, 8, 0.3));
        }
        .error-code {
            font-size: 5rem; font-weight: 700; line-height: 1;
            background: linear-gradient(135deg, #fbbf24, #f59e0b, #d97706);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }
        .error-title { font-size: 1.5rem; font-weight: 600; color: #fcd34d; margin-bottom: 1rem; }
        .error-message { font-size: 1rem; color: #94a3b8; margin-bottom: 2rem; line-height: 1.6; }
        .error-actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem;
            border-radius: 12px; font-size: 0.875rem; font-weight: 600; text-decoration: none;
            transition: all 0.3s ease; border: none; cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(135deg, #f59e0b, #d97706); color: white;
            box-shadow: 0 4px 16px rgba(245, 158, 11, 0.4);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(245, 158, 11, 0.6); }
        .btn-ghost { background: rgba(255,255,255,0.06); color: #fcd34d; border: 1px solid rgba(255,255,255,0.1); }
        .btn-ghost:hover { background: rgba(255,255,255,0.12); }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <div class="error-container">
        <img src="/assets/img/errors/403.png" alt="Error" class="error-illustration">
        <div class="error-code"><?= $statusCode ?? 400 ?></div>
        <h1 class="error-title"><?= $statusCode === 403 ? 'Acceso denegado' : 'Solicitud incorrecta' ?></h1>
        <p class="error-message">
            <?= $statusCode === 403
                ? 'No tenés permisos suficientes para acceder a este recurso. Contactá al administrador.'
                : 'La solicitud contiene datos inválidos o está mal formada. Revisá los datos y volvé a intentar.'
            ?>
        </p>
        <div class="error-actions">
            <a href="/dashboard" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Ir al Dashboard
            </a>
            <a href="javascript:history.back()" class="btn btn-ghost">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                Volver
            </a>
        </div>
    </div>
</body>
</html>
