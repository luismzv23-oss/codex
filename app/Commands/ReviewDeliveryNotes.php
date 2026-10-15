<?php
namespace App\Commands;

use App\Libraries\{DeliveryNoteService, DeliveryNoteInvoicingService};
use CodeIgniter\CLI\{BaseCommand, CLI};

/** Read-only reconciliation report; never infer or backfill historical item links. */
class ReviewDeliveryNotes extends BaseCommand
{
    protected $group = 'Ventas';
    protected $name = 'ventas:remitos-revisar';
    protected $description = 'Revisa remitos y detecta facturas anteriores o movimientos financieros que requieren conciliación.';
    protected $options = ['--company' => 'Limita la revisión al UUID de una empresa.'];

    public function run(array $params)
    {
        $db = db_connect(); $notes = new DeliveryNoteService(); $rows = []; $checked = 0;
        $company = CLI::getOption('company');
        $sales = $db->table('sales s')->select('s.id, s.company_id')->join('sales_document_types d', 'd.id=s.document_type_id')
            ->groupStart()->where('d.category', 'delivery_note')->orWhereIn('d.code', ['REMITO', 'RTO', 'RM'])->groupEnd();
        $cycle = $db->table('sales_delivery_notes')->select('id, company_id');
        if ($company) { $sales->where('s.company_id', $company); $cycle->where('company_id', $company); }
        foreach (['sale' => $sales->get()->getResultArray(), 'cycle' => $cycle->get()->getResultArray()] as $origin => $documents) {
            foreach ($documents as $document) {
                $checked++;
                try {
                    $note = $notes->summary($document['company_id'], $origin, $document['id']);
                    if ($note['status'] === 'cancelled') continue;
                    $reasons = [];
                    if ($note['legacy']) $reasons[] = 'Documentos anteriores: ' . implode(', ', array_map(static fn($i) => $i['sale_number'] ?? $i['id'], $note['legacy']));
                    try { (new DeliveryNoteInvoicingService())->assertSourceFinancials($document['company_id'], $note); }
                    catch (\Throwable $e) { $reasons[] = $e->getMessage(); }
                    if ($reasons) $rows[] = [$document['company_id'], $origin, $note['number'], implode(' ', $reasons)];
                } catch (\Throwable $e) { $rows[] = [$document['company_id'], $origin, $document['id'], $e->getMessage()]; }
            }
        }
        CLI::write('Remitos revisados: ' . $checked . '. Casos a conciliar: ' . count($rows) . '. No se modificaron datos.');
        if ($rows) CLI::table($rows, ['Empresa', 'Origen', 'Remito', 'Revisión']);
    }
}
