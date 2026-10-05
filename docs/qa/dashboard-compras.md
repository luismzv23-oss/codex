# Dashboard de Compras

## Cambios

- Diseño compartido con Inventario, con acciones como iconos a la derecha del banner.
- Empresa, proveedor y fechas como filtros. Las fechas afectan órdenes, recepciones, facturas, notas de crédito e historial de costos. Proveedores y deuda reflejan la situación vigente.
- Seis indicadores; comparación de estados de órdenes y deuda separada por moneda, sin sumar monedas distintas ni compensar saldos a favor.
- Siete listados con búsqueda y paginación independiente de cinco filas sin recargar la página.
- Tablas adaptables con etiquetas en pantallas pequeñas, sin scroll horizontal.
- Actualización cada 60 segundos, conservando búsquedas y páginas. Pausa durante edición, filtros pendientes, pestaña oculta o modal abierto.
- Guardar un formulario emergente actualiza inmediatamente los listados y los indicadores, sin navegar. Eliminar proveedores y aprobar órdenes usan POST asíncrono con respuesta JSON y renovación del token CSRF. Se conservan confirmaciones, errores de negocio, búsquedas y paginación.
- Estilos aplicados a alta/edición de proveedores, órdenes, recepciones, devoluciones, pagos, facturas y notas de crédito. Los formularios conservan sus campos, acciones, permisos y reglas de negocio.

## Validación (2026-10-04)

- Suite: 148 pruebas y 702 aserciones, correcta; incluye respuestas JSON de éxito/error de las acciones y token CSRF, sin redirección.
- Pruebas nuevas: fechas invertidas/inválidas, proveedor, límite inclusivo del período, deuda antigua vigente, monedas separadas, saldos a favor, renderizado de dashboard y siete formularios, permisos de acciones.
- Sintaxis PHP y JavaScript correcta; `git diff --check` sin errores.
- Empresa Demo, transacción de solo lectura: dashboard renderizado con seis indicadores, siete listados y dos cuentas a pagar. Sin escrituras de negocio.
- No se realizó validación visual ni interacción en navegador; pendiente verificar escritorio/móvil, paginación y actualización automática en un navegador conectado.
