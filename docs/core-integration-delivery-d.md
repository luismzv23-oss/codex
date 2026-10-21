# Entrega D: implementación y operación de eventos persistentes

## Estado

Diseño validado e implementación local realizada. Migración aislada aplicada. Pruebas SQLite y concurrencia MySQL aprobadas. La ejecución del trabajador sobre una empresa real quedó pendiente de aprobación operativa: el control automático rechazó esa acción por su posible aplicación de automatizaciones/impuestos. No se eludió el rechazo ni se instaló una tarea programada.

## Cambios implementados

`app/Libraries/Core/Events` contiene:

- `OutboxPublisher`: exige transacción activa, valida el contrato del sobre, normaliza JSON y registra evento y destinatarios en la misma conexión de negocio. Deduplica por empresa/evento/operación y rechaza reutilizar una clave con contenido diferente. Serializa publicaciones por empresa, además del índice único de base de datos. Payload máximo: 256 KiB.
- `ConsumerRegistry`: registro de callbacks desplegados con identificador y versión. Ningún campo de payload elige clases, código, URLs o destinatarios ejecutables.
- `BusinessEvents`: comprueba pertenencia empresarial y estado del origen; arma payloads por lista explícita de campos. No copia modelos completos, contraseñas, certificados ni tokens.
- `DeliveryWorker`: reservas condicionales, bloqueo de filas, token, vencimiento de cinco minutos, recuperación de reservas abandonadas y entrega transaccional por consumidor. Efectos locales y estado completado se confirman juntos. Un token sustituido no puede finalizar la entrega.
- `LocalAutomation`: consumidores de percepciones, retenciones y evidencia. Contabilidad, caja, stock y autorización ARCA mantienen sus circuitos actuales; no se vuelven a ejecutar por reintentar una entrega.
- `DeliveryStatus`: conteos y hasta veinte entregas en reintento, agotadas o bloqueadas, con IDs y metadatos saneados para diagnóstico, siempre por empresa. No muestra payloads.

Las tablas nuevas son `integration_outbox`, `integration_deliveries` e `integration_delivery_attempts`. En MySQL se crean explícitamente con InnoDB. Se verificó que estas tres tablas y `companies` utilizan InnoDB en el checkout actual.

La bitácora conserva inicio y resultado de cada intento, abandonos y solicitudes manuales de reintento. Los mensajes de excepción y los payloads no se copian al diagnóstico; se registra el tipo de excepción en el log técnico y un mensaje operativo fijo en la entrega.

El hash permite comprobar consistencia del sobre; no es una firma criptográfica frente a un administrador con acceso de escritura a toda la base.

## Productores y compatibilidad

| Evento | Punto de publicación y clave |
|---|---|
| `sale.confirmed` | Confirmación transaccional web/API; transición e ID de venta. |
| `sale.cancelled` | Cancelación bloqueada web/API; transición e ID de venta. |
| `sale.payment_received` | Cada medio confirmado dentro de `SalesCollectionService::apply`; ID del medio. No se deduplica todo el recibo como si fuera un único cobro. |
| `inventory.stock_received` | Recepción transaccional web/API; ID de recepción. |
| `purchase.payment_made` | Pago contabilizado dentro de `SupplierPaymentService`; ID del pago. |

Se eliminaron las emisiones de cobros posteriores al commit de los controladores web/API. `EventBus::emit` reconoce los cinco eventos migrados y los dirige exclusivamente al camino durable; no ejecuta también sus listeners antiguos. Los otros eventos conservan su comportamiento anterior hasta su adopción específica. No se reprocesaron `event_log` ni `automation_log` históricos.

En cobros, cada medio confirmado tiene su evento e importe propios. Confirmar de nuevo un medio ya aplicado no genera otro evento. Si un segundo medio falla después de que se publicó el del primero dentro de la transacción, se revierten ambos: operación y evento del primer medio.

Los handlers antiguos contenían etiquetas de acciones supuestamente creadas o encoladas sin implementar esos efectos. El consumidor nuevo de evidencia registra únicamente el hecho real `integration_event_recorded`, su evento y su origen. No afirma haber restaurado stock, emitido un CAE o creado un asiento. Los asientos reales siguen siendo responsabilidad de los servicios de negocio existentes.

## Percepciones y retenciones

Los eventos guardan una captura mínima de configuraciones tributarias activas y aplicables a su empresa: identidad, tipo, tasa y mínimo cuando corresponde. El consumidor usa esa captura, no una tasa modificada después de la operación.

`WithholdingService` admite esa captura como último argumento opcional; sus llamadas anteriores siguen siendo válidas. Comprueba la empresa y busca una aplicación previa por empresa/origen/configuración. Si ya existe con los mismos valores, la reutiliza; si tiene otros valores, rechaza el procesamiento. Los errores de inserción se propagan para que la entrega pueda fallar y revertirse. El trabajador serializa por empresa la aplicación local y la numeración de certificados; la búsqueda del último certificado usa su número, no timestamps nulos o empatados.

La protección durable corresponde a los consumidores locales registrados, sobre la misma conexión transaccional. Un consumidor nuevo no debe confirmar por su cuenta la transacción externa del worker, escribir efectos en otra conexión o invocar servicios remotos bajo esta garantía. Esos adaptadores necesitan su propia idempotencia y conciliación. No se habilitaron reintentos automáticos de llamadas fiscales externas.

## Estados y reintentos

Estados: `pending`, `processing`, `retry`, `succeeded`, `dead`, `blocked`.

Cada ciclo tiene hasta cinco intentos, incluidas reservas abandonadas: tras los primeros cuatro fallos, esperas de 1, 5, 15 y 60 minutos. El quinto fallo termina en `dead`. Un contrato, versión o consumidor no disponible, o un hash inconsistente, termina en `blocked`, sin ejecutarse.

Una entrega terminada no se selecciona de nuevo. Si un consumidor falla y otro del mismo evento ya terminó, el segundo conserva su éxito. La reserva vencida puede recuperarse; el bloqueo de fila impide tomarla mientras el consumidor anterior mantiene su transacción activa.

El reintento manual solo admite `dead` o `blocked` en la empresa indicada. Abre un nuevo ciclo de cinco intentos conservando todos los intentos anteriores y agregando evidencia `manual_retry`. No se reintenta automáticamente una entrega agotada.

## Consulta y operación

El informe de **Sistemas → Integración** muestra los conteos y las entregas que requieren seguimiento para la empresa activa. Mantiene restricción a superadmin y `systems.manage`, y conserva la empresa al navegar desde y hacia Sistemas. No ofrece un endpoint HTTP de ejecución.

```text
php spark integrations:status --company EMPRESA_UUID
php spark integrations:work --company EMPRESA_UUID --limit 50
php spark integrations:retry --company EMPRESA_UUID --delivery ENTREGA_UUID
```

`status` es de solo lectura. `work` procesa consumidores locales y limita el lote a 1–200 entregas. `retry` solo cambia el estado de una entrega elegible, deja evidencia y requiere una ejecución posterior del worker para procesarla. Todos exigen una empresa existente.

No hay un programador instalado: generar un evento no inicia un proceso de segundo plano. Las entregas quedan pendientes hasta ejecutar el trabajador. Para una programación posterior se debe usar la ruta absoluta de PHP del entorno correcto, el directorio raíz de este checkout y una empresa explícita por invocación. La cadencia y la cuenta de servicio deben validarse antes de crear una tarea del sistema operativo. Los parámetros editables que se agreguen a la interfaz pertenecerán a Configuración; los permisos permanecerán en Sistemas.

Prueba operativa propuesta y todavía no ejecutada:

```text
php spark integrations:work --company 077f3cfc-e966-4c50-b8e8-ef954e58a44f --limit 1
```

El destino es **Distribuidora Duck**. En la última lectura no existían eventos ni entregas pendientes en el checkout. Ese dato puede cambiar: el comando procesaría hasta una entrega que exista al ejecutarlo y podría aplicar una percepción o retención local. No confirma otra vez la venta ni el pago original.

El control automático de aprobación rechazó esta ejecución sobre la empresa real por el posible efecto de negocio y la disponibilidad de alternativas aisladas. Las pruebas aisladas se completaron; la prueba operativa requiere una autorización explícita del usuario para este comando.

## Despliegue y conservación

Antes de migrar se guardó `writable/private/core-integration/before-delivery-d.json` con catálogo, asignaciones, historial de migraciones y registros tributarios aplicados. Se comprobó el descubrimiento de las dos migraciones CodexCore y se ejecutó únicamente:

```text
php spark migrate -n CodexCore
```

La única migración nueva aplicada fue `CreateIntegrationOutbox`. No se ejecutaron las cuatro migraciones App pendientes de Compras.

La comparación posterior coincide exactamente con la copia previa: **7 sistemas, 18 asignaciones empresariales, 24 asignaciones de usuarios**, y los registros aplicados de percepciones y retenciones (cero en esta instalación). No se ejecutó un consumidor de negocio sobre esas empresas durante la verificación.

Para retirar o reparar el despliegue, conservar siempre la cola y sus intentos. No volver a ejecutar operaciones originales para recrear eventos ni reenviar bitácoras históricas. La migración rechaza su rollback si cualquiera de sus tres tablas contiene evidencia. Si hay trabajo pendiente, detener el worker, revisar y conciliar esas entregas antes de cambiar a un circuito anterior. No hacer rollback global ni restaurar catálogo/permisos; los cambios de entregas A/B/C y los del usuario deben conservarse.

## Evidencia de pruebas

- Focalizadas A/B/C/D, Inventario, Caja, Cobranzas, Remitos/Facturación y Compras: **139 pruebas y 1007 aserciones aprobadas**.
- `IntegrationOutboxTest`: **17 pruebas y 60 aserciones**. Incluye transacción obligatoria, rollback conjunto, deduplicación/conflicto, JSON canónico, efectos no repetidos, recuperación de SQL fallido, consumidores separados, reservas y token sustituido, aislamiento empresarial, bloqueo, agotamiento y reintento manual, alteración del payload, captura de tasas, compatibilidad EventBus y conservación de evidencias ante rollback de migración.
- Pruebas reales de confirmación de venta y recepción de compras en ambos canales; cobros parciales por medio; reversión del primer evento cuando falla un medio posterior.
- `php tests/integration_outbox_mysql_check.php`: dos procesos productores y dos trabajadores concurrentes, sincronizados por barrera, sobre tablas de prueba con prefijo aleatorio. Resultado: un evento, una entrega, un intento y un efecto local. Las tablas de prueba se eliminan al terminar. No utiliza las colas de las empresas reales.
- `CoreRoutesTest`: **447 rutas, 1342 aserciones** aprobadas.
- Suite completa: **342 pruebas, 3135 aserciones, 23 errores, 5 fallos y 1 omitida**. Persisten las mismas familias preexistentes: SalesTransitions, SettingsIntegrity, TreasuryPayment, SalesDashboardView y PaymentSurcharge. La suite global no está aprobada.
- Renderizado PHP del informe: siete módulos y sección de entregas correctos. No se realizó E2E autenticado ni validación visual responsive en navegador.
- Sintaxis PHP de los archivos intervenidos y `git diff --check` verificados. No se agregó JavaScript.

Esta evidencia no acredita operación de un programador, prueba operativa del worker en una empresa real, servicios externos, carga sostenida o preparación para producción.
