# Dashboard de Kardex

Implementado con los estilos compartidos de Inventario: cabecera, identidad de empresa, botones y filtros principales (empresa, producto, desde, hasta). Origen, destino, movimiento, documento y motivo permanecen disponibles en filtros avanzados.

Seis indicadores contabilizan todos los movimientos coincidentes, sin el limite de 400 registros de la tabla de detalle. Tendencia diaria con dias sin actividad; para intervalos superiores a un ano se agrupa por mes. Comparacion por tipo de operacion, tablas con cantidades por producto y acceso a trazabilidad.

El resumen se actualiza cada 60 segundos. Se pausa al ocultar la pagina, abrir un modal o editar filtros sin aplicar. Los errores conservan la informacion anterior. La cabecera permite recargar toda la pagina; el PDF conserva los filtros aplicados. La tabla informa su limite de 400 movimientos y diferencia existencias actuales de actividad historica.

Validacion: 143 pruebas y 608 aserciones, sin fallos ni omisiones. Nuevas pruebas cubren mas de 400 registros, aislamiento de empresa, filtros, intervalos sin actividad, agrupacion mensual y render vacio/con datos. Validacion PHP completa con consultas de solo lectura sobre Empresa Demo: 25 movimientos y 5 productos. Sintaxis PHP y JavaScript verificada.

No se modificaron movimientos ni existencias. La inspeccion visual en navegador sigue pendiente por no disponer de un navegador conectado.
