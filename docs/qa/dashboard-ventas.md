# Dashboard y pantallas de Ventas

## Alcance

- Estilos de Inventario aplicados a Ventas, Diarios, Reportes, Cobranzas y Configuración.
- Banner con acciones por icono, filtros fuera del banner y seis indicadores en el resumen principal.
- Comparación por estado y gráfico de cantidad de ventas por fecha en Reportes.
- Tablas y listados comerciales con búsqueda por tarjeta, cinco registros por página y navegación local. Las listas de precios y promociones ya no se recortan a los primeros cinco registros.
- Actualización cada 60 segundos, con pausa durante edición y popups. Al guardar un popup se actualizan los datos; las acciones POST de los listados se ejecutan en segundo plano conservando las rutas y validaciones existentes.
- Formularios de POS, Kiosco, clientes y demás submenús con estilos consistentes; los formularios no se actualizan automáticamente. No se renderiza banner en popups.
- No se modificaron servicios de cálculo, autorización fiscal, stock, cobranzas, impresión, ni plantillas PDF/ticket/QR.

## Verificación

- Suite: 151 pruebas, 729 aserciones, correcta.
- Pruebas nuevas de indicadores, acceso de vendedor, formularios sin banner y gráfico vacío/con un día.
- Renderizado real de Ventas, Diarios, Reportes, Cobranzas y Configuración con Empresa Demo bajo transacción de solo lectura; sin escrituras ni envíos a ARCA.
- Sintaxis PHP/JavaScript y revisión del diff.
- Pendiente validación visual e interacción en navegador; no se generaron capturas ni se realizaron operaciones comerciales reales.
