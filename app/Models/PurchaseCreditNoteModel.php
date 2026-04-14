<?php

namespace App\Models;

class PurchaseCreditNoteModel extends BaseUuidModel
{
    protected $table = 'purchase_credit_notes';
    protected $allowedFields = [
        'id', 'company_id', 'supplier_id', 'purchase_invoice_id', 'credit_note_number', 'amount',
        'issue_date', 'status', 'notes', 'created_by', 'created_at', 'updated_at',
    ];
}
