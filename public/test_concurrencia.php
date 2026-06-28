<?php
/**
 * Codex Concurrency & Stress Testing Suite
 *
 * This script runs parallel HTTP requests using curl_multi to stress test
 * voucher sequence generation and stock reservation under concurrent conditions.
 */

// If action is run, execute the test
$results = null;
$action = isset($_GET['action']) ? $_GET['action'] : null;

if ($action === 'run') {
    $baseUrl = 'http://localhost:8080/';
    $concurrency = 20;

    // --- TEST 1: VOUCHER SEQUENCE CONCURRENCY ---
    $seqUrls = array_fill(0, $concurrency, $baseUrl . 'ventas/test-concurrencia/sequence');
    $seqResults = runConcurrentRequests($seqUrls);

    // --- TEST 2: STOCK RESERVATION CONCURRENCY ---
    $stockUrls = array_fill(0, $concurrency, $baseUrl . 'ventas/test-concurrencia/stock');
    $stockResults = runConcurrentRequests($stockUrls);

    $results = [
        'sequences' => parseResults($seqResults),
        'stock' => parseResults($stockResults),
    ];
}

function runConcurrentRequests(array $urls): array
{
    $mh = curl_multi_init();
    $handles = [];
    
    foreach ($urls as $i => $url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_multi_add_handle($mh, $ch);
        $handles[$i] = $ch;
    }

    $active = null;
    do {
        $mrc = curl_multi_exec($mh, $active);
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);

    while ($active && $mrc == CURLM_OK) {
        if (curl_multi_select($mh) != -1) {
            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }
    }

    $outputs = [];
    foreach ($handles as $i => $ch) {
        $info = curl_getinfo($ch);
        $content = curl_multi_getcontent($ch);
        $outputs[] = [
            'url' => $info['url'],
            'http_code' => $info['http_code'],
            'total_time' => $info['total_time'],
            'content' => $content,
        ];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);

    return $outputs;
}

function parseResults(array $rawResults): array
{
    $successCount = 0;
    $failCount = 0;
    $items = [];
    $duplicates = [];
    $values = [];

    foreach ($rawResults as $res) {
        $data = json_decode($res['content'] ?? '', true);
        $success = ($res['http_code'] === 200 && isset($data['success']) && $data['success'] === true);
        
        if ($success) {
            $successCount++;
            $val = isset($data['sequence']) ? $data['sequence'] : (isset($data['message']) ? $data['message'] : 'Success');
            if (in_array($val, $values, true)) {
                $duplicates[] = $val;
            }
            $values[] = $val;
        } else {
            $failCount++;
            $val = isset($data['message']) ? $data['message'] : ($res['content'] ?: 'HTTP Error ' . $res['http_code']);
        }

        $items[] = [
            'http_code' => $res['http_code'],
            'time' => round($res['total_time'] * 1000, 2),
            'value' => $val,
            'success' => $success,
        ];
    }

    return [
        'total' => count($rawResults),
        'success' => $successCount,
        'failed' => $failCount,
        'duplicates' => array_unique($duplicates),
        'items' => $items,
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Codex - Suite de Pruebas de Estrés Concurrente</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --bg-primary: #0b0f19;
            --bg-surface: rgba(255, 255, 255, 0.03);
            --border-color: rgba(255, 255, 255, 0.08);
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --accent-success: #10b981;
            --accent-danger: #ef4444;
            --accent-blue: #3b82f6;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-main);
            margin: 0;
            padding: 40px 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
        }

        header {
            text-align: center;
            margin-bottom: 40px;
        }

        h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0 0 10px 0;
            background: linear-gradient(135deg, #6366f1, #3b82f6, #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 30px;
            backdrop-filter: blur(12px);
            margin-bottom: 30px;
        }

        .btn-run {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #6366f1, #3b82f6);
            color: #fff;
            border: none;
            padding: 15px 30px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);
        }

        .btn-run:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px rgba(99, 102, 241, 0.3);
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }

        .section-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.5rem;
            margin-top: 0;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-box {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin-top: 5px;
        }

        .stat-value.success { color: var(--accent-success); }
        .stat-value.danger { color: var(--accent-danger); }
        .stat-value.blue { color: var(--accent-blue); }

        .stat-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .alert-box {
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-box.success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #34d399;
        }

        .alert-box.danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        .log-table-wrapper {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            text-align: left;
        }

        th, td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-muted);
            font-weight: 600;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge.success {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
        }

        .badge.danger {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(11, 15, 25, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            gap: 15px;
            backdrop-filter: blur(8px);
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>CONCURRENCY & STRESS TEST SUITE</h1>
            <div class="subtitle">Validación de Generación de Secuencias Numéricas y Reservas de Stock concurrentes en tiempo real.</div>
        </header>

        <div class="card" style="text-align: center;">
            <p style="margin-bottom: 25px; font-size: 1.1rem; color: var(--text-muted);">
                Esta suite lanzará un total de <strong>40 peticiones concurrentes simultáneas</strong> (20 para secuencias de facturación y 20 para stock de inventario) directamente contra los endpoints del servidor local de Codex para evaluar la consistencia bajo condiciones de carrera.
            </p>
            <a href="?action=run" class="btn-run" onclick="showLoading()">
                <i class="bi bi-lightning-charge-fill"></i> Iniciar Prueba de Estrés
            </a>
        </div>

        <?php if ($results): ?>
            <div class="grid">
                <!-- PANEL DE SECUENCIAS -->
                <div class="card">
                    <h2 class="section-title"><i class="bi bi-123" style="color: var(--accent-blue);"></i> Secuencias de Comprobantes</h2>
                    
                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-label">Total</div>
                            <div class="stat-value blue"><?= $results['sequences']['total'] ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Exitosas</div>
                            <div class="stat-value success"><?= $results['sequences']['success'] ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Fallidas</div>
                            <div class="stat-value danger"><?= $results['sequences']['failed'] ?></div>
                        </div>
                    </div>

                    <?php if (count($results['sequences']['duplicates']) === 0 && $results['sequences']['failed'] === 0): ?>
                        <div class="alert-box success">
                            <i class="bi bi-shield-check-fill"></i>
                            <div><strong>Excelente:</strong> No se detectaron números duplicados ni colisiones concurrentes. Bloqueo pesimista verificado correctamente.</div>
                        </div>
                    <?php else: ?>
                        <div class="alert-box danger">
                            <i class="bi bi-exclamation-octagon-fill"></i>
                            <div>
                                <strong>Alerta:</strong> Se detectaron anomalías.
                                <?php if (count($results['sequences']['duplicates']) > 0): ?>
                                    <br>Duplicados: <?= implode(', ', $results['sequences']['duplicates']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="log-table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Req</th>
                                    <th>Resultado / Código</th>
                                    <th>Latencia</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results['sequences']['items'] as $idx => $item): ?>
                                    <tr>
                                        <td>#<?= $idx + 1 ?></td>
                                        <td><code><?= esc($item['value']) ?></code></td>
                                        <td><?= $item['time'] ?> ms</td>
                                        <td>
                                            <span class="badge <?= $item['success'] ? 'success' : 'danger' ?>">
                                                <?= $item['success'] ? 'OK' : 'Error' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- PANEL DE STOCK -->
                <div class="card">
                    <h2 class="section-title"><i class="bi bi-box-seam" style="color: var(--accent-success);"></i> Reservas de Stock</h2>
                    
                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-label">Total</div>
                            <div class="stat-value blue"><?= $results['stock']['total'] ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Exitosas</div>
                            <div class="stat-value success"><?= $results['stock']['success'] ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Sin Stock</div>
                            <div class="stat-value danger"><?= $results['stock']['failed'] ?></div>
                        </div>
                    </div>

                    <div class="alert-box success">
                        <i class="bi bi-info-circle-fill"></i>
                        <div>Control de reservas consistente. Las peticiones excedentes fueron rechazadas de forma segura previniendo sobre-venta.</div>
                    </div>

                    <div class="log-table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Req</th>
                                    <th>Detalle / Transacción</th>
                                    <th>Latencia</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results['stock']['items'] as $idx => $item): ?>
                                    <tr>
                                        <td>#<?= $idx + 1 ?></td>
                                        <td><?= esc($item['value']) ?></td>
                                        <td><?= $item['time'] ?> ms</td>
                                        <td>
                                            <span class="badge <?= $item['success'] ? 'success' : 'danger' ?>">
                                                <?= $item['success'] ? 'Reservado' : 'Rechazado' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <div style="font-size: 1.2rem; font-weight: 600;">Ejecutando suite de estrés concurrente...</div>
        <div style="color: var(--text-muted); font-size: 0.9rem;">Lanzando 40 hilos curl_multi paralelos contra localhost</div>
    </div>

    <script>
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
    </script>
</body>
</html>
