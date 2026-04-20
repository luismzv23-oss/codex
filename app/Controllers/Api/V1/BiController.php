<?php

namespace App\Controllers\Api\V1;

use App\Libraries\BusinessIntelligenceService;
use App\Libraries\CodexAssistService;
use App\Libraries\NotificationService;
use App\Libraries\LibroIvaDigitalService;

class BiController extends BaseApiController
{
    public function executive()
    {
        $period = $this->request->getGet('period') ?? 'month';
        $bi = new BusinessIntelligenceService();
        return $this->success($bi->executiveDashboard($this->apiCompanyId(), $period));
    }

    public function forecast()
    {
        $days = (int)($this->request->getGet('days') ?? 30);
        $bi = new BusinessIntelligenceService();
        return $this->success($bi->salesForecast($this->apiCompanyId(), $days));
    }

    public function cashFlow()
    {
        $days = (int)($this->request->getGet('days') ?? 30);
        $bi = new BusinessIntelligenceService();
        return $this->success($bi->cashFlowProjection($this->apiCompanyId(), $days));
    }

    public function alerts()
    {
        $assist = new CodexAssistService();
        return $this->success($assist->analyzeAlerts($this->apiCompanyId()));
    }

    public function libroIvaVentas()
    {
        $from = $this->request->getGet('from') ?? date('Y-m-01');
        $to   = $this->request->getGet('to') ?? date('Y-m-d');
        return $this->success((new LibroIvaDigitalService())->ventasReport($this->apiCompanyId(), $from, $to));
    }

    public function libroIvaCompras()
    {
        $from = $this->request->getGet('from') ?? date('Y-m-01');
        $to   = $this->request->getGet('to') ?? date('Y-m-d');
        return $this->success((new LibroIvaDigitalService())->comprasReport($this->apiCompanyId(), $from, $to));
    }

    public function exportLibroIva()
    {
        $type = $this->request->getGet('type') ?? 'ventas';
        $from = $this->request->getGet('from') ?? date('Y-m-01');
        $to   = $this->request->getGet('to') ?? date('Y-m-d');
        $libro = new LibroIvaDigitalService();

        $content = $type === 'compras'
            ? $libro->exportComprasTxt($this->apiCompanyId(), $from, $to)
            : $libro->exportVentasTxt($this->apiCompanyId(), $from, $to);

        return $this->response->setStatusCode(200)
            ->setHeader('Content-Type', 'text/plain')
            ->setHeader('Content-Disposition', "attachment; filename=libro_iva_{$type}_{$from}_{$to}.txt")
            ->setBody($content);
    }
}
