# Fase H - QA integral y hardening productivo

## Objetivo
Cerrar la brecha entre funcionalidad implementada y operacion confiable en un entorno real.

## Brecha con Tango
Tango tiene madurez acumulada por años de uso real. Codex necesita pruebas integrales y endurecimiento.

## Alcance funcional
- QA manual por modulo
- pruebas cruzadas entre modulos
- pruebas de concurrencia
- validaciones de regresion
- checklist de salida a operacion

## Requerimientos funcionales
- Ejecutar escenarios completos:
  - compra -> recepcion -> stock
  - pedido -> remito -> factura -> cobranza -> caja
  - devoluciones
  - cierre de caja
  - autorizacion fiscal
- Medir y registrar errores de concurrencia y consistencia.

## Requerimientos tecnicos
- suite de pruebas funcionales y smoke tests
- seeds de datos de QA
- tareas de verificacion post-migracion
- reportes de readiness y de gaps abiertos

## Criterios de aceptacion
- No hay errores criticos en flujos principales.
- El sistema conserva consistencia documental, fiscal, financiera y de stock.
- Existe checklist firmado de salida a operacion.
