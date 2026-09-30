<?php

namespace App\Libraries;

final class PosTicketDesign
{
    public const BLOCKS = [
        'show_header'=>'Nombre de la empresa', 'show_subtitle'=>'Subtítulo',
        'show_address'=>'Dirección y teléfono de la empresa', 'show_tax_id'=>'CUIT de la empresa',
        'show_custom_header'=>'Textos fiscales de cabecera', 'show_document'=>'Tipo, letra y número',
        'show_dates'=>'Fechas de emisión y vencimiento', 'show_customer'=>'Datos del cliente',
        'show_condition'=>'Condición de venta', 'show_user'=>'Vendedor',
        'show_sku'=>'Columna SKU', 'show_brand'=>'Marca', 'show_item_breakdown'=>'Desglose de cantidades',
        'show_quantity'=>'Columna cantidad', 'show_unit_price'=>'Columna precio unitario',
        'show_item_tax'=>'Columna IVA %', 'show_item_discount'=>'Columna descuento %',
        'show_subtotal'=>'Subtotal neto', 'show_taxes'=>'IVA discriminado',
        'show_discounts'=>'Descuentos', 'show_surcharges'=>'Recargos por medio de pago',
        'show_payments'=>'Medios de pago', 'show_currency'=>'Moneda',
        'show_authorization'=>'CAE y vencimiento', 'show_qr'=>'QR fiscal',
        'show_notes'=>'Observaciones de la venta', 'show_footer'=>'Leyenda al pie',
        'show_custom_footer'=>'Textos inferiores personalizados',
    ];

    public static function defaults(): array { return array_fill_keys(array_keys(self::BLOCKS), 1); }

    public static function preview(array $company): array
    {
        return [
            'preview'=>true, 'company'=>$company, 'ticketSettings'=>[],
            'documentType'=>['name'=>'FACTURA', 'letter'=>'B'], 'conditionName'=>'Contado', 'creatorName'=>'Vendedor de ejemplo',
            'sale'=>['sale_number'=>'0002-00003555','issue_date'=>'2026-09-30','due_date'=>'2026-09-30', 'customer_name_snapshot'=>'Cliente de ejemplo','customer_document_snapshot'=>'12345678','customer_tax_profile'=>'Consumidor Final','subtotal'=>10000,'total'=>12350,'currency_code'=>'ARS','payment_surcharge_amount'=>250,'notes'=>'Observaciones de la venta.'],
            'customer'=>['address'=>'Dirección del cliente', 'phone'=>'Teléfono del cliente'],
            'items'=>[['sku'=>'PROD-001','product_name'=>'Producto de ejemplo','brand'=>'Marca','quantity'=>2,'unit_price'=>6050,'line_total'=>12100,'tax_rate'=>21,'tax_total'=>2100]],
            'payments'=>[['payment_method_code'=>'TARJETA','amount'=>12350,'surcharge_amount'=>250]],
            'fiscal'=>['cae'=>'EJEMPLO SIN VALIDEZ','caeDueDate'=>'2026-10-10','documentTypeCode'=>'006'],
            'qrDataUri'=>self::qrDataUri('VISTA PREVIA SIN VALIDEZ FISCAL'),
        ];
    }

    public static function qrDataUri(?string $url): ?string
    {
        if (!$url) { return null; }
        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(180),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        );
        return 'data:image/svg+xml;base64,' . base64_encode((new \BaconQrCode\Writer($renderer))->writeString($url));
    }
}
