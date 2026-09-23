# Catálogo de medios de pago por empresa

Implementado en Configuración, junto a Impuestos. Listado de 4 registros por página sin recarga, alta y edición en popup, estado activo/inactivo y eliminación lógica. La consulta utiliza `settings.view`; los cambios requieren `settings.manage`.

## Persistencia

Migración: `2026-09-23-120000_CreateCompanyPaymentMethods`. Tabla `company_payment_methods`.

- `id`: GUID/UUID automático de 36 caracteres, clave primaria, conforme a `BaseUuidModel`.
- `company_id`, `code`: código único por empresa, normalizado a mayúsculas; no se reutiliza tras eliminar.
- Nombre, tipo, estado, destino de fondos, permite cuotas y requiere confirmación.
- Monedas, sucursales, puntos de venta y datos obligatorios: listas JSON en columnas TEXT. Las referencias se validan contra la empresa dentro de una transacción.
- Sucursales y puntos de venta vacíos significan disponibilidad general. Monedas requiere al menos una selección.
- `deleted_at`: conserva el registro y GUID; la eliminación también desactiva el medio.

Los tipos admitidos son efectivo, transferencia, tarjeta, cheque, billetera y otro. Destino de fondos clasifica caja, banco o cuenta transitoria; aún no selecciona una cuenta contable o bancaria concreta.

## Alcance

Este cambio implementa el CRUD del catálogo. No reemplaza los selectores existentes de facturación y cobranzas ni modifica pagos históricos. La aplicación de moneda, disponibilidad, datos obligatorios, cuotas, confirmación y destino a operaciones reales requiere integrar el catálogo con esos flujos en una etapa posterior.

La migración se aplicó en la base local durante el desarrollo. En otros entornos ejecutar `php spark migrate` antes de servir la nueva vista.

Pruebas: `tests/database/CompanyPaymentMethodTest.php`, sobre base SQLite de pruebas; cubren CRUD, GUID, bajas lógicas, códigos únicos y aislamiento entre empresas.
