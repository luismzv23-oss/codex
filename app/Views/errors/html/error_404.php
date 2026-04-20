<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>404 — Página no encontrada | Codex ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: linear-gradient(135deg, #0f0c29 0%, #1a1a3e 50%, #24243e 100%);
            color: #e0e0e0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            overflow: hidden;
        }
        .error-container {
            text-align: center; max-width: 540px; padding: 2rem;
            animation: fadeUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .error-illustration {
            max-width: 380px; width: 100%; margin-bottom: 2rem; border-radius: 16px;
            filter: drop-shadow(0 8px 32px rgba(99, 102, 241, 0.3));
            animation: float 6s ease-in-out infinite;
        }
        .error-code {
            font-size: 5rem; font-weight: 700; line-height: 1;
            background: linear-gradient(135deg, #a78bfa, #6366f1, #06b6d4);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }
        .error-title { font-size: 1.5rem; font-weight: 600; color: #c4b5fd; margin-bottom: 1rem; }
        .error-message { font-size: 1rem; color: #94a3b8; margin-bottom: 2rem; line-height: 1.6; }
        .error-actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem;
            border-radius: 12px; font-size: 0.875rem; font-weight: 600; text-decoration: none;
            transition: all 0.3s ease; border: none; cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white;
            box-shadow: 0 4px 16px rgba(99, 102, 241, 0.4);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(99, 102, 241, 0.6); }
        .btn-ghost { background: rgba(255,255,255,0.06); color: #c4b5fd; border: 1px solid rgba(255,255,255,0.1); }
        .btn-ghost:hover { background: rgba(255,255,255,0.12); }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }
        .particles { position: fixed; width: 100%; height: 100%; pointer-events: none; z-index: -1; }
        .particle {
            position: absolute; width: 3px; height: 3px; background: rgba(99, 102, 241, 0.4);
            border-radius: 50%; animation: drift 15s linear infinite;
        }
        @keyframes drift { from { transform: translateY(100vh) rotate(0deg); opacity: 0; } 10% { opacity: 1; } 90% { opacity: 1; } to { transform: translateY(-10vh) rotate(720deg); opacity: 0; } }
    </style>
</head>
<body>
    <div class="particles">
        <?php for ($i = 0; $i < 20; $i++): ?>
        <div class="particle" style="left: <?= rand(0, 100) ?>%; animation-delay: <?= rand(0, 150) / 10 ?>s; animation-duration: <?= rand(100, 200) / 10 ?>s; width: <?= rand(2, 5) ?>px; height: <?= rand(2, 5) ?>px;"></div>
        <?php endfor; ?>
    </div>
    <div class="error-container">
        <img src="/assets/img/errors/404.png" alt="404" class="error-illustration">
        <div class="error-code">404</div>
        <h1 class="error-title">Página no encontrada</h1>
        <p class="error-message">La ruta que buscás no existe o fue movida. Verificá la URL o volvé al inicio.</p>
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
