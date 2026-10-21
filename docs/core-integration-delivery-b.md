# Integración de Core — entrega B

Verificación local: 2026-10-21. Piloto: Inventario, web y API.

## Implementación

Se incorporaron `ActorContext`, `CompanyContext`, `SystemAccessService` y `AccessException` bajo `app/Libraries/Core`. Los contextos tienen construcción controlada y no modifican catálogo, usuarios, roles o asignaciones.

- `ActorContext::current()` obtiene la identidad autenticada, sin contraseñas ni secretos de MFA.
- `CompanyContext::requested($request)` concilia `company_id` en query, formulario, JSON y `X-Company-Id`. Rechaza tipos inválidos, JSON inválido y valores contradictorios antes de ejecutar operaciones.
- `CompanyContext::resolve($actor, $companyId)` exige una empresa activa; los usuarios ordinarios solo pueden operar su empresa vigente. Superadmin conserva la selección explícita y el fallback alfabético, ahora entre empresas activas.
- `CompanyContext::assertResource($resource)` exige que la entidad pertenezca a la empresa. Los filtros por empresa de los recursos de Inventario existentes se mantienen; el helper no sustituye automáticamente todas esas consultas.
- `SystemAccessService::context(...)` devuelve el contexto compatible con los controladores actuales. `authorize(...)` ofrece la variante que solo autoriza o lanza una excepción.
- La identidad y los permisos de rol se refrescan una vez por petición para sesiones web. Bearer sigue resolviendo el usuario vigente desde base de datos sin crear ni modificar una sesión web. Cambios de rol, sucursal, permisos y estado se reconocen en la siguiente petición.
- Dentro de la petición se reutiliza una lectura de identidad. Empresa, sistema y asignaciones se comprueban en cada invocación del servicio de acceso. No se promete revocación a mitad de una operación ya iniciada.

`AuthService::allows()` concentra la política previa de permisos y la restricción de administradores fuera de MAIN; `can()` y `ActorContext` usan esa misma regla. Se conserva la excepción de superadmin. Logout también limpia la identidad que se había resuelto dentro de la petición.

El servicio `auth` no figura en `WorkerMode::$persistentServices`; no se agregó ningún contexto de usuario a servicios persistentes. Los consumidores que creen procesos propios deben iniciar una identidad nueva por petición o trabajo, sin conservar `ActorContext` entre usuarios.

## Política del piloto

| Actor y acceso | Consultar | Gestionar | Configurar |
|---|---|---|---|
| Superadmin | Sí | Sí | Sí |
| Admin / gestión | Sí | Sí | Sí |
| Admin / consulta | Sí | No | No |
| Operador / gestión | Sí | Sí | No |
| Operador / consulta | Sí | No | No |
| Vendedor | No | No | No |

Superadmin continúa exceptuado de las asignaciones empresa/usuario, pero no de comprobar que la empresa y el sistema estén activos. Los demás necesitan `systems.view`, habilitación empresarial y asignación activa del usuario. Un nivel de acceso desconocido se rechaza.

La configuración de Inventario conserva su regla existente de admin con gestión; esta entrega no cambia esa regla por la de administración general de Configuración.

## Integración y compatibilidad

Ambos `InventoryController::inventoryContext()` consumen el servicio común. Web mantiene sus redirecciones y mensajes; API mantiene su respuesta JSON de error. La decisión de acceso es la misma, aunque los transportes respondan de manera distinta.

Los inicializadores de Inventario no se movieron al servicio de autorización. La API conserva su inicialización previa, después de autorizar. No se alteraron cantidades, productos, reglas de kits, precios ni movimientos.

Cambios de comportamiento intencionales:

- Una sesión antigua no conserva privilegios de un rol anterior.
- Un usuario inactivo o eliminado pierde acceso tanto por sesión como por bearer.
- Una empresa inactiva queda bloqueada, también para superadmin.
- Indicar otra empresa ya no se ignora para un usuario ordinario: se rechaza.
- Query, formulario, JSON y encabezado deben indicar la misma empresa cuando se combinan.
- La API de Inventario aplica la restricción de vendedor que ya existía en web; no abre un acceso alternativo por API.

Los controles generales de autenticación se benefician del refresco de identidad. La resolución empresarial y la autorización de otros módulos todavía no se migraron a este servicio.

## Verificación

- 13 pruebas nuevas, 286 aserciones, sobre SQLite aislada. Se prueban roles, acciones, sesión/bearer, ambos controladores, revocación de asignaciones y permisos, identidades inactivas/eliminadas, empresas inactivas, referencias ajenas, selección contradictoria, JSON inválido y rechazo de un contexto previo después de mover al usuario de empresa.
- Regresión enfocada: 63 pruebas, 648 aserciones, aprobadas; incluye entrega A, reglas de productos, integridad de Inventario, cobranzas y controles de Caja.
- Las 446 rutas continúan resolviendo a clases cargables y métodos públicos: una prueba, 1339 aserciones.
- Suite completa: 305 pruebas, 2977 aserciones, 23 errores, 5 fallos y una omisión. Se conservan los mismos casos fallidos de la entrega A; no se presenta la suite completa como aprobada.
- HTTP local sin autenticar: `/inventario` responde 302 a login y `/api/v1/inventory/settings` responde 401.
- No se probaron sesiones de usuarios reales, concurrencia MySQL ni despliegue en destino. Las pruebas con sesión y JWT usan identidades ficticias en SQLite.

Repetir:

```powershell
php -d extension=sqlite3 vendor/bin/phpunit --no-logging --do-not-cache-result tests/database/CoreAccessContextTest.php
php -d extension=sqlite3 vendor/bin/phpunit --no-logging --do-not-cache-result tests/unit/CoreRoutesTest.php
php -d extension=sqlite3 vendor/bin/phpunit --no-logging --do-not-cache-result
```

## Configuración y despliegue

No necesita nuevas tablas, migraciones, variables de entorno ni reasignaciones. Desplegar juntos los contextos, el servicio, AuthService y los dos controladores de Inventario. Reiniciar trabajadores persistentes mediante el procedimiento del entorno para que no conserven código anterior. No se instaló ni reinició ningún servidor durante esta entrega.

En la validación autenticada del destino, comprobar primero consulta con un operador, gestión con un operador autorizado y configuración con admin. Luego revocar temporalmente el acceso de una cuenta de prueba y comprobar la siguiente petición en web y API. Usar cuentas de prueba, sin alterar permisos de usuarios de negocio.

La vuelta a la entrega A requiere restaurar juntos estos componentes de la entrega B; no requiere revertir datos. El archivo ajeno `tmp/company-payment-form.js` permanece sin cambios.

## Próxima entrega

El registro técnico de módulos, sus manifiestos, la navegación derivada y la instalación verificable pertenecen a la entrega C. No se implementaron todavía. Tampoco se ejecutaron las cuatro migraciones pendientes de compras ni se configuraron correo o servicios externos.
