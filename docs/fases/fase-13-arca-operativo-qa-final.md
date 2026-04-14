# Fase 13 - ARCA operativo y QA final

## Objetivo
Dejar a `Codex` preparado para una salida real a homologacion/produccion y exponer un QA funcional integral por modulo.

## Alcance
- diagnostico real de certificado y clave privada
- verificacion de bundle fiscal ARCA
- readiness por ambiente
- checklist QA por modulo
- salida web y API para diagnostico final

## Requerimientos funcionales
### ARCA operativo
- validar existencia de certificado
- validar existencia de clave privada
- verificar que certificado y clave correspondan
- detectar vigencia del certificado
- mostrar readiness por ambiente
- registrar evidencias de pruebas fiscales

### QA integral
- compras
- inventario
- ventas
- cobranzas
- caja
- fiscal

Cada modulo debe exponer:
- estado
- score
- bloqueos
- advertencias

## Requerimientos tecnicos
- ampliacion de `ArcaService`
- endpoints web/API de diagnostico ARCA
- pantalla `dashboard/qa`
- checklist reutilizando tablas ya existentes

## Criterios de aceptacion
- el sistema informa si el bundle fiscal local es valido o no
- el dashboard muestra QA integral por modulo
- la API devuelve diagnostico estructurado

## Comparacion contra Tango
- Acerca a `Codex` a una salida profesional real porque ya no solo tiene funcionalidades: tambien tiene control de readiness y QA.
- Sigue faltando la homologacion/produccion viva con credenciales oficiales del cliente para afirmar paridad total en fiscalidad productiva.
