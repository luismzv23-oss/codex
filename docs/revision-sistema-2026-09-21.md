# Revision tecnica del sistema Codex

Fecha: 2026-09-21. Alcance: revision inicial del repositorio, autenticacion API, 2FA, configuracion y ejecucion de pruebas, y sintaxis PHP. No constituye una auditoria completa de los circuitos comerciales.

## Correcciones realizadas

- Configuracion de PHPUnit adaptada a la version 9.6 instalada: esquema, cache y filtros de cobertura. Antes producia advertencias de validacion.
- El alta de 2FA rechaza con HTTP 409 reemplazar un secreto activo. Para cambiar dispositivo se debe desactivar primero mediante el flujo existente.
- Los endpoints de perfil y 2FA recuperan el usuario actual desde la base tambien cuando se autentica por sesion. La sesion no almacena los campos de 2FA; antes no permitia confirmar correctamente la configuracion y no servia para comprobar si estaba activada.
- La verificacion TOTP rechaza secretos vacios o con caracteres invalidos y codigos que no tengan seis digitos.
- Cuatro pruebas de regresion cubren secretos invalidos, ventana temporal y proteccion del alta con sesion.

## Validacion reproducible

En este entorno Windows/XAMPP, SQLite esta instalado pero no habilitado en la consola. OpenSSL necesita indicar su archivo de configuracion. Ejecutar desde la raiz:

```powershell
$env:OPENSSL_CONF = 'C:/xampp/php/extras/ssl/openssl.cnf'
php -d extension=sqlite3 vendor/bin/phpunit --no-coverage
```

Resultado: 13 pruebas, 26 aserciones, sin errores ni omisiones. Estos ajustes se aplican al proceso de pruebas; no se modifico php.ini. En otros entornos, usar la ruta local de OpenSSL y habilitar SQLite en PHP. Las pruebas de base usan el grupo `tests` con SQLite en memoria.

## Pendientes priorizados

1. **Alta: unificar identidad y permisos API.** `ApiAuthFilter` acepta JWT y coloca la identidad en `request->jwt_user`, pero `ApiPermissionFilter` y `BaseApiController::apiUser()` consultan la sesion. Un cliente que solo envia JWT puede quedar sin acceso; una peticion que combina sesion y token puede consultar identidades distintas. Antes de cambiar este circuito, agregar pruebas de integracion para JWT, sesion, ambas identidades distintas, usuarios inactivos y aislamiento entre empresas.
2. **Alta: revisar el secreto y la rotacion JWT.** `JwtService` tiene un secreto predeterminado y la rotacion de refresh tokens lee y revoca en operaciones separadas. Verificar configuracion de despliegue sin revelar secretos y hacer atomico el consumo del refresh token, con prueba de concurrencia. No se evaluo el valor configurado en `.env`.
3. **Alta: distinguir validacion local de autenticacion ARCA.** `ArcaService::testAuthentication()` comprueba preparacion y escribe cache local; devuelve `WSAA_OK` sin realizar una autenticacion remota. Separar los estados de configuracion lista y autenticacion remota confirmada, con pruebas mediante cliente simulado y luego homologacion autorizada.
4. **Media: ampliar pruebas de procesos.** La suite sigue siendo pequena y contiene ejemplos del framework. Agregar escenarios de venta/stock/caja, anulaciones, cobros parciales, aislamiento por empresa, rollback ante fallo y reintentos duplicados. Ejecutarlos con datos aislados antes de refactorizar controladores.

No se realizaron operaciones comerciales, migraciones ni llamadas remotas a ARCA. No se inicio un servidor web, conforme a `.agents/AGENTS.md`.

## Correcciones del modulo Ventas

Se corrigieron los seis hallazgos de la revision posterior de Ventas:

1. **Aislamiento entre empresas:** las consultas y actualizaciones directas de presupuestos, pedidos y remitos ahora filtran por empresa. Se validan referencias recibidas al crear documentos (cliente, producto, deposito, documento origen y renglon del pedido). Las aprobaciones informan cuando no se modifico un documento valido.
2. **Confirmacion repetida:** web y API bloquean la fila de la venta y comprueban que siga en borrador dentro de la transaccion. Una segunda confirmacion se rechaza antes de modificar inventario. Anulaciones y devoluciones usan el mismo bloqueo.
3. **Devoluciones y deuda:** el saldo reconoce las cantidades devueltas, ponderando los importes de los renglones y el descuento global. La valuacion de nuevas devoluciones usa ese mismo criterio. Las anulaciones dejan saldo pendiente cero.
4. **Despacho atomico:** stock, movimiento y estado del remito se confirman juntos. Un fallo, incluido un periodo cerrado, revierte la operacion. Se registran `performed_by`, `source_warehouse_id`, tipo `egreso` y cantidad positiva. Se comprueban pertenencia del producto/deposito y disponibilidad de stock.
5. **Entrega:** solo un remito despachado puede pasar a entregado; se rechazan pendientes y documentos ajenos.
6. **Anulacion despues de devolucion parcial:** se reponen solo las unidades que no habian sido devueltas, tambien para los componentes de kits.

La nueva clase `SalesIntegrityService` concentra la transaccion con bloqueo y los calculos compartidos. No se reemplazo todo el modulo por servicios: el resto de la logica web/API sigue separado.

### Pruebas y limites

```powershell
$env:OPENSSL_CONF = 'C:/xampp/php/extras/ssl/openssl.cnf'
php -d extension=sqlite3 vendor/bin/phpunit --no-coverage --no-logging --do-not-cache-result
```

Resultado: **26 pruebas, 67 aserciones, sin fallos ni omisiones**. Las pruebas nuevas incluyen despacho real del controlador, movimiento generado, reintento, entrega, periodo cerrado, stock insuficiente, aprobaciones entre empresas, confirmacion repetida en ambos controladores, rollback de transacciones anidadas y calculos de devoluciones. Sintaxis PHP y `git diff --check` correctos.

Las pruebas de base usan SQLite en memoria. El bloqueo `FOR UPDATE` se aplica en MySQL, pero no se ejecuto una prueba simultanea con conexiones MySQL reales. No se ejecutaron migraciones, operaciones comerciales ni recalculos masivos: los documentos historicos que ya quedaron inconsistentes requieren conciliacion separada. Las correcciones se aplican a las operaciones y sincronizaciones futuras.

## Correcciones de Inventario

- Movimientos web/API validan empresa del deposito, relacion ubicacion/deposito y trazabilidad obligatoria. Se rechazan lotes insuficientes, series duplicadas, cantidades distintas de una unidad por serie y salidas de series no disponibles en el origen.
- El stock se mantiene por empresa, producto, deposito y ubicacion. Las operaciones de Ventas/Compras sin ubicacion consumen saldos existentes en orden estable; los ingresos sin ubicacion se registran como no ubicados. El catalogo comercial suma las ubicaciones por deposito.
- `InventoryIntegrityService` centraliza movimientos de saldos y reservas. Las operaciones de Inventario se serializan por empresa dentro de una transaccion; los saldos se bloquean tambien por producto para coordinar con Ventas/Compras. La liberacion de reservas vuelve a comprobar el estado dentro de esa transaccion.
- La revalorizacion API controla cierres, suma todas las ubicaciones y sincroniza contabilidad dentro de la misma transaccion. Los errores contables revierten capas y comprobante.
- Los cierres globales y por deposito se distinguen correctamente. El ultimo dia queda incluido y las actualizaciones parciales de movimientos tambien comprueban los datos originales.

### Migracion y validacion realizadas

Se aplico `2026-09-21-120000_InventoryStockLocations` al grupo local `default`. El indice unico anterior se reemplazo por uno que incluye ubicacion y normaliza ubicaciones nulas. Se guardo previamente `writable/backups/inventory-stock-before-locations-20260921.json` y se verifico que los **384 registros de stock conservaron sus datos**. No se redistribuyeron ni corrigieron saldos historicos.

- PHPUnit: **36 pruebas y 126 aserciones**, sin fallos ni omisiones.
- `php tests/inventory_mysql_check.php`: crea una base temporal, verifica migracion y reversa, multiples ubicaciones, reservas simultaneas e ingresos simultaneos con dos procesos PHP; elimina la base temporal al terminar. Resultado correcto en MySQL/MariaDB local.
- La prueba concurrente confirma que solo una de dos reservas de 6 unidades sobre un saldo de 10 se acepta, y que dos ingresos de 4 conservan ambas actualizaciones.

La copia local de respaldo se excluye de Git. La migracion debe ejecutarse tambien en cualquier otro entorno donde se despliegue este codigo. No se completo una prueba manual de todas las pantallas ni una conciliacion de inconsistencias anteriores.

## Analisis de Caja (pendiente de correccion)

Se revisaron controladores web/API, `CashService`, conciliaciones, cheques y migraciones. No se modifico el codigo de Caja ni se registraron operaciones reales.

1. **Critico: resumen incorrecto y datos de otra empresa.** `CashService::summary()` reutiliza la misma instancia de `CashMovementModel` para ingresos y egresos. Se acumulan filtros incompatibles y la primera consulta reinicia el builder compartido. Reproduccion en SQLite en memoria: empresa A con +100 y -30, empresa B con +900; el resumen de A devuelve ingreso 0, egreso 900 y saldo -900, en lugar de 100, 30 y 70.
2. **Alta: concurrencia en sesiones y movimientos.** `openSession()` comprueba sesion abierta e inserta sin bloqueo ni restriccion unica de sesion activa. `closeSession()` calcula el saldo antes de la transaccion. `registerMovement()` verifica apertura antes de insertar sin coordinarse con el cierre. Pueden existir aperturas/cierres duplicados o movimientos que no integren el arqueo cerrado. La deduplicacion por referencia tambien es una consulta previa sin unicidad en la base.
3. **Alta: cierre API omite supervisor.** El controlador API llama a `closeSession(..., true)` sin aplicar el control de diferencias y credenciales de supervisor que realiza la web. Un usuario con acceso `manage` puede omitir esa regla mediante API.
4. **Alta: conciliacion confiada al cliente y sin aislamiento de sesion.** Web/API aceptan `expected_amount` enviado por el usuario. `createReconciliation()` lo compara con el importe real para determinar `balanced`, sin calcularlo desde movimientos y sin comprobar que la sesion corresponda a la empresa.
5. **Alta: rendicion cambia el saldo despues de autorizar el arqueo.** El cierre web valida diferencias antes de insertar `transfer_out` y luego recalcula el esperado manteniendo el mismo importe real. Ejemplo: esperado 100, contado 100 y rendicion 100 termina con esperado 0 y diferencia +100. Tampoco se exige una sesion destino abierta: se intenta insertar `cash_session_id = null` aunque la migracion de movimientos lo declara obligatorio, y ese insert esta fuera del bloque `try` de cierre.
6. **Alta: cheques con dos vocabularios incompatibles.** `CashService` crea y opera estados `portfolio`, `received`, `deposited`, `endorsed`, `rejected`; `CheckLifecycleService` admite `cartera`, `depositado`, `endosado`, `rechazado`, entre otros. El endpoint avanzado utiliza este segundo servicio sobre la misma tabla, por lo que un cheque creado por el flujo habitual no cumple sus transiciones.

Prioridad propuesta: resumen y aislamiento; luego transacciones/estados e idempotencia; despues unificar autorizacion de cierre, rendicion, conciliacion y ciclo de cheques. Excepto el resumen, los escenarios se identificaron por lectura del codigo y requieren pruebas de integracion especificas antes de corregirse.

## Analisis de Compras (hallazgos corregidos; detalle al final)

Revision de ordenes, recepciones, facturas, notas de credito, devoluciones, pagos y vinculacion con Inventario/Caja. No se modifico codigo de Compras durante este analisis ni se ejecutaron operaciones sobre datos reales.

1. **Alta: referencias entre empresas/proveedores.** `storeInvoice()` acepta una recepcion sin comprobar su empresa ni proveedor; la web ademas actualiza su estado por ID sin filtro de empresa. `storeCreditNote()` acepta cualquier ID de factura sin validar su pertenencia al proveedor/empresa seleccionados. La API tampoco comprueba cantidades facturadas contra lo recibido.
2. **Alta: recepciones superiores a lo pedido.** `receiptItemsPayload()` comprueba cada renglon por separado, sin acumular IDs repetidos; tambien calcula pendientes antes de la transaccion y sin bloqueo de la orden. Reproduccion aislada: pedido de 10, dos renglones del mismo item de 6 unidades; acepta 12. `storeReceipt()` tampoco exige una orden aprobada.
3. **Alta: devoluciones acumuladas ilimitadas.** `returnItemsPayload()` compara con la cantidad original recibida, sin descontar devoluciones anteriores ni agrupar renglones repetidos. Reproduccion aislada: recepcion de 10 con 8 ya devueltas; acepta otra devolucion de 5. `storeReturn()` no comprueba disponibilidad antes de descontar stock; el servicio compartido aplica el delta y no sustituye esa validacion comercial.
4. **Alta: perdida del credito por devolucion de compras pagadas.** `applyReturnToPayable()` reduce `paid_amount` al minimo entre lo pagado y el nuevo total, sin crear un saldo a favor. Reproduccion aislada: total 100 pagado 100, devolucion 40; queda total 60, pagado 60, saldo 0, aunque el desembolso registrado fue 100.
5. **Alta: documentos financieros desconectados.** La cuenta a pagar se crea en la recepcion; el alta de factura no la ajusta, y una factura sin recepcion no crea una cuenta a pagar. Las notas de credito se guardan sin reducir deuda ni generar credito disponible. El alta de factura y sus renglones tampoco estan agrupados en una transaccion.
6. **Alta: pagos concurrentes y sin movimiento de caja.** `storePayment()` lee y valida el saldo antes de iniciar la transaccion, sin bloquear la cuenta a pagar. Dos pagos pueden superar el saldo y sobrescribir `paid_amount`. Si no existe caja activa, el pago y la cancelacion de deuda continuan sin movimiento de caja; tambien se ignora el retorno de `registerMovement()`.
7. **Alta: recepciones no sincronizan trazabilidad/costos de Inventario.** Compras incrementa stock y registra lote/serie como atributos del movimiento, pero no crea/actualiza `inventory_lots`, `inventory_serials` ni `inventory_cost_layers`. Una recepcion con lote puede aumentar existencias sin dejar saldo consumible de ese lote; la valuacion posterior puede usar costos alternativos en lugar del costo recibido.

Las tres reproducciones numericas se ejecutaron invocando los metodos privados reales de la API con SQLite en memoria. El resto son hallazgos por lectura del codigo; no se probaron solicitudes simultaneas de Compras. Prioridad: aislamiento y cantidades acumuladas, credito/deuda y pagos atomicos, luego integrar documentos financieros y trazabilidad compartida.

## Correcciones de Compras implementadas

Los siete hallazgos anteriores se abordaron en los flujos web y API:

1. Facturas y notas de credito validan empresa, proveedor y documento origen. La precarga web de una factura desde una recepcion tambien filtra por empresa. Se rechazan documentos anulados y productos ajenos.
2. Recepciones, devoluciones, facturas, notas y pagos se ejecutan dentro de una transaccion con bloqueo de empresa previo a la lectura de cantidades y saldos. Solo se reciben ordenes aprobadas o parcialmente recibidas; se acumulan los renglones repetidos antes de compararlos con el pendiente.
3. Las devoluciones suman cantidades ya devueltas y renglones repetidos, y verifican existencias disponibles descontando reservas. Un fallo revierte comprobante, stock y cuenta a pagar.
4. Los pagos historicos permanecen intactos. Una compra de 100 pagada por completo y con devolucion de 40 queda con total 60, pagado 100, saldo -40 y estado `credit`. El listado explica que el saldo negativo es a favor de la empresa compradora; no se aplica automaticamente a otras cuentas ni genera un reintegro de caja.
5. Las facturas directas crean su cuenta a pagar. Las vinculadas reemplazan el valor provisional de las cantidades facturadas y conservan el resto pendiente, sin duplicar la cuenta de la recepcion. Las notas actualizan esa misma deuda. Se exige factura origen para las nuevas notas; las que documentan una devolucion ya registrada deben seleccionar esa devolucion y coincidir con su importe para evitar descontarla dos veces. Sin ese vinculo, la nota representa un descuento adicional independiente.
6. Los pagos requieren caja abierta, bloquean la sesion y comprueban el resultado del movimiento de egreso. Pasarela y cheque se validan en la empresa. Se exige la moneda de la cuenta y cantidades/tipos de cambio finitos y positivos. Si falla contabilidad se revierten pago, movimiento, saldo de caja y deuda.
7. `InventoryArtifactService` comparte la actualizacion de lotes, series y capas de costo entre Inventario y Compras. Las recepciones validan trazabilidad; las devoluciones usan el lote/serie recibido. Una devolucion trazada consume el stock sin ubicacion de la recepcion, sin descontar otra ubicacion; si el articulo fue trasladado, debe volver a ese origen antes de devolverlo por este formulario.

### Migracion y comprobaciones

- Aplicada localmente `2026-09-21-130000_PurchaseDocumentPayables`: permite cuentas de facturas sin recepcion, agrega referencia a factura en la cuenta y referencia a devolucion en la nota.
- Respaldo previo: `writable/backups/purchases-before-document-payables-20260921.json`. Se verifico que las **3 cuentas y 2 notas existentes conservaron sus datos**. No se reconstruyeron pagos, lotes ni cuentas historicas automaticamente.
- PHPUnit completo: **49 pruebas, 204 aserciones**, sin fallos ni omisiones. Incluye 13 pruebas nuevas de Compras, controles web/API, documentos cruzados, renglones repetidos, devoluciones acumuladas, reservas, credito, facturas parciales/directas, trazabilidad y rollback contable.
- `php tests/purchases_mysql_check.php`: migracion/reversa en base temporal, preservacion de la FK de recepcion y rechazo de reversa destructiva; dos procesos concurrentes prueban el bloqueo compartido con cantidades de recepcion y saldos de pago. Una sola recepcion de 6 sobre pendiente 10 y un solo pago de 60 sobre saldo 100 se aceptan. Estos chequeos de concurrencia ejercitan el servicio/transaccion, no solicitudes HTTP simultaneas.

La migracion debe aplicarse en los demas entornos al desplegar. Las pruebas contables usan un servicio simulado para verificar atomicidad; no constituyen conciliacion del libro mayor ni validacion fiscal. No se realizo un recorrido manual completo del navegador. Los hallazgos generales de Caja detallados anteriormente siguen pendientes fuera de esta correccion de Compras.

## Analisis de Configuracion (hallazgos corregidos; detalle al final)

Se revisaron los controladores web/API de Configuracion, rutas y filtros de permisos, modelos, migraciones, formularios y consumidores de numeraciones/parametros. Esta revision no modifica codigo funcional ni datos reales. Los permisos de ruta estan definidos por operacion y la seleccion habitual de empresa se obtiene del usuario; el problema de aislamiento comprobado se encuentra en la referencia a sucursal de las numeraciones.

1. **Alta: numeraciones vinculadas a sucursales ajenas.** `SettingsController::storeVoucherSequence()` web y API aceptan `branch_id` sin comprobar empresa ni estado. La FK solo comprueba que la sucursal exista. Reproduccion con el controlador API real en SQLite: se registra una numeracion de empresa A apuntando a una sucursal de B. Corregir con validacion de pertenencia antes de insertar. Referencias: `app/Controllers/SettingsController.php:256`, `app/Controllers/Api/V1/SettingsController.php:102`.
2. **Alta: contrato de numeracion incompatible con su consumo.** Se permite crear varias filas para la misma empresa/tipo/sucursal, sin unicidad ni validacion de correlativo positivo. El generador de Compras selecciona por empresa y tipo, omite sucursal y estado, y usa el prefijo recibido como argumento en lugar del configurado. Reproduccion: se insertan dos numeraciones inactivas iguales con prefijo `CUSTOMPREFIX`; el generador produce `CALLER-00000001`. Esto vuelve ambiguo el correlativo utilizado y hace que opciones del formulario no se respeten. Definir una clave unica y un servicio generador comun que respete sucursal, estado y prefijo. Referencias: ambos `storeVoucherSequence()`, `app/Controllers/PurchasesController.php:1180`, migracion `CreateCoreTables`.
3. **Alta: un error al guardar impuestos elimina el predeterminado anterior.** `storeTax()`, `updateTax()` y `TaxModel::setDefault()` limpian el predeterminado antes de completar la segunda escritura, sin transaccion. Reproduccion web real en SQLite con indice unico empresa/codigo: intentar crear otro impuesto con codigo existente e `is_default=1` falla en el insert y deja cero impuestos predeterminados. Tambien se permite desactivar/eliminar el predeterminado o seleccionar uno inactivo, mientras `getDefaultTax()` solo devuelve activos. Agrupar validacion y cambio en una transaccion y definir reemplazo o rechazo al desactivar/eliminar. Referencias: `app/Controllers/SettingsController.php:121`, `:165`, `app/Models/TaxModel.php`.
4. **Alta: monedas sin consistencia ni validacion uniforme.** La API permite cambiar la moneda base a un codigo que no existe entre las monedas de la empresa; la web tiene un control que la API omite. Ambos `storeCurrency()` aceptan tipos de cambio cero/negativos y multiples monedas predeterminadas; tampoco sincronizan esa marca con `companies.currency_code`. Reproduccion API: moneda base `XYZ` sin catalogo y dos monedas simultaneamente predeterminadas con cambio cero. Centralizar reglas de moneda base/predeterminada, vigencia y cambio positivo. Referencias: `app/Controllers/Api/V1/SettingsController.php:30`, `:85`, `app/Controllers/SettingsController.php:241`.
5. **Media: validaciones de maestros y contrato de impuestos incompletos.** Los modelos de empresa/sucursal/impuesto/moneda/numeracion no aportan reglas de validacion; los controladores convierten tipos pero no validan sistematicamente obligatorios, rangos o longitudes. Los atributos HTML no protegen solicitudes directas. La API de impuestos omite `afip_code` e `is_default` aunque se envien. Reproduccion API: acepta tasa -21 y descarta ambos campos enviados. Validar del lado servidor, devolver errores de negocio y alinear campos admitidos por web/API. Referencia: `app/Controllers/Api/V1/SettingsController.php:70` y modelos respectivos.
6. **Alta: restriccion de campos de impresion incompleta en servidor.** El formulario deshabilita `custom_text_bottom_left` y `custom_text_bottom_right` para roles distintos de admin/superadmin. Sin embargo, `updateTicketSettings()` solo restringe los textos superiores, sus negritas y la fuente. Un usuario de otro rol con permiso `settings.manage` puede enviar directamente los textos inferiores que la interfaz le impide editar. Agregar esos campos al control de servidor y probar las combinaciones rol/permiso. Hallazgo por inspeccion, no se ejecuto una solicitud autenticada de ese rol. Referencias: `app/Controllers/SettingsController.php:498`, `app/Views/settings/forms/tickets.php:110`.
7. **Media: crear una sucursal modifica permisos ajenos al tramite.** La web llama `syncAdminSystemAssignments()` al crear una sucursal. Selecciona solo el primer administrador de la empresa y pone sus asignaciones en `manage`/activo, incluso si estaban restringidas o desactivadas. La API no realiza esa sincronizacion. Es un efecto lateral comprobado por lectura del codigo: requiere decidir si se elimina o se limita a la provision inicial, preservando decisiones previas de acceso y tratando igual ambos canales. Referencia: `app/Controllers/SettingsController.php:354`.
8. **Media: cambios de empresa e impresion pueden quedar parcialmente guardados.** La web actualiza empresa y luego `max_cash_registers` sin transaccion; el guardado de tickets realiza multiples consultas e inserts/updates independientes. Un fallo intermedio deja configuracion parcial; dos primeros guardados simultaneos pueden competir con la clave unica empresa/parametro. Ademas, omitir `max_cash_registers` en un POST lo convierte en cero (sin limite), y omitir checks de impresion los desactiva. Definir si cada endpoint reemplaza todo o actualiza campos presentes, usar transaccion y upsert seguro. Hallazgo por inspeccion; no se inyectaron fallos ni solicitudes concurrentes. Referencias: `app/Controllers/SettingsController.php:48`, `:498`, migracion `CreateCompanySettingsTable`.

### Alcance de la comprobacion

Las reproducciones se ejecutaron contra SQLite en memoria invocando controladores reales, con empresa simulada y tablas minimas; se incluyo el indice unico real de impuestos. Se confirmaron referencias de sucursal ajenas, duplicados/uso de numeraciones inactivas, moneda base inexistente, cambios cero/multiples predeterminadas, tasa negativa/campos API ignorados y perdida del impuesto predeterminado tras insert fallido. No representan una prueba HTTP de los filtros ni una prueba concurrente en MySQL. El script temporal fue eliminado al terminar. La busqueda del log local no aporto entradas especificas de estos componentes; esto no descarta los defectos reproducidos.

Prioridad de correccion: aislamiento y numeracion; atomicidad de impuestos y consistencia de monedas; autorizacion de tickets; despues validacion uniforme, guardado atomico y eliminacion de efectos laterales sobre permisos. Mantener los datos historicos y revisar duplicados antes de agregar restricciones de unicidad.

## Correcciones de Configuracion implementadas

Los ocho hallazgos se corrigieron mediante `SettingsService`, compartido por web y API, y `VoucherSequenceService`, utilizado por Ventas y Compras.

- **Aislamiento y validacion:** se valida empresa existente, sucursal activa de esa empresa, campos obligatorios, longitudes, correo, valores booleanos, tasas entre 0 y 100, cambios positivos y correlativos enteros positivos. Codigos duplicados generan rechazo antes de modificar otros registros. La API de impuestos conserva `afip_code` e `is_default`.
- **Numeracion:** el generador respeta prefijo y estado, prioriza la sucursal y luego una secuencia general. Para usuarios sin sucursal conserva compatibilidad con las secuencias antiguas de Casa Matriz. Rechaza ambiguedades e inactividad; no sustituye una secuencia inactiva por otra. La creacion y el incremento se serializan por empresa. Las vistas previas no crean ni incrementan secuencias. Los nuevos prefijos deben ser distintos dentro de la empresa para evitar colisiones de numeros; las devoluciones/conversiones usan la sucursal del documento origen cuando esta definida.
- **Impuestos:** cambio del predeterminado y guardado ocurren en una sola transaccion. Se impide seleccionar uno inactivo o desactivar/eliminar/quitar la marca al actual sin seleccionar antes otro. El metodo publico `TaxModel::setDefault()` aplica las mismas reglas y valida empresa.
- **Monedas:** ambos canales exigen moneda base activa y existente, y tipo de cambio positivo. Al seleccionar la moneda base o crear una moneda predeterminada se sincronizan la marca unica y `companies.currency_code`. No se recalculan importes ni tipos de cambio historicos.
- **Permisos:** crear sucursales ya no reactiva ni aumenta permisos del administrador. Los textos inferiores de tickets tambien quedan restringidos en el servidor a admin/superadmin. Una solicitud no autorizada se rechaza y revierte sus otras modificaciones.
- **Guardado atomico:** empresa/limite de cajas y parametros de impresion se guardan dentro de transacciones con bloqueo por empresa. Los campos omitidos se conservan. El formulario envia cero explicitamente para checks desmarcados y mantiene deshabilitados los campos restringidos, incluidos sus campos ocultos. Consultar Configuracion ya no crea una sucursal como efecto lateral.

### Migracion y verificacion de Configuracion

Se aplico localmente `2026-09-21-140000_UniqueVoucherSequences`, que agrega unicidad por empresa, tipo y sucursal, normalizando sucursal nula mediante columna generada. Antes se comprobaron duplicados (ninguno) y se guardo `writable/backups/settings-sequences-before-unique-20260921.json`. Se verifico que las **51 numeraciones conservaron todos sus datos y correlativos**. La migracion rechaza bases con duplicados sin intentar fusionarlos ni renumerar comprobantes.

- Suite completa PHPUnit: **57 pruebas y 251 aserciones**, sin fallos ni omisiones. Ocho pruebas nuevas cubren Configuracion por servicio y controladores web/API; se ajusto la fixture de Compras para declarar sus secuencias activas explicitamente.
- `php tests/settings_mysql_check.php`: base temporal eliminada al terminar; comprueba migracion/reversa, rechazo previo de duplicados, unicidad con sucursal nula, preservacion de FK, inicializacion/asignacion concurrente de correlativos y guardado concurrente de la misma clave de impresion. Dos procesos obtienen `T-00000001` y `T-00000002`, con una sola secuencia y una sola fila por parametro.
- No se modificaron datos comerciales durante las pruebas. No se hizo una conciliacion de parametros historicos ni un recorrido manual completo del navegador. La migracion debe aplicarse en otros entornos junto con el codigo; si detecta duplicados, requiere conciliarlos antes del despliegue.
