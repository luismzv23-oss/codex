# Fase A - Fiscal real y ARCA productivo

## Objetivo
Llevar la capa fiscal de `Ventas` desde readiness estructural a operacion viva contra ARCA en homologacion y produccion.

## Brecha con Tango
`Codex` ya modela configuracion, eventos y autorizacion simulada/local. `Tango` opera con emision fiscal real y manejo de contingencias vivas.

## Alcance funcional
- Alta y validacion de:
  - certificado
  - clave privada
  - CUIT
  - ambiente homologacion / produccion
- Emision real de CAE por comprobante fiscalizable.
- Consulta real de estado del comprobante.
- Reintentos y trazabilidad de errores.
- Cambio controlado de ambientes.

## Requerimientos funcionales
- Diagnostico de bundle fiscal por empresa.
- Test de autenticacion WSAA.
- Emision fiscal para `Factura A/B/C/M`, `Ticket`, `NC`, `ND`.
- Consulta posterior de comprobante.
- Registro de errores por:
  - credenciales
  - ambiente
  - servicio
  - datos fiscales invalidos

## Requerimientos tecnicos
- Extender `ArcaService` para conexion real.
- Persistencia segura de rutas y metadatos del bundle.
- Cache de TA por ambiente y empresa.
- Logs request/response anonimizados en `sales_arca_events` e `integration_logs`.
- Manejo de timeout, reintento y mensajes funcionales.

## Pantallas afectadas
- Configuracion de ventas
- Listado de ventas
- PDF fiscal
- Dashboard readiness

## API afectada
- `POST /api/v1/sales/arca/test-auth`
- `POST /api/v1/sales/{id}/arca/authorize`
- `POST /api/v1/sales/{id}/arca/consult`
- nuevos endpoints de validacion del bundle fiscal

## Criterios de aceptacion
- Una factura valida obtiene CAE real en homologacion.
- Una consulta recupera estado real del comprobante.
- El sistema distingue claramente homologacion y produccion.
- Un error fiscal deja evento, mensaje y detalle tecnico.

## Dependencias
- certificados reales
- clave privada real
- CUIT del contribuyente
- endpoints ARCA disponibles
