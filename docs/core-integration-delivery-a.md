# Integración de Core — entrega A

Fecha de verificación local: 2026-10-21.

## Cambios

- La API de Sistemas puede cargar su controlador: se corrigió la visibilidad del método heredado, conservando su política de autorización.
- Siete rutas avanzadas apuntan al namespace correcto. Se implementaron los dos adaptadores API de recuperación de contraseña que faltaban.
- La recuperación web/API comparte un servicio de entrega privada. Nunca devuelve el enlace ni el token por HTTP. Con un correo válido, la respuesta API es 202 tanto para cuentas existentes como inexistentes; con remitente no configurado devuelve 503 para todas. Los fallos de entrega se registran sin direcciones ni tokens.
- La confirmación del token y el cambio de contraseña son una sola transacción. Se rechazan tokens vencidos, utilizados y cuentas inactivas. Se mantienen las reglas de fortaleza y confirmación de contraseña existentes.
- Notificaciones: listado, contador, lectura individual y lectura masiva se acotan al usuario y su empresa autenticada.
- Contabilidad: publicación de asientos y libro mayor requieren empresa explícita. La consulta tampoco incluye líneas de asientos ajenos aunque tengan una referencia de cuenta inconsistente.
- La auditoría de campos utiliza las columnas actuales de `audit_logs`. Los campos sensibles quedan excluidos incluso si aparecen en `watchedFields`.
- El adaptador fiscal usa `sales_settings`, referencias de la misma empresa y `ArcaService::authorizeSale`, el motor que utiliza Ventas. Conserva el contrato actual de `sales`, `sales_arca_events` e `integration_logs`.
- La cola fiscal procesa solo la empresa solicitada. No admite comprobantes ajenos, borradores, anulados ni reemisiones con CAE. Una reclamación transaccional impide procesar dos veces la misma tarea. Los resultados ambiguos pasan a contingencia: requieren consulta fiscal antes de otro intento. Los rechazos explícitos permiten hasta cinco intentos registrados; no es una cola general con planificación/backoff.

No se cambiaron catálogos, asignaciones, niveles de acceso, filtros globales, secretos ni datos históricos. No se instalaron módulos o tablas de plataforma. El archivo ajeno `tmp/company-payment-form.js` no se modificó.

## Configuración y operación

### Recuperación

Configurar el servicio Email existente mediante el mecanismo de configuración del entorno. Se necesitan un `fromEmail` válido y un transporte operativo; para SMTP también `SMTPHost`, puerto, seguridad y credenciales correspondientes. No colocar credenciales en el repositorio.

La revisión local confirmó que el remitente está sin configurar. Por eso la recuperación por correo permanece no disponible hasta completar esa configuración. No se enviaron correos reales; las pruebas inyectan un transporte ficticio. La respuesta 202 significa que se aceptó la solicitud sin revelar si existe una cuenta; no certifica entrega en el buzón.

API:

- POST `/api/v1/auth/forgot-password`: `{ "email": "usuario@empresa.example" }`.
- POST `/api/v1/auth/reset-password`: `selector`, `token`, `password`, `password_confirm`.
- Se conservan los controles CSRF y de peticiones existentes. No se agregaron excepciones de seguridad para estos endpoints.

La pantalla web ya no muestra el enlace local de recuperación. El destinatario debe recibirlo por el canal privado configurado.

### Fiscal

El trabajador recibe empresa explícita: `processBatch(string $companyId, int $limit = 20)`. El endpoint de lote ya suministra ese contexto y limita la selección a esa empresa.

La autorización externa ocurre después de reclamar el comprobante, fuera de la transacción de base de datos. El estado `processing` evita reemisiones automáticas si el proceso se interrumpe. Si queda en ese estado o en `contingency`, un operador debe consultar el comprobante mediante el circuito fiscal existente y conciliar su resultado antes de otro intento. No restablecer a `queued` directamente sin esa consulta.

No se ejecutaron WSAA, solicitudes de CAE ni llamadas fiscales reales. Las pruebas usan respuestas explícitamente ficticias. El ambiente y la configuración fiscal continúan siendo los de Ventas; esta entrega no modifica certificados ni habilita producción.

## Revisión de las migraciones de importación pendientes

Las siguientes cuatro migraciones siguen pendientes en la base local. No se ejecutaron ni se modificaron.

| Orden | Versión | Efecto revisado |
|---|---|---|
| 1 | 2026-10-16-150000 | Crea seis tablas de documentos, borradores, renglones, trabajos, auditoría y vínculos con proveedores; agrega campos a facturas de compra |
| 2 | 2026-10-16-151000 | Amplía payloads y ajustes de facturas a LONGTEXT |
| 3 | 2026-10-16-152000 | Conserva resultados históricos y agrega índice de cola |
| 4 | 2026-10-16-153000 | Registra proveedor de extracción; los trabajos anteriores conservan `azure` |

Requisitos antes de aplicarlas:

1. Registrar versión desplegada y ejecutar `php spark migrate:status` sobre la base de destino correcta.
2. Respaldar base y archivos privados de compras; conservar configuración y claves necesarias. Probar restauración en una copia aislada.
3. Pausar cargas y trabajadores durante el cambio. Comprobar espacio, permisos DDL y compatibilidad MySQL/MariaDB del destino.
4. Ensayar las cuatro migraciones, en orden, en una copia de la base. Si existen tablas parciales, comparar columnas e índices: `createTable(..., true)` no certifica que una tabla preexistente esté completa.
5. Conciliar cantidad e importes de facturas preexistentes antes/después; verificar columnas nuevas, seis tablas, índice de cola y proveedor de trabajos anteriores.
6. Aplicar al destino únicamente tras validar el ensayo y autorizar su ventana. Verificar carga, procesamiento, revisión y registro sin duplicar facturas.

Las cuatro migraciones bloquean su reversión automática. No usar `migrate:rollback` como recuperación de este cambio: ante una falla, detener escrituras, preservar evidencia y usar el respaldo probado o una migración correctiva supervisada. El DDL del motor puede confirmar parcialmente; no asumir atomicidad de las cuatro migraciones.

## Validación

- 446 destinos HTTP cargables y públicos, mediante reflexión; esto no prueba el comportamiento funcional de cada endpoint avanzado.
- 11 pruebas nuevas, 1398 aserciones: carga de rutas, alcance empresarial y por usuario, recuperación, uso único de tokens, esquema de auditoría y adaptador fiscal.
- Regresión enfocada inicial: 86 pruebas, 510 aserciones, aprobadas. Pruebas de ARCA/MFA/rutas: 9 pruebas, 1354 aserciones, con una omisión.
- Suite completa después de los cambios: 292 pruebas, 2691 aserciones, 23 errores, 5 fallos y una omisión. Son los mismos nombres de pruebas fallidas y omitidas observados en la revisión anterior de 281 pruebas: SalesTransitionsTest, SettingsIntegrityTest, TreasuryPaymentTest, SalesDashboardViewTest y PaymentSurchargeTest. Esta entrega no presenta la suite completa como aprobada.
- Comprobación de solo lectura contra MySQL local: sin columnas faltantes para los adaptadores reparados; siete sistemas en catálogo; cuatro migraciones todavía pendientes.
- No se probaron correo real, concurrencia con conexiones MySQL simultáneas, E2E autenticada completa ni autorización fiscal externa.

Repetir las pruebas:

```powershell
php -d extension=sqlite3 vendor/bin/phpunit --no-logging --do-not-cache-result tests/database/CoreIntegrationRepairTest.php
php -d extension=sqlite3 vendor/bin/phpunit --no-logging --do-not-cache-result tests/unit/CoreRoutesTest.php
php -d extension=sqlite3 vendor/bin/phpunit --no-logging --do-not-cache-result
```

Los cambios nuevos de base se prueban en SQLite aislada. Los chequeos sobre la base local de negocio fueron consultas de diagnóstico.

## Límite de esta entrega

No implementa aún el contexto empresarial y la autorización comunes de la entrega B, manifiestos, facultades detalladas, instalación automática, navegación derivada ni outbox general. Persisten las diferencias de autorización globales identificadas en el diagnóstico. Los circuitos avanzados ahora tienen destinos válidos; su validación de negocio completa requiere sus propias pruebas y configuración.
