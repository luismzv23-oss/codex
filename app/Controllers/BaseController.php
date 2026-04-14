<?php

namespace App\Controllers;

use App\Models\CurrencyModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    protected $helpers = ['url', 'form', 'auth'];
    protected $session;
    protected ?array $currentUser = null;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        $this->session = service('session');
        $this->currentUser = auth_user();
    }

    protected function currentUser(): ?array
    {
        return $this->currentUser;
    }

    protected function isSuperadmin(): bool
    {
        return ($this->currentUser['role_slug'] ?? null) === 'superadmin';
    }

    protected function roleSlug(): ?string
    {
        return $this->currentUser['role_slug'] ?? null;
    }

    protected function companyId(): ?string
    {
        return $this->currentUser['company_id'] ?? null;
    }

    protected function isMainBranchUser(): bool
    {
        return ($this->currentUser['branch_code'] ?? null) === 'MAIN';
    }

    protected function canMutateUsers(): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return ($this->currentUser['role_slug'] ?? null) === 'admin' && $this->isMainBranchUser();
    }

    protected function canDisableOrDeleteUsers(): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return ($this->currentUser['role_slug'] ?? null) === 'admin' && $this->isMainBranchUser();
    }

    protected function canManageSystems(): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return $this->roleSlug() === 'admin' && $this->isMainBranchUser();
    }

    protected function isPopupRequest(): bool
    {
        return $this->request->getGet('popup') === '1' || $this->request->getPost('popup') === '1';
    }

    protected function popupOrRedirect(string $redirectUrl, string $message)
    {
        if ($this->isPopupRequest()) {
            return view('layouts/popup_close', [
                'redirectUrl' => $redirectUrl,
                'message' => $message,
            ]);
        }

        return redirect()->to($redirectUrl)->with('message', $message);
    }

    protected function currencyOptions(): array
    {
        return $this->companyCurrencyOptions();
    }

    protected function companyCurrencyOptions(?string $companyId = null, ?string $selectedCode = null): array
    {
        if (! $companyId) {
            return $this->globalCurrencyCatalogOptions($selectedCode);
        }

        $currencyModel = new CurrencyModel();
        $options = [];
        $companyCurrencies = $currencyModel
            ->where('company_id', $companyId)
            ->where('active', 1)
            ->orderBy('code', 'ASC')
            ->findAll();

        foreach ($companyCurrencies as $currency) {
            $options[$currency['code']] = $this->formatCurrencyOptionLabel($currency);
        }

        if ($selectedCode && ! array_key_exists($selectedCode, $options)) {
            $selectedCurrency = $currencyModel
                ->where('company_id', $companyId)
                ->where('code', $selectedCode)
                ->first();

            if ($selectedCurrency) {
                $options[$selectedCode] = $this->formatCurrencyOptionLabel($selectedCurrency);
            }
        }

        return $options;
    }

    protected function isAllowedCurrencyCode(string $currencyCode, ?string $companyId = null, ?string $selectedCode = null): bool
    {
        return array_key_exists($currencyCode, $this->companyCurrencyOptions($companyId, $selectedCode));
    }

    private function formatCurrencyOptionLabel(array $currency): string
    {
        $code = trim((string) ($currency['code'] ?? ''));
        $name = trim((string) ($currency['name'] ?? ''));
        $symbol = trim((string) ($currency['symbol'] ?? ''));

        $parts = array_filter([$code, $name], static fn ($value) => $value !== '');
        $label = implode(' - ', $parts);

        if ($symbol !== '') {
            $label .= ' (' . $symbol . ')';
        }

        return $label !== '' ? $label : $code;
    }

    private function globalCurrencyCatalogOptions(?string $selectedCode = null): array
    {
        $currencyModel = new CurrencyModel();
        $options = [];
        $globalCurrencies = $currencyModel
            ->select('code, name, symbol')
            ->where('active', 1)
            ->groupBy('code, name, symbol')
            ->orderBy('code', 'ASC')
            ->findAll();

        foreach ($globalCurrencies as $currency) {
            $options[$currency['code']] = $this->formatCurrencyOptionLabel($currency);
        }

        if ($selectedCode && ! array_key_exists($selectedCode, $options)) {
            $selectedCurrency = $currencyModel
                ->where('code', $selectedCode)
                ->orderBy('active', 'DESC')
                ->first();

            if ($selectedCurrency) {
                $options[$selectedCode] = $this->formatCurrencyOptionLabel($selectedCurrency);
            }
        }

        if ($options === []) {
            $options = [
                'ARS' => 'ARS - Pesos Argentinos (ARS)',
            ];
        }

        return $options;
    }

    protected function systemEntryUrl(?string $entryUrl): string
    {
        $entryUrl = trim((string) $entryUrl);

        if ($entryUrl === '') {
            return '#';
        }

        if (preg_match('#^https?://#i', $entryUrl) === 1) {
            return $entryUrl;
        }

        return site_url(ltrim($entryUrl, '/'));
    }
}
