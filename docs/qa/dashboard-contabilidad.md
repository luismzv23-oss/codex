# Contabilidad: dashboard y submenús

## Alcance

- Cabecera, filtros y botones basados en los estilos compartidos de Inventario.
- Pantallas: resumen y plan de cuentas, diario, mayor, comprobación, balance general, resultados, nueva cuenta y nuevo asiento.
- Seis indicadores del período: diferencia entre debe y haber contabilizados, borradores, asientos contabilizados, ingresos, egresos y resultado neto.
- Comparación de ingresos y egresos y enlaces a sus libros de detalle.
- Empresa y fechas en filtros; estado adicional en diario. En mayor se conserva la empresa de la cuenta consultada.
- Búsqueda local y paginación independiente de cinco registros, sin recargar el documento. Los totales permanecen visibles y no cambian al buscar o paginar.
- Tablas de ancho fijo con texto adaptable; bajo 1100 px los registros se presentan como bloques con etiquetas, sin scroll horizontal.
- Actualización de los datos cada 60 segundos, conservando búsqueda y página. Se pausa con pestaña oculta, filtros modificados, campos en edición o modal abierto. Los formularios de alta no se actualizan automáticamente.
- Se reutilizan los cálculos de AccountingService; no se modificaron asientos ni reglas contables.

## Verificación

- PHP lint: controlador y todas las vistas de Contabilidad, sin errores.
- JavaScript: `node --check public/assets/js/accounting-dashboard.js`, correcto.
- Suite completa: 144 pruebas, 654 aserciones.
- Prueba de renderizado de las ocho pantallas con datos, acciones de contabilización y campos de líneas conservados.
- Consulta real de Empresa Demo dentro de una transacción de solo lectura: seis KPIs renderizados, once asientos contabilizados en el período, cero borradores y diferencia debe/haber cero. Transacción revertida sin escrituras.

## Límite de validación

No hubo navegador conectado para comprobar visualmente escritorio/móvil ni interactuar con los controles. Pendiente la revisión visual de paginación, modales y actualización automática en el navegador del usuario.
