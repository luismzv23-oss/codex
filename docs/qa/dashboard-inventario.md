# Dashboard de Inventario

Se mantiene el sistema visual del dashboard principal y los permisos existentes.

- Seis indicadores: sin disponibilidad, bajo minimo, productos activos, productos con reservas, valor a costo de catalogo y movimientos del periodo.
- Disponibilidad calculada con stock menos reservas, sumando todas las ubicaciones del deposito.
- Filtros del resumen por deposito, categoria y fechas (actividad, maximo 90 dias). Existencias actuales, no reconstruccion historica.
- Reposicion prioritaria con acceso al kardex del producto; alertas adicionales desplegables.
- Tendencia diaria de cantidad de operaciones, con detalle accesible y estados vacios.
- Boton de actualizacion en la cabecera y refresco cada 60 segundos, pausable. Se pausa con pagina oculta o modal abierto; conserva datos y muestra aviso si falla.
- El detalle operativo inferior conserva el alcance de toda la empresa y sus acciones originales.

## Validacion

139 pruebas, 593 aserciones, sin fallos ni omisiones. Cuatro pruebas nuevas cubren aislamiento de empresas, stock reservado, multiples ubicaciones, filtro de deposito, transferencias sin duplicacion, fechas y renderizado con/sin datos.

Consulta MySQL de solo lectura sobre Empresa Demo y render PHP satisfactorios: 8 productos activos, 1 bajo minimo, 0 sin disponible y 25 movimientos en el periodo predeterminado de 30 dias al verificar.

No se modificaron registros de inventario. No se pudo inspeccionar visualmente en navegador: el proveedor no tiene navegadores disponibles y rechazo crear una pestana. La verificacion visual y la impresion no se afirman como realizadas.

Referencias de diseno proporcionadas por el usuario: [Toptal](https://www.toptal.com/designers/data-visualization/diseno-de-dashboard-consideraciones-y-mejores-practicas) y [Tableau](https://www.tableau.com/es-es/learn/whitepapers/must-dos-marketing-dashboards).
