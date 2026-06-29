<?php
/**
 * Codex Sales Controller Restoration Utility
 * Discards uncommitted changes on Sales controllers.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

// Fallback helper if esc() is not loaded
if (!function_exists('esc')) {
    function esc($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

// Execute restoration commands
$restoreWeb = shell_exec("git checkout -- ../app/Controllers/SalesController.php 2>&1");
$restoreApi = shell_exec("git checkout -- ../app/Controllers/Api/V1/SalesController.php 2>&1");
$gitStatus  = shell_exec("git status 2>&1");

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurar Controladores de Ventas | Codex</title>
    <!-- Google Fonts & Bootstrap Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: rgba(20, 26, 46, 0.6);
            --border-color: rgba(255, 255, 255, 0.08);
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --accent-color: #6366f1;
            --accent-hover: #4f46e5;
            --success-color: #10b981;
            --glow-color: rgba(99, 102, 241, 0.15);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.05) 0px, transparent 50%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            overflow-x: hidden;
        }

        .container {
            width: 100%;
            max-width: 680px;
            perspective: 1000px;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 2.5rem;
            backdrop-filter: blur(16px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3), 0 0 50px var(--glow-color);
            animation: cardAppear 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes cardAppear {
            from {
                opacity: 0;
                transform: translateY(30px) rotateX(-5deg);
            }
            to {
                opacity: 1;
                transform: translateY(0) rotateX(0deg);
            }
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .icon-wrapper {
            width: 64px;
            height: 64px;
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
            color: var(--accent-color);
            font-size: 28px;
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.2);
        }

        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #ffffff 0%, #a5b4fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            font-size: 0.95rem;
            color: var(--text-secondary);
        }

        .section-title {
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--accent-color);
            margin: 1.5rem 0 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .console-box {
            background: rgba(10, 12, 22, 0.8);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 0.875rem;
            line-height: 1.5;
            color: #38bdf8;
            overflow-x: auto;
            max-height: 180px;
            box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.8);
        }

        .console-box.status-box {
            color: #a7f3d0;
        }

        .btn-container {
            margin-top: 2.25rem;
            display: flex;
            gap: 1rem;
        }

        .btn {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--accent-color);
            border: none;
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .btn-primary:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.08);
            color: white;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="header">
            <div class="icon-wrapper">
                <i class="bi bi-arrow-counterclockwise"></i>
            </div>
            <h1>Controladores Restaurados</h1>
            <p class="subtitle">Se han descartado los cambios locales no confirmados sobre los controladores de ventas.</p>
        </div>

        <div class="section-title">
            <i class="bi bi-terminal"></i> Comando de restauración
        </div>
        <div class="console-box">
$ git checkout -- app/Controllers/SalesController.php
<?= esc(trim((string)$restoreWeb) ?: 'OK') ?>


$ git checkout -- app/Controllers/Api/V1/SalesController.php
<?= esc(trim((string)$restoreApi) ?: 'OK') ?>
        </div>

        <div class="section-title">
            <i class="bi bi-info-circle"></i> Estado actual de Git
        </div>
        <div class="console-box status-box">
<?= esc(trim((string)$gitStatus)) ?>
        </div>

        <div class="btn-container">
            <a href="/dashboard" class="btn btn-primary">
                <i class="bi bi-house"></i> Ir al Dashboard
            </a>
            <a href="javascript:window.location.reload();" class="btn btn-secondary">
                <i class="bi bi-arrow-clockwise"></i> Re-ejecutar
            </a>
        </div>
    </div>
</div>

</body>
</html>
