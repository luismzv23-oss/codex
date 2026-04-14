# Fase F - Inventario avanzado operativo

## Objetivo
Cerrar las brechas operativas finas de `Inventario`.

## Brecha con Tango
Codex ya tiene costeo, lotes, series, ubicaciones y kardex valorizado. Faltan procesos operativos mas maduros.

## Alcance funcional
- ensamble y desensamble
- cierre por periodo
- revalorizacion
- reglas de stock negativo por documento
- logistica interna mas profunda

## Requerimientos funcionales
- Crear ordenes de ensamble de kit a producto terminado.
- Desensamblar stock de producto compuesto.
- Bloquear movimientos cerrados por periodo.
- Ejecutar revalorizacion controlada.
- Parametrizar stock negativo por:
  - empresa
  - deposito
  - tipo de documento

## Requerimientos tecnicos
- nuevas tablas:
  - `inventory_assemblies`
  - `inventory_assembly_items`
  - `inventory_period_closures`
  - `inventory_revaluations`
- ampliacion del motor de costo y validacion de stock.

## Criterios de aceptacion
- Ensamble y desensamble dejan trazabilidad completa.
- No se permite mover stock en periodo cerrado.
- Una revalorizacion deja impacto valorizado auditado.
