<?php

namespace App\Controllers;

use App\Libraries\LibroIvaDigitalService;
use App\Libraries\SicoreService;
use App\Libraries\WithholdingService;
use CodeIgniter\HTTP\RedirectResponse;

class TaxController extends BaseController
{
    private function taxContext(): array|RedirectResponse
    {
        $user = $this->currentUser();
        if (!$user) return redirect()->to(site_url('login'));
        $companyId = $this->resolveActiveCompanyId();
        if (!$companyId) return redirect()->to(site_url('dashboard'))->with('error', 'Seleccione una empresa.');
        $company = db_connect()->table('companies')->where('id', $companyId)->get()->getRowArray();
        if (!$company) return redirect()->to(site_url('dashboard'))->with('error', 'Empresa no encontrada.');
        return ['user' => $user, 'company' => $company];
    }

    public function index()
    {
        $ctx = $this->taxContext();
        if ($ctx instanceof RedirectResponse) return $ctx;

        $companyId = $ctx['company']['id'];
        $from = $this->request->getGet('from') ?: date('Y-m-01');
        $to   = $this->request->getGet('to')   ?: date('Y-m-d');

        $libroIva = new LibroIvaDigitalService();
        $sicore   = new SicoreService();

        return view('taxes/index', $this->taxViewData($ctx, [
            'pageTitle'    => 'Impuestos',
            'filters'      => ['from' => $from, 'to' => $to],
            'ivaVentas'    => $libroIva->ventasReport($companyId, $from, $to),
            'ivaCompras'   => $libroIva->comprasReport($companyId, $from, $to),
            'sicoreSummary' => $sicore->periodSummary($companyId, $from, $to),
        ]));
    }

    // ── Libro IVA Digital exports ────────────────────────
    public function exportIvaVentasTxt()
    {
        $ctx = $this->taxContext();
        if ($ctx instanceof RedirectResponse) return $ctx;

        $from = $this->request->getGet('from') ?: date('Y-m-01');
        $to   = $this->request->getGet('to')   ?: date('Y-m-d');
        $txt  = (new LibroIvaDigitalService())->exportVentasTxt($ctx['company']['id'], $from, $to);
        $period = str_replace('-', '', substr($from, 0, 7));

        return $this->response
            ->setHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="LIBRO_IVA_VENTAS_' . $period . '.txt"')
            ->setBody($txt);
    }

    public function exportIvaVentasCbte()
    {
        $ctx = $this->taxContext();
        if ($ctx instanceof RedirectResponse) return $ctx;

        $from = $this->request->getGet('from') ?: date('Y-m-01');
        $to   = $this->request->getGet('to')   ?: date('Y-m-d');
        $txt  = (new LibroIvaDigitalService())->exportVentasCbteTxt($ctx['company']['id'], $from, $to);
        $period = str_replace('-', '', substr($from, 0, 7));

        return $this->response
            ->setHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="LIBRO_IVA_VENTAS_CBTE_' . $period . '.txt"')
            ->setBody($txt);
    }

    public function exportIvaVentasAlicuotas()
    {
        $ctx = $this->taxContext();
        if ($ctx instanceof RedirectResponse) return $ctx;

        $from = $this->request->getGet('from') ?: date('Y-m-01');
        $to   = $this->request->getGet('to')   ?: date('Y-m-d');
        $txt  = (new LibroIvaDigitalService())->exportVentasAlicuotasTxt($ctx['company']['id'], $from, $to);
        $period = str_replace('-', '', substr($from, 0, 7));

        return $this->response
            ->setHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="LIBRO_IVA_VENTAS_ALICUOTAS_' . $period . '.txt"')
            ->setBody($txt);
    }

    public function exportIvaComprasTxt()
    {
        $ctx = $this->taxContext();
        if ($ctx instanceof RedirectResponse) return $ctx;

        $from = $this->request->getGet('from') ?: date('Y-m-01');
        $to   = $this->request->getGet('to')   ?: date('Y-m-d');
        $txt  = (new LibroIvaDigitalService())->exportComprasTxt($ctx['company']['id'], $from, $to);
        $period = str_replace('-', '', substr($from, 0, 7));

        return $this->response
            ->setHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="LIBRO_IVA_COMPRAS_' . $period . '.txt"')
            ->setBody($txt);
    }

    public function exportIvaComprasCbte()
    {
        $ctx = $this->taxContext();
        if ($ctx instanceof RedirectResponse) return $ctx;

        $from = $this->request->getGet('from') ?: date('Y-m-01');
        $to   = $this->request->getGet('to')   ?: date('Y-m-d');
        $txt  = (new LibroIvaDigitalService())->exportComprasCbteTxt($ctx['company']['id'], $from, $to);
        $period = str_replace('-', '', substr($from, 0, 7));

        return $this->response
            ->setHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="LIBRO_IVA_COMPRAS_CBTE_' . $period . '.txt"')
            ->setBody($txt);
    }

    public function exportIvaComprasAlicuotas()
    {
        $ctx = $this->taxContext();
        if ($ctx instanceof RedirectResponse) return $ctx;

        $from = $this->request->getGet('from') ?: date('Y-m-01');
        $to   = $this->request->getGet('to')   ?: date('Y-m-d');
        $txt  = (new LibroIvaDigitalService())->exportComprasAlicuotasTxt($ctx['company']['id'], $from, $to);
        $period = str_replace('-', '', substr($from, 0, 7));

        return $this->response
            ->setHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="LIBRO_IVA_COMPRAS_ALICUOTAS_' . $period . '.txt"')
            ->setBody($txt);
    }

    // ── SICORE exports ──────────────────────────────────
    public function exportSicoreRetencionesTxt()
    {
        $ctx = $this->taxContext();
        if ($ctx instanceof RedirectResponse) return $ctx;

        $from = $this->request->getGet('from') ?: date('Y-m-01');
        $to   = $this->request->getGet('to')   ?: date('Y-m-d');
        $txt  = (new SicoreService())->exportWithholdingsTxt($ctx['company']['id'], $from, $to);

        return $this->response
            ->setHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="SICORE_RETENCIONES_' . str_replace('-', '', $from) . '.txt"')
            ->setBody($txt);
    }

    public function exportSicorePercepcionesTxt()
    {
        $ctx = $this->taxContext();
        if ($ctx instanceof RedirectResponse) return $ctx;

        $from = $this->request->getGet('from') ?: date('Y-m-01');
        $to   = $this->request->getGet('to')   ?: date('Y-m-d');
        $txt  = (new SicoreService())->exportPerceptionsTxt($ctx['company']['id'], $from, $to);

        return $this->response
            ->setHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="SICORE_PERCEPCIONES_' . str_replace('-', '', $from) . '.txt"')
            ->setBody($txt);
    }

    private function taxViewData(array $ctx, array $extra = []): array
    {
        return array_merge([
            'context' => $ctx,
            'companies' => $this->activeCompanies(),
            'selectedCompanyId' => $ctx['company']['id'],
            'companyId' => $ctx['company']['id'],
        ], $extra);
    }
}
