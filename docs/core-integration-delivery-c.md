# Entrega C: registro técnico e incorporación de módulos

## Resultado y alcance

Los siete sistemas actuales tienen manifiestos bajo `app/Modules/<Module>/module.php`. Sus controladores, servicios, tablas y reglas operativas permanecen en sus ubicaciones actuales. La versión `1.0.0` identifica este contrato de integración, no una certificación de madurez del producto.

El catálogo sigue siendo la fuente de nombre, icono, entrada y estado. La verificación técnica no habilita empresas, usuarios ni permisos. Un sistema de catálogo sin manifiesto sigue apareciendo según sus asignaciones; su diagnóstico indica que falta un manifiesto.

La pantalla `sistemas/integracion`, enlazada mediante el icono de conexión de Sistemas, es de consulta y exclusiva de superadmin. Se conservan el filtro de autenticación, el permiso `systems.manage` y una comprobación explícita de rol en el controlador.

## Componentes

- `ModuleRegistry`: descubre código confiable desplegado, valida manifiestos y dependencias, detecta duplicados y ciclos, comprueba compatibilidad de Core, rutas GET/controladores públicos y tablas/columnas declaradas. Un manifiesto inválido se informa por separado, sin vaciar el catálogo.
- `ModuleInstallationService`: diagnóstico de solo lectura, previsualización y registro explícito. Una nueva entrada se crea inactiva; una existente conserva todos sus campos. Se guarda la última verificación correcta, versión, huella y fecha en `system_installations`. No es un historial completo de despliegues.
- `ModuleNavigation`: construye enlaces con contexto empresarial a partir del manifiesto y los valores del catálogo, conservando query y fragmento. No envía el identificador de empresa a dominios externos y rechaza esquemas ejecutables. La navegación no reemplaza los controles de autorización del servidor.
- `Config/Routes.php` carga los archivos `Config/Routes.php` de módulos con manifiestos válidos, desde el árbol desplegado. No se ejecuta código obtenido de campos del catálogo o de archivos subidos por usuarios.

El selector de módulos, las tarjetas de Sistemas y los accesos de Configuración comparten la construcción de enlaces. La navegación v2 conserva sus enlaces anteriores y agrega los sistemas asignados que no estaban representados. Los tres accesos existentes de Configuración (Inventario, Ventas, Impresión y Tickets) conservan su visibilidad y el popup de Tickets. Los accesos de configuración de módulos nuevos requieren empresa/sistema habilitados y superadmin o admin MAIN con `settings.manage` y asignación de gestión.

## Incorporar un módulo nuevo

1. Desplegar el código en una carpeta propia. No cargar PHP desde la interfaz.
2. Agregar `module.php` con este formato mínimo; el ejemplo es documental y no crea Gestión Documental en esta entrega:

```php
<?php
return [
    'slug' => 'ejemplo',
    'name' => 'Ejemplo',
    'version' => '1.0.0',
    'owner' => 'Equipo responsable',
    'core_min' => '1.0.0',
    'core_max' => '1.99.99',
    'entry' => 'ejemplo',
    'icon' => 'bi-grid',
    'company_scoped' => true,
    'description' => 'Descripción del sistema.',
    'dependencies' => ['inventario' => '1.0.0'], // Versiones mínimas.
    'schema' => ['example_records' => ['id', 'company_id']],
    'settings' => [[
        'name' => 'Ejemplo',
        'path' => 'configuracion/ejemplo',
        'icon' => 'bi-gear',
        'description' => 'Reglas del módulo.',
    ]],
];
```

3. Declarar sus rutas en `app/Modules/Example/Config/Routes.php`, con namespace explícito `App\Modules\Example\Controllers` y filtros de autenticación/permisos. Los controladores deben usar `ActorContext`, `CompanyContext` y `SystemAccessService`, verificar pertenencia empresarial de cada recurso y proteger tanto web como API. La comprobación automática de rutas no acredita que esa lógica esté correctamente implementada: deben existir pruebas de denegación y aislamiento.
4. Preparar y probar sus migraciones en un namespace aislado. Aplicar únicamente las migraciones revisadas de ese módulo, con respaldo y recuperación definidos. El registro no ejecuta migraciones ni instala dependencias, workers, archivos o servicios externos.
5. Ejecutar `php spark modules:register ejemplo`. Es una previsualización sin escrituras.
6. Revisar el resultado y ejecutar `php spark modules:register ejemplo --apply`. Solo registra si se satisface el contrato declarado. No modifica asignaciones ni activa el sistema.
7. Activar y asignar el sistema usando los formularios actuales de **Sistemas**. Sus accesos operativos aparecerán en los selectores y sus parámetros en **Configuración**. Los permisos de usuarios permanecen en Sistemas.
8. Validar los circuitos funcionales, seguridad, recuperación, workers e integraciones de ese módulo antes de considerarlo operativo.

El hash cubre el manifiesto y su archivo de rutas, si existe. Cambios en otros archivos requieren actualizar la versión del manifiesto y repetir la verificación; el hash no es una firma criptográfica del producto entero. Cambios de dependencias/esquema se vuelven a comprobar al consultar el diagnóstico. Una verificación guardada no oculta fallos actuales.

## Despliegue realizado en este checkout

Se agregó un namespace de migración independiente `CodexCore` en Autoload. Se comprobó que descubre **una sola migración**, `CreateSystemInstallations`, y se ejecutó:

```text
php spark migrate -n CodexCore
```

No se usó `migrate --all` ni `migrate` sin namespace. Las cuatro migraciones pendientes de importación de Compras no se ejecutaron.

Antes de aplicar, se guardó una copia local de `systems`, `company_systems`, `user_systems` y del historial de migraciones en `writable/private/core-integration/before-delivery-c.json`. Tras aplicar y registrar los siete manifiestos, las tres tablas de catálogo/asignaciones coinciden exactamente con esa copia: **7 sistemas, 18 asignaciones empresariales y 24 asignaciones de usuarios**. La única migración nueva pertenece a `CodexCore`; hay siete verificaciones técnicas guardadas.

Consulta posterior:

```text
php spark modules:status
php spark modules:register inventario
```

Ambos comandos son de solo lectura sin `--apply`. El estado indica únicamente cumplimiento del contrato básico declarado. No asegura ARCA, correo, OCR, todas las migraciones opcionales, autorización de cada endpoint ni preparación para producción.

Para retirar este cambio, restaurar únicamente los archivos de esta entrega desde una copia o revisión propia, conservando las entregas A/B y los cambios del usuario. La tabla adicional puede permanecer sin uso. No ejecutar un rollback global ni restaurar masivamente catálogo/permisos: esta entrega no cambió esas tablas. Cualquier eliminación posterior del registro técnico debe hacerse de forma específica, conservando antes su copia.

## Verificación

- `ModuleRegistryTest`: **16 pruebas, 61 aserciones**. Cubre previsualización, idempotencia, conservación de catálogo/permisos, alta inactiva, errores de manifiesto, duplicados, ciclos, versiones, rutas, pérdida de esquema, enlaces (incluidos parámetros repetidos), contexto empresarial, revocación, configuración y restricción del diagnóstico.
- Conjunto focalizado de entregas A/B/C e Inventario, Caja y Cobranzas: **79 pruebas y 709 aserciones, aprobadas**.
- `CoreRoutesTest`: **447 rutas**, **1 prueba y 1342 aserciones**, clases/métodos públicos disponibles.
- Suite completa: **321 pruebas, 3041 aserciones, 23 errores, 5 fallos y 1 omitida**. Coinciden las familias de fallos preexistentes de la entrega B: SalesTransitions, SettingsIntegrity, TreasuryPayment, SalesDashboardView y PaymentSurcharge. No se declara la suite global aprobada.
- Renderizado PHP del informe con identidad superadmin vigente: siete tarjetas verificadas. La consulta HTTP sin sesión redirige al login (302). No se ejecutó una sesión autenticada E2E en navegador ni una revisión visual de responsive.
- Sintaxis PHP y `git diff --check` verificados.

No se agregó JavaScript ni una dependencia frontend. Se mantienen las interacciones existentes. Los permisos detallados por acción y la infraestructura durable de eventos/outbox quedan para su entrega específica; se preserva el modelo vigente de acceso `view/manage/vendedor`.
