# Segunda etapa: dashboard y módulos operativos

## Alcance

Se extiende la base clara aprobada a las rutas de Dashboard, Inventario, Ventas, Compras, Caja, Contabilidad e Impuestos, incluidos sus formularios que usan layouts/app. La portada y autenticación conservan su diseño. No se modificaron controladores, permisos, esquemas, rutas ni cálculos de negocio.

- Una única carga local de Alpine CSP en layouts/app, después de registrar los componentes. Se eliminan las cargas duplicadas de las vistas de inventario.
- Dashboard ejecutivo: componente executiveDashboard, aplicación de filtros, cambio de período y pausa/reanudación declarativos; conserva cancelación de solicitudes anteriores, filtros aplicados, actualización periódica y mensajes de error.
- Ventas, Compras, Contabilidad e Impuestos: sus controladores existentes se registran como componentes Alpine asociados a cada contenedor. Conservan sus algoritmos DOM de tablas, paginación, refresco y contratos POST.
- Caja: componentes cashWorkspace y cashTables. Se extrae el controlador inline de tablas a cash-tables.js; se conserva el refresco conjunto de estados y acciones.
- El contenedor común de popups del piloto se utiliza en estas rutas.
- La vista alternativa de dashboard por perfil incorpora el encabezado compartido, conservando su contenido y autorizaciones.

Esto no declara completada la migración de todo Core: navegación Bootstrap, Tom Select, formularios especializados y otros scripts de negocio todavía requieren su propia adaptación. Usuarios, Empresas, Sistemas y Configuración quedan para el siguiente bloque aprobado.

## Evidencia

18 pruebas PHP y 174 aserciones: AccountingDashboardViewTest, TaxesDashboardViewTest, SalesDashboardViewTest, PurchasesDashboardTest, DashboardInsightsTest y CashDashboardTest. Prueba JS de refresco de caja: cinco comprobaciones.

Chrome con Alpine CSP real y vistas PHP con datos sintéticos: 88 comprobaciones en seis pantallas a 1440 y 390 px. Se revisan arranque, colores claros, ausencia de desbordamiento global, actualización y reintento; en el dashboard también pausa/reanudación y conservación de la última lectura. Las respuestas de red se simulan, sin crear sesiones ni escribir datos operativos.

Las capturas y HTML sintéticos se guardan en writable/private/ui-operational, excluido de Git. No equivalen a validación autenticada de todos los formularios o circuitos de compra/venta/caja.

## Reproducir

```powershell
$env:CODEX_UI_PREVIEW='1'
'AccountingDashboardViewTest','TaxesDashboardViewTest','SalesDashboardViewTest','PurchasesDashboardTest','DashboardInsightsTest','CashDashboardTest' | ForEach-Object { php -d extension=sqlite3 vendor/bin/phpunit --no-logging --do-not-cache-result "tests/database/$_.php" }
node tests/frontend/operational-workspaces.cjs
node tests/js/cash-dashboard-refresh.cjs
```

El próximo punto de control es la revisión visual del usuario antes de extender la identidad a administración.
