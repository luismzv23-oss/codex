# Fase 10 - Auditoria y robustez

## Objetivo

Agregar trazabilidad operativa sobre acciones criticas de ventas, documentos y servicios.

## Alcance funcional

- bitacora de auditoria por accion
- eventos por documento comercial
- logs de integracion
- consulta de auditoria por API

## Alcance tecnico

- tablas:
  - `audit_logs`
  - `document_events`
  - `integration_logs`
- registro automatico en:
  - alta de clientes
  - alta de borradores
  - confirmacion
  - cancelacion
  - devoluciones
  - cobranzas
  - eventos ARCA

## Criterios de aceptacion

- Cada operacion critica deja traza persistente.
- Se puede consultar la actividad reciente desde API y desde el modulo de ventas.
