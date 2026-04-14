# Fase 12 - QA integral y readiness productiva

## Objetivo
Cerrar el ERP con una capa de verificacion operativa real que permita medir si cada empresa esta lista para operar de forma estable, auditable y mas cercana a un nivel Tango.

## Alcance funcional
- diagnostico ERP por empresa
- chequeo de configuracion minima obligatoria
- chequeo de readiness fiscal/ARCA
- chequeo de ventas, compras, caja e inventario
- lectura ejecutiva de bloqueos y advertencias
- endpoint API para integraciones internas o monitoreo

## Requerimientos funcionales
### 1. Diagnostico operativo
El sistema debe informar:
- estado general del ERP
- porcentaje de readiness
- bloqueos criticos
- advertencias
- items operativos listos

### 2. Chequeos minimos
Debe validar al menos:
- empresa activa
- moneda base y monedas activas
- sucursales activas
- usuarios activos
- sistemas asignados
- depositos activos
- productos activos
- puntos de venta y comprobantes
- caja abierta o definida
- configuracion fiscal/ARCA

### 3. Criterio ejecutivo
El resultado debe clasificar:
- `ready`
- `warning`
- `blocked`

## Requerimientos tecnicos
- metodo web en `DashboardController`
- endpoint API en `Api/V1/DashboardController`
- vista propia de diagnostico
- reutilizacion de tablas ya existentes
- sin romper modulos actuales

## Criterios de aceptacion
- una empresa puede consultar su readiness actual
- el dashboard expone diagnostico resumido
- la API devuelve el mismo diagnostico de forma estructurada
- los bloqueos criticos se distinguen de advertencias

## Comparacion contra Tango
- Acerca a `Codex` a una salida profesional porque agrega criterio de puesta en marcha y control operativo visible.
- Tango suele tener mucha madurez operativa por acumulacion funcional; esta fase hace que `Codex` sea mas auditable y administrable en la practica.
- Lo que sigue faltando para igualar de forma real la salida productiva es QA funcional manual completo y homologacion viva de ARCA con credenciales reales.
