# Centro de control: administrador y superadmin

El dashboard principal utiliza `DashboardInsights` para estos dos perfiles. Las demás vistas y los accesos a diagnóstico y QA conservan su comportamiento.

- Administrador: alcance limitado en servidor a la empresa asignada al usuario. Un parámetro de URL no permite cambiarlo.
- Superadmin: todas las empresas o una empresa seleccionada, con comparación entre empresas.
- Filtros: fechas (máximo 92 días) y moneda. Los importes de distintas monedas nunca se suman ni se convierten implícitamente.
- Actualización: cada 60 segundos, sin recargar la página. Se puede pausar. Las pestañas ocultas y el detalle que tiene foco no se actualizan automáticamente. Ante errores se conserva la última lectura y se informa que está desactualizada.

## Definiciones

1. **Ventas emitidas:** total original de facturas y tickets confirmados, incluidos impuestos y recargos. Incluye comprobantes parcialmente o totalmente devueltos: es volumen emitido, no ventas netas ni cobranzas. Excluye borradores, anulaciones, pedidos y remitos.
2. **Comprobantes:** cantidad de documentos del mismo conjunto.
3. **Venta promedio:** ventas emitidas divididas por comprobantes. Sin ventas se muestra un guion.
4. **Por cobrar:** saldo pendiente actual de cuentas por cobrar asociado a ventas de la moneda seleccionada. Los vencidos tienen fecha de vencimiento anterior al día actual.
5. **Stock en atención:** combinaciones de producto activo y depósito activo cuyo disponible (existencias menos reservas, sumadas entre ubicaciones) no supera el mínimo. Solo se evalúan saldos registrados.
6. **ARCA por resolver:** facturas/tickets confirmados con estado pendiente, en cola, rechazado o error, sin CAE. No se asume que todo comprobante sin CAE sea un error.

Los primeros tres indicadores, la tendencia, la comparación y los últimos comprobantes usan el período. Los restantes muestran la situación actual, indicado expresamente en pantalla. Stock no depende de la moneda. La comparación de ventas usa el período inmediatamente anterior de igual duración; si su importe es cero se muestra “Sin base de comparación anterior”.

## Interacción y presentación

Seis tarjetas con jerarquía visual, tendencia diaria con tabla accesible desplegable, comparación mediante barras, prioridades operativas y últimos ocho comprobantes. Los enlaces conducen al detalle del dashboard o a módulos existentes cuando hay una empresa seleccionada. Diseño responsive, botones con iconos y nombre accesible, foco visible y respeto por movimiento reducido. No se incorpora una biblioteca externa de gráficos.

Referencias de diseño: [Toptal — diseño de dashboards](https://www.toptal.com/designers/data-visualization/diseno-de-dashboard-consideraciones-y-mejores-practicas) y [Tableau — aspectos indispensables](https://www.tableau.com/es-es/learn/whitepapers/must-dos-marketing-dashboards).

## Validación

`tests/database/DashboardInsightsTest.php` verifica aislamiento por empresa, moneda, fechas, comparación previa, agrupación de stock por depósito, períodos vacíos y renderizado del panel de ambos roles. La consulta de datos reales se realiza en transacción de solo lectura. La integración SOAP/ARCA y los registros operativos no se modifican desde este dashboard.
