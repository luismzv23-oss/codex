# Dashboard de Impuestos

- Diseño compartido con Inventario: cabecera, iconos y filtros de empresa y período.
- Seis indicadores y comparación visual entre IVA Ventas e IVA Compras. La diferencia se presenta como comparación de ambos libros, no como una liquidación que incluya SICORE.
- Secciones Libro IVA Ventas, Libro IVA Compras y SICORE; accesos y seis exportaciones existentes en el banner.
- Búsqueda a la derecha del título; paginación independiente de cinco registros sin navegar. Los totales del período permanecen visibles.
- Tablas adaptables sin scroll horizontal; CAE completo en el listado de ventas.
- Actualización cada 60 segundos preservando búsqueda y página, con pausa al editar filtros o abrir popups. Los popups no muestran banner.
- Servicios de cálculo, rutas, permisos y generación de archivos fiscales sin cambios.

## Verificación

- 153 pruebas, 744 aserciones, correctas.
- Pruebas de indicadores, tablas, totales, CAE, parámetros de las seis exportaciones, estado vacío y popup sin banner.
- Renderizado con Empresa Demo bajo transacción de solo lectura: seis indicadores, tres listados, cuatro comprobantes de venta y cero de compra en el período. Sin escrituras ni envíos fiscales.
- Sintaxis PHP y JavaScript correcta. Pendiente comprobación visual e interacción en navegador.
