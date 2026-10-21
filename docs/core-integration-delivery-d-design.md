# Entrega D: análisis y diseño para validación

Estado: diseño validado por el usuario. La implementación, el despliegue local y la evidencia de pruebas se describen en `core-integration-delivery-d.md`.

## Hallazgos del código actual

1. `EventBus` registra diez tipos de eventos/listeners de AutomationService. Despacha en memoria, captura excepciones y continúa. Después intenta escribir `event_log`, pero también ignora los errores de esa escritura. No hay entregas pendientes, confirmaciones ni reintentos persistentes.
2. `event_log` contiene evento, payload y cantidad de listeners; carece de columna empresarial, clave de deduplicación y estado de entrega. No puede reutilizarse como cola fiable sin cambiar su significado.
3. Hay ocho puntos de emisión, para cinco tipos de evento: venta confirmada, venta cancelada, cobro recibido, recepción de stock y pago a proveedor. Las emisiones están repartidas entre controladores y servicios.
4. Cobranzas web/API emiten después de que SalesCollectionService confirma su transacción. Una caída entre commit y emisión deja la operación realizada sin evento. Además, la diferencia de importes se calcula en el controlador con una lectura anterior a la confirmación: no es una clave segura para deduplicar cobros parciales.
5. La recepción de Compras web emite dentro de su transacción; su equivalente API no tiene esa emisión. La confirmación y cancelación de Ventas también presentan diferencias de emisión entre canales.
6. SupplierPaymentService emite dentro de la transacción del pago. Esta posición permite guardar el evento junto con el pago, sin recalcular ni repetir el egreso.
7. `journalFromSale` ya bloquea y consulta un asiento por referencia. No corresponde afirmar que toda la contabilidad carece de deduplicación. `journalFromPurchase` no tiene esa misma protección en su método.
8. WithholdingService inserta percepciones/retenciones con nuevos identificadores al calcularlas, sin una comprobación previa equivalente por origen. Reejecutar indiscriminadamente AutomationService puede repetir esos registros.
9. Algunos listeners devuelven etiquetas como `queued` o `created` para acciones que solo describen y registran. No deben interpretarse como evidencia de ejecución de una integración.

## Alcance propuesto

Infraestructura durable y adopción de los cinco eventos que realmente se emiten. Preservar stock, caja, asientos, condiciones fiscales, permisos y asignaciones existentes. Los efectos críticos actuales siguen siendo parte de la transacción de negocio; esta entrega no traslada la confirmación de una venta o un pago a una tarea eventual.

Los otros cinco tipos registrados no generarán trabajo ficticio ni eventos históricos. Se conservan compatibles hasta que tengan un productor y contrato verificables.

No convertir `event_log` ni `automation_log` en colas, no reprocesar su contenido histórico y no activar envíos fiscales, correo o llamadas externas como efecto de migrar la infraestructura.

## Modelo persistente

Nueva migración exclusivamente bajo `CodexCore`, con tres tablas:

| Tabla | Campos y garantías principales |
|---|---|
| `integration_outbox` | UUID, empresa, evento, versión de esquema, tipo/ID de origen, clave de operación, fecha, payload JSON mínimo y hash. Unicidad por empresa/evento/clave de operación. Una clave repetida con payload diferente se rechaza. |
| `integration_deliveries` | UUID, evento, empresa, consumidor estable y versión, estado, intentos, próxima ejecución, token y vencimiento de reserva, fecha de finalización y error saneado. Unicidad evento/consumidor. |
| `integration_delivery_attempts` | Entrega, empresa, número de intento, inicio/fin y resultado saneado, para diagnóstico de fallos y recuperaciones. |

El evento y las entregas a consumidores se guardan dentro de la misma transacción y conexión de la operación de negocio. Un rollback elimina también el trabajo pendiente. No hay fallback silencioso a eventos volátiles si falta el esquema.

Los consumidores se identifican mediante un registro de código desplegado; nunca mediante PHP, una clase o una URL elegidos desde el payload. Se captura la lista de destinatarios al producir el evento. Un consumidor desaparecido o incompatible queda bloqueado para revisión, sin descartar su entrega.

## Servicios y contratos

Ubicación propuesta: `app/Libraries/Core/Events/`.

```php
OutboxPublisher::publish(
    string $companyId,
    string $event,
    string $operationKey,
    string $sourceType,
    string $sourceId,
    array $payload
): string; // ID del evento; exige transacción activa.

DeliveryWorker::processBatch(string $companyId, int $limit): array;
DeliveryWorker::retryFailed(string $companyId, string $deliveryId): void;
DeliveryStatus::summary(string $companyId): array;
```

El registro de consumidores define evento, ID estable, versión y callback de código. Los productores validan la pertenencia del origen a la empresa y construyen payloads por lista explícita de campos, sin contraseñas, tokens, certificados ni volcado de modelos completos.

`EventBus` mantiene compatibilidad de interfaz donde sea necesaria, pero no ejecutará a la vez el listener antiguo y su entrega durable. La sustitución se hará por productor/consumidor, con pruebas que impidan el doble despacho.

## Entrega y reintentos

Estados: `pending`, `processing`, `retry`, `succeeded`, `dead`, `blocked`.

- El worker reclama una entrega mediante cambio condicional y token propio. La reserva se confirma antes de ejecutar el consumidor.
- Antes de aplicar efectos locales, bloquea y verifica la entrega, empresa, token y estado. Efectos y finalización se confirman juntos en una transacción de la misma base de datos. Un proceso antiguo no puede confirmar una entrega reclamada por otro.
- Si falla la transacción, no queda un efecto local confirmado sin su marca de éxito. Se guarda el intento fallido y se agenda la siguiente ejecución.
- Propuesta inicial: cinco intentos totales, con esperas de 1, 5, 15 y 60 minutos. Agotados los intentos, pasa a `dead`; no se descarta ni reinicia automáticamente.
- Una reserva abandonada puede recuperarse tras vencer, respetando el bloqueo de cualquier transacción activa. El trabajador procesa lotes acotados por empresa.
- No se promete ejecución exactamente una vez frente a servicios externos. Un consumidor remoto deberá aportar clave de idempotencia, consulta de resultado y tratamiento de respuesta ambigua antes de habilitar reintentos. El worker ARCA existente mantiene su circuito específico.

## Adopción de productores

| Evento | Cambio propuesto |
|---|---|
| `sale.confirmed` | Publicación transaccional en confirmación web/API, con clave de transición estable. Contabilidad y stock críticos mantienen su ubicación actual. |
| `sale.cancelled` | Publicación dentro de la cancelación bloqueada y transaccional en ambos canales, en lugar de una emisión posterior al commit. |
| `sale.payment_received` | Publicación dentro de SalesCollectionService al cambiar efectivamente el estado de un medio de cobro. Clave basada en el medio confirmado, no solo en el recibo: admite cobros parciales sin pérdidas ni duplicados. Eliminar emisiones posteriores de los controladores. |
| `inventory.stock_received` | Publicación dentro de la recepción web/API, identificada por la recepción. |
| `purchase.payment_made` | Sustituir la emisión dentro del servicio de pagos, identificada por el pago efectivamente contabilizado. |

Las automatizaciones se separarán por efecto cuando tengan necesidades diferentes de reintento. Los efectos existentes de percepciones/retenciones deberán quedar protegidos por la entrega transaccional y sus referencias de origen antes de habilitarlos. Ningún reintento volverá a ejecutar la confirmación original, descontar stock o registrar un egreso.

## Operación y permisos

Comandos propuestos:

```text
php spark integrations:status --company <id>
php spark integrations:work --company <id> --limit 50
php spark integrations:retry --company <id> --delivery <id>
```

`status` es de consulta; `work` ejecuta únicamente consumidores habilitados; `retry` es una acción explícita y deja evidencia. Cada operación exige la empresa. La configuración del programador de tareas requiere comprobar la ruta y el entorno de PHP antes de instalar una tarea real; no se dará por operativa solo por existir el comando.

El diagnóstico se conectará al apartado técnico de Sistemas para superadmin. Cualquier pantalla editable de parámetros debe ubicarse en Configuración; los permisos permanecen en Sistemas. No se crean ni asignan permisos automáticamente. No se agrega un endpoint público de ejecución de trabajos.

## Aceptación y despliegue

Pruebas necesarias: commit/rollback conjunto, deduplicación y conflicto de payload, cobros parciales, paridad web/API, un consumidor fallido sin repetir otros completados, recuperación tras caída, reserva vencida y token antiguo, exclusión concurrente real en MySQL, aislamiento empresarial, consumidor ausente, cola de fallos y reintento explícito. Además, comprobar que stock, caja, contabilidad y asignaciones existentes no cambian por instalar la infraestructura.

Despliegue por pasos: respaldo y comprobación de descubrimiento; migración aislada CodexCore; pruebas; verificación de productores/consumidores; ejecución de prueba acotada; activación controlada del worker. No ejecutar migraciones App pendientes de Compras ni reprocesar registros históricos. Registrar las limitaciones de cualquier prueba de concurrencia que no pueda realizarse en el entorno disponible.

El diseño fue validado antes de implementar, conforme al protocolo de fases solicitado por el usuario. La prueba operativa sobre una empresa real tiene un control de aprobación separado, detallado en el documento de implementación.
