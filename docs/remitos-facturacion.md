# Facturación y anulación de remitos

## Instalación

1. Aplicar las migraciones: `php spark migrate`.
2. Ejecutar la revisión de históricos: `php spark ventas:remitos-revisar`.
   Se puede limitar a una empresa con `--company UUID`.
3. Abrir Ventas > Diarios > Remitos de Entrega y actualizar la pantalla.

No se requiere iniciar otro servidor. El servidor existente es administrado por el usuario.

## Operación

Los botones muestran únicamente iconos. El nombre de cada acción está disponible al pasar el cursor y mediante su etiqueta accesible: recibo recortado para parcial, recibo para total y círculo con X para anular. Guardar borrador usa un disquete y confirmar factura usa una marca de verificación.

- **Facturar parcialmente:** seleccionar cantidades menores al total pendiente. Elegir factura y punto de venta, revisar importes y guardar borrador o confirmar.
- **Facturar totalmente:** incluye todo el pendiente, descontando las facturas vigentes anteriores. Confirmar vuelve a validar el saldo.
- **Anular:** exige motivo. Si hubo salida de stock, confirmar el retorno físico completo. No permite anular con facturas ni borradores vigentes.
- **Continuar borrador:** usar el acceso al borrador desde el remito o editarlo desde Comprobantes. La edición vuelve al formulario del remito.
- **Ver facturación:** muestra comprobantes vinculados y su estado, incluso cuando el remito ya está totalmente facturado.

Los remitos deben estar confirmados (circuito de ventas) o despachados/entregados (circuito de entregas) para facturarse. Las cantidades admiten cuatro decimales.

La facturación se genera a cuenta corriente; registrar el cobro desde Cobranzas. La confirmación conserva el requisito vigente de caja abierta en el circuito web. Los remitos sin precios se valorizan con la lista seleccionada o con el precio de venta del producto, y con el impuesto seleccionado. Usar **Actualizar precios** después de cambiar lista o impuesto. Si cambian precios, impuestos o pendientes antes de guardar, el servidor solicita revisar nuevamente los importes.

Los remitos que tienen precios conservan sus importes originales; los descuentos y recargos globales se distribuyen según la política fiscal existente. La asignación monetaria acumulativa conserva los centavos al completar varias facturas parciales.

## Consistencia

El formulario conserva el modo popup tanto por GET como por POST y no muestra el menú ni el asistente. El selector muestra todos los puntos activos registrados en Configuración de Ventas de la empresa, con código, nombre y número fiscal cuando está cargado. Los incompatibles con el tipo de factura y canal quedan visibles y deshabilitados; si hay uno solo compatible, se selecciona automáticamente. Los canales compartidos se admiten conservando el vínculo del comprobante. Para Factura B estándar, la configuración local usa Punto de Venta Principal; el punto de Kiosco está vinculado a Ticket Consumidor Final.

- Los borradores no consumen cantidades. Dos borradores pueden contener el mismo pendiente; al confirmar, únicamente se admite el saldo aún disponible.
- La clave única de operación impide duplicar una confirmación reenviada. La empresa y el documento se bloquean durante las operaciones.
- Una factura de remito no repite la salida de stock. Cancelar la factura tampoco devuelve stock del remito.
- La anulación del remito revierte sus movimientos originales, conservando productos, cantidades, costos, lotes, series y depósitos registrados. No reconstruye kits usando su composición actual.
- Los cierres de inventario bloquean el retorno y revierten toda la transacción ante un error.
- Cuando existe un pedido de origen, la facturación se acumula por renglón original; la anulación del remito ajusta las cantidades entregadas. La facturación directa de pedidos con remitos vigentes se bloquea para evitar duplicaciones.
- La autorización fiscal se realiza después de la confirmación local. Un rechazo, error o estado fiscal incierto no libera automáticamente cantidades. Una factura con CAE o gestión fiscal registrada no admite anulación directa para liberar el remito.
- Las devoluciones no liberan automáticamente cantidades facturadas.
- Los nuevos remitos no generan un asiento de venta ni las automatizaciones financieras de una factura.

## Históricos

No se infieren vínculos de facturación por coincidencia de producto. Los documentos anteriores sin referencias por renglón, pedidos con cantidades previas a conciliar y remitos con cobros, deuda o asientos de venta previos se informan para revisión. No se modifican automáticamente.

En la base local revisada, RTO-00000001 requiere conciliación financiera. El comando de revisión detalla el motivo actual. Para habilitarlo se debe resolver el origen de los movimientos existentes mediante el circuito correspondiente, conservando la auditoría. Esta implementación no elimina ni reasigna esos movimientos.

## Validación

Ejecutar en una base de pruebas aislada:

```powershell
php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage --log-junit tmp/remitos-junit.xml --testdox-text tmp/remitos-testdox.txt --testdox-html tmp/remitos-testdox.html --filter 'DeliveryNoteInvoicingTest|SalesDeliveryTest|DeliveryNotePricingTest|SaleAccountingTest|SalesDashboardViewTest|CommercialDocumentViewTest|SalesCollectionTest|PaymentFiscalPolicyTest' tests/database
node --check public/assets/js/delivery-invoice.js
```

El parámetro de SQLite habilita la extensión solamente para ese proceso de pruebas; no cambia php.ini. No se utilizan credenciales de producción ni se emiten comprobantes fiscales en las pruebas.

Verificación manual pendiente si no hay navegador conectado: abrir los tres popups, revisar cantidades y totales, confirmar una operación de prueba y comprobar el cierre del popup y la actualización del listado. Las pruebas automatizadas verifican el renderizado HTML, pero no sustituyen esta interacción en navegador.
