<?php

namespace App\Libraries;

/**
 * CodexAssistService — AI-powered internal agent for user guidance and intelligent alerts.
 * Supports Ollama (local) or OpenAI (cloud) backends.
 */
class CodexAssistService
{
    private string $provider; // 'ollama' or 'openai'
    private string $model;
    private string $apiUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->provider = env('codex.assist.provider', 'ollama');
        $this->model    = env('codex.assist.model', 'llama3.2');
        $this->apiUrl   = env('codex.assist.url', 'http://localhost:11434');
        $this->apiKey   = env('codex.assist.api_key', '');
    }

    /**
     * Ask Codex Assist a question about the ERP system.
     */
    public function ask(string $question, array $context = []): array
    {
        $systemPrompt = $this->buildSystemPrompt($context);
        $startTime = microtime(true);

        try {
            if ($this->provider === 'openai') {
                $response = $this->callOpenAI($systemPrompt, $question);
            } else {
                $response = $this->callOllama($systemPrompt, $question);
            }

            $duration = round((microtime(true) - $startTime) * 1000);

            $this->logInteraction($question, $response, $context, $duration);

            return [
                'answer'   => $response,
                'provider' => $this->provider,
                'model'    => $this->model,
                'duration_ms' => $duration,
            ];

        } catch (\Throwable $e) {
            log_message('error', 'CodexAssist error: ' . $e->getMessage());
            return [
                'answer'   => $this->fallbackAnswer($question, $context),
                'provider' => 'fallback',
                'model'    => 'rule-based',
                'error'    => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze business data and generate proactive insights/alerts.
     */
    public function analyzeAlerts(string $companyId): array
    {
        $alerts = [];
        $db = db_connect();

        // 1. Overdue receivables
        $overdueReceivables = $db->table('sales')
            ->where('company_id', $companyId)->where('status', 'confirmed')
            ->where('payment_status !=', 'paid')->where('due_date <', date('Y-m-d'))
            ->selectSum('total')->selectCount('id', 'count')
            ->get()->getRowArray();

        if ((int)($overdueReceivables['count'] ?? 0) > 0) {
            $alerts[] = [
                'type'     => 'warning',
                'category' => 'cash_flow',
                'title'    => 'Cuentas por cobrar vencidas',
                'message'  => sprintf('%d facturas vencidas por $%s. Revisar cobranza.', $overdueReceivables['count'], number_format((float)($overdueReceivables['total'] ?? 0), 2, ',', '.')),
                'action'   => '/ventas/cobros',
                'priority' => 'high',
            ];
        }

        // 2. Low stock products
        $lowStock = $db->query("SELECT COUNT(*) AS cnt FROM products WHERE company_id = ? AND active = 1 AND stock <= min_stock AND min_stock > 0", [$companyId])->getRowArray();
        if ((int)($lowStock['cnt'] ?? 0) > 0) {
            $alerts[] = [
                'type' => 'warning', 'category' => 'inventory',
                'title' => 'Productos con stock bajo',
                'message' => sprintf('%d productos por debajo del stock minimo.', $lowStock['cnt']),
                'action' => '/inventario', 'priority' => 'medium',
            ];
        }

        // 3. Pending purchase orders
        $pendingPO = $db->table('purchase_orders')->where('company_id', $companyId)->where('status', 'pending')->countAllResults();
        if ($pendingPO > 5) {
            $alerts[] = [
                'type' => 'info', 'category' => 'purchases',
                'title' => 'Ordenes de compra pendientes',
                'message' => sprintf('%d ordenes de compra sin confirmar.', $pendingPO),
                'action' => '/compras/ordenes', 'priority' => 'low',
            ];
        }

        // 4. Certificate expiring soon
        try {
            $settings = $db->table('company_settings')->where('company_id', $companyId)->get()->getResultArray();
            $settingsMap = [];
            foreach ($settings as $s) $settingsMap[$s['key']] = $s['value'];

            $certPath = $settingsMap['certificate_path'] ?? '';
            if ($certPath !== '' && is_file($certPath)) {
                $cert = @openssl_x509_read(file_get_contents($certPath));
                if ($cert) {
                    $parsed = openssl_x509_parse($cert);
                    $daysLeft = isset($parsed['validTo_time_t']) ? (int)floor(($parsed['validTo_time_t'] - time()) / 86400) : null;
                    if ($daysLeft !== null && $daysLeft <= 30) {
                        $alerts[] = [
                            'type' => 'caution', 'category' => 'fiscal',
                            'title' => 'Certificado AFIP por vencer',
                            'message' => sprintf('El certificado fiscal vence en %d dias. Renovar antes del vencimiento.', $daysLeft),
                            'action' => '/configuracion', 'priority' => 'critical',
                        ];
                    }
                }
            }
        } catch (\Throwable $e) { /* skip */ }

        // 5. Sales trend analysis
        $last7 = (float)($db->query("SELECT SUM(total) AS t FROM sales WHERE company_id = ? AND status='confirmed' AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)", [$companyId])->getRowArray()['t'] ?? 0);
        $prev7 = (float)($db->query("SELECT SUM(total) AS t FROM sales WHERE company_id = ? AND status='confirmed' AND sale_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND DATE_SUB(CURDATE(), INTERVAL 7 DAY)", [$companyId])->getRowArray()['t'] ?? 0);

        if ($prev7 > 0 && ($last7 / $prev7) < 0.7) {
            $drop = round((1 - $last7 / $prev7) * 100);
            $alerts[] = [
                'type' => 'warning', 'category' => 'sales',
                'title' => 'Caida en ventas',
                'message' => sprintf('Las ventas cayeron un %d%% vs la semana anterior.', $drop),
                'action' => '/ventas/reportes', 'priority' => 'high',
            ];
        }

        return $alerts;
    }

    /**
     * Generate guided workflow for a user action.
     */
    public function getGuide(string $topic): array
    {
        $guides = [
            'new_sale' => [
                'title' => 'Crear una nueva venta',
                'steps' => [
                    ['step' => 1, 'title' => 'Seleccionar cliente', 'description' => 'Elegí un cliente existente o creá uno nuevo con CUIT/DNI.'],
                    ['step' => 2, 'title' => 'Agregar productos', 'description' => 'Buscá por nombre o código. El precio se carga automáticamente.'],
                    ['step' => 3, 'title' => 'Verificar impuestos', 'description' => 'El IVA se calcula automáticamente según la alícuota del producto.'],
                    ['step' => 4, 'title' => 'Confirmar', 'description' => 'Al confirmar se descuenta stock, se genera asiento contable y se solicita CAE a AFIP.'],
                ],
            ],
            'new_purchase' => [
                'title' => 'Registrar una compra',
                'steps' => [
                    ['step' => 1, 'title' => 'Crear orden de compra', 'description' => 'Seleccioná proveedor y productos necesarios.'],
                    ['step' => 2, 'title' => 'Confirmar recepción', 'description' => 'Al recibir mercadería, registrá un remito de ingreso.'],
                    ['step' => 3, 'title' => 'Cargar factura', 'description' => 'Ingresá la factura del proveedor para generar cuenta por pagar.'],
                    ['step' => 4, 'title' => 'Registrar pago', 'description' => 'Se calculan retenciones automáticamente y se genera asiento.'],
                ],
            ],
            'fiscal_setup' => [
                'title' => 'Configurar facturación electrónica',
                'steps' => [
                    ['step' => 1, 'title' => 'Obtener certificado', 'description' => 'Ingresá a AFIP con clave fiscal y generá un certificado en "Administración de certificados".'],
                    ['step' => 2, 'title' => 'Subir archivos', 'description' => 'Cargá el certificado (.crt) y la clave privada (.key) en Configuración > ARCA.'],
                    ['step' => 3, 'title' => 'Configurar CUIT', 'description' => 'Ingresá el CUIT de la empresa y la condición frente al IVA.'],
                    ['step' => 4, 'title' => 'Homologar', 'description' => 'Probá en ambiente de homologación antes de pasar a producción.'],
                ],
            ],
            'accounting' => [
                'title' => 'Contabilidad básica',
                'steps' => [
                    ['step' => 1, 'title' => 'Plan de cuentas', 'description' => 'Configurá el plan de cuentas jerárquico en Contabilidad > Plan de cuentas.'],
                    ['step' => 2, 'title' => 'Mapeo automático', 'description' => 'Asigná cuentas para ventas, compras, IVA débito/crédito en Configuración.'],
                    ['step' => 3, 'title' => 'Asientos automáticos', 'description' => 'Las ventas y compras generan asientos automáticamente al confirmarse.'],
                    ['step' => 4, 'title' => 'Reportes', 'description' => 'Consultá Balance de Sumas y Saldos, Balance General y Estado de Resultados.'],
                ],
            ],
        ];

        return $guides[$topic] ?? [
            'title' => 'Guía no encontrada',
            'steps' => [['step' => 1, 'title' => 'Contactar soporte', 'description' => 'Consultá al administrador del sistema para ayuda con: ' . $topic]],
        ];
    }

    // ── Private ──────────────────────────────────────────

    private function buildSystemPrompt(array $context): string
    {
        $base = "Eres Codex Assist, el asistente inteligente del ERP Codex. Respondés en español argentino, de forma clara y concisa. ";
        $base .= "El ERP maneja: Ventas, Compras, Inventario, Caja, Contabilidad, Facturación Electrónica AFIP (ARCA). ";
        $base .= "Siempre mencioná los pasos necesarios y qué módulo del sistema usar.";

        if (!empty($context['company'])) $base .= " Empresa: {$context['company']}.";
        if (!empty($context['role']))    $base .= " Rol del usuario: {$context['role']}.";
        if (!empty($context['module']))  $base .= " Módulo actual: {$context['module']}.";

        return $base;
    }

    private function callOllama(string $system, string $question): string
    {
        $ch = curl_init($this->apiUrl . '/api/chat');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POSTFIELDS     => json_encode([
                'model'    => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $question],
                ],
                'stream' => false,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            throw new \RuntimeException("Ollama returned HTTP {$httpCode}");
        }

        $data = json_decode($response, true);
        return $data['message']['content'] ?? 'Sin respuesta del asistente.';
    }

    private function callOpenAI(string $system, string $question): string
    {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $this->apiKey],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POSTFIELDS     => json_encode([
                'model'    => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $question],
                ],
                'max_tokens' => 1000,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            throw new \RuntimeException("OpenAI returned HTTP {$httpCode}");
        }

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? 'Sin respuesta.';
    }

    private function fallbackAnswer(string $question, array $context): string
    {
        $q = mb_strtolower($question);

        if (str_contains($q, 'factura') || str_contains($q, 'cae')) return 'Para facturar, andá a Ventas > Nueva Venta, agregá productos y confirmá. El CAE se solicita automáticamente a AFIP si tenés la integración ARCA configurada.';
        if (str_contains($q, 'stock') || str_contains($q, 'inventario')) return 'Podés ver el stock en Inventario > Productos. Los movimientos se registran automáticamente con ventas y compras. Para ajustes manuales usá Inventario > Movimientos.';
        if (str_contains($q, 'cobr') || str_contains($q, 'pag')) return 'Para registrar un cobro andá a Ventas > Cobros. Para pagos a proveedores, Compras > Pagos. Las retenciones se calculan automáticamente.';
        if (str_contains($q, 'reporte') || str_contains($q, 'balance')) return 'Los reportes contables están en Contabilidad: Balance de Sumas y Saldos, Balance General e Estado de Resultados. Los reportes de ventas en Ventas > Reportes.';

        return 'Esta pregunta requiere el asistente IA. Verificá que Ollama esté corriendo o configurá OpenAI en el archivo .env.';
    }

    private function logInteraction(string $question, string $answer, array $context, int $durationMs): void
    {
        try {
            db_connect()->table('codex_assist_log')->insert([
                'id' => app_uuid(), 'user_id' => auth_user()['id'] ?? null,
                'company_id' => $context['company_id'] ?? null,
                'question' => $question, 'answer' => $answer,
                'provider' => $this->provider, 'model' => $this->model,
                'duration_ms' => $durationMs, 'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) { /* table may not exist */ }
    }
}
