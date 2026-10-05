<?php

namespace App\Libraries;

final class KioskTicketDesign
{
    public const BLOCKS = [
        'show_header' => 'Nombre de la empresa',
        'show_subtitle' => 'Sucursal / subtítulo',
        'show_address' => 'Dirección comercial',
        'show_phone' => 'Teléfono',
        'show_tax_id' => 'CUIT',
        'show_custom_header' => 'Textos de cabecera',
        'show_date' => 'Fecha y hora',
        'show_document' => 'Tipo de comprobante',
        'show_reference' => 'Número de comprobante',
        'show_currency' => 'Moneda',
        'show_discounts' => 'Descuentos aplicados',
        'show_subtotal' => 'Subtotal neto',
        'show_taxes' => 'Desglose de impuestos',
        'show_surcharges' => 'Recargos por medio de pago',
        'show_payments' => 'Detalle de medios de pago',
        'show_item_count' => 'Cantidad total de artículos',
        'show_authorization' => 'CAE disponible',
        'show_transparency' => 'Transparencia fiscal después del total',
        'show_qr' => 'QR del comprobante autorizado',
        'show_contact' => 'Atención al cliente y WhatsApp',
        'show_thanks' => 'Mensaje de agradecimiento',
        'show_footer' => 'Leyenda al pie',
    ];

    public static function defaults(): array
    {
        return array_fill_keys(array_keys(self::BLOCKS), 1) + ['font_size' => 'medium'] + self::TEXTS;
    }

    public const TEXTS = [
        'contact_title' => 'Atendemos tus consultas',
        'contact_phone' => '',
        'contact_whatsapp' => '',
        'thanks_text' => 'Gracias por tu compra',
    ];
}
