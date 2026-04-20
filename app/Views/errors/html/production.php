<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Error del servidor | Codex ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: linear-gradient(135deg, #1a0a0a 0%, #2d1515 50%, #1a1a2e 100%);
            color: #e0e0e0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
        }
        .error-container {
            text-align: center; max-width: 540px; padding: 2rem;
            animation: fadeUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .error-illustration {
            max-width: 380px; width: 100%; margin-bottom: 2rem; border-radius: 16px;
            filter: drop-shadow(0 8px 32px rgba(239, 68, 68, 0.3));
            animation: pulse 4s ease-in-out infinite;
        }
        .error-code {
            font-size: 5rem; font-weight: 700; line-height: 1;
            background: linear-gradient(135deg, #f87171, #ef4444, #f97316);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }
        .error-title { font-size: 1.5rem; font-weight: 600; color: #fca5a5; margin-bottom: 1rem; }
        .error-message { font-size: 1rem; color: #94a3b8; margin-bottom: 2rem; line-height: 1.6; }
        .error-actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem;
            border-radius: 12px; font-size: 0.875rem; font-weight: 600; text-decoration: none;
            transition: all 0.3s ease; border: none; cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(135deg, #ef4444, #f97316); color: white;
            box-shadow: 0 4px 16px rgba(239, 68, 68, 0.4);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(239, 68, 68, 0.6); }
        .btn-ghost { background: rgba(255,255,255,0.06); color: #fca5a5; border: 1px solid rgba(255,255,255,0.1); }
        .btn-ghost:hover { background: rgba(255,255,255,0.12); }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.85; } }
    </style>
</head>
<body>
    <div class="error-container">
        <img src="/assets/img/errors/500.png" alt="Error" class="error-illustration">
        <div class="error-code"><?= $statusCode ?? 500 ?></div>
        <h1 class="error-title">Algo salió mal</h1>
        <p class="error-message">Ocurrió un error interno en el servidor. El equipo técnico fue notificado. Intentá nuevamente en unos minutos.</p>
        <div class="error-actions">
            <a href="/dashboard" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Ir al Dashboard
            </a>
            <a href="javascript:location.reload()" class="btn btn-ghost">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                Reintentar
            </a>
        </div>
    </div>
</body>
</html>
