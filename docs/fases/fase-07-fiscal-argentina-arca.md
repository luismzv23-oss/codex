# Fase 07 - Fiscal Argentina y ARCA

## Objetivo

Elevar `Codex Ventas` a una operativa fiscal argentina más cercana a un ERP profesional:

- configuracion fiscal por empresa
- control de servicios ARCA habilitados
- estado fiscal por comprobante
- bitacora de eventos y solicitudes
- autorizacion y consulta fiscal desde web y API

## Alcance funcional

### 1. Configuracion fiscal por empresa

- Alias fiscal interno de la empresa.
- CUIT, condicion IVA, IIBB e inicio de actividades.
- Ambiente `homologacion` o `produccion`.
- servicios ARCA habilitados:
  - WSAA
  - WSFEv1
  - WSMTXCA
  - WSFEXv1
  - WSBFEv1
  - WSCT
  - WSSEG
- rutas a certificado, clave privada y cache de TA.
- opcion de autorizacion automatica al confirmar comprobantes fiscales.

### 2. Estado fiscal por comprobante

Cada venta fiscalizable debe registrar:

- estado ARCA
- servicio ARCA utilizado
- CAE
- vencimiento del CAE
- fecha de autorizacion
- ultimo control
- codigo y mensaje de resultado

### 3. Bitacora ARCA

Registrar eventos por empresa y por comprobante:

- test de autenticacion
- consulta de estado
- solicitud de autorizacion
- respuesta recibida
- errores de configuracion

### 4. Operacion web

- Configuracion de Ventas muestra:
  - readiness fiscal
  - checklist de configuracion
  - servicios habilitados
  - eventos recientes
- El listado de ventas muestra estado fiscal del comprobante.
- Cada comprobante fiscalizable puede:
  - autorizarse
  - consultarse

### 5. API REST

Exponer endpoints para:

- obtener estado fiscal de la empresa
- probar autenticacion
- listar eventos ARCA
- autorizar comprobantes
- consultar estado de un comprobante

## Alcance tecnico

### Persistencia

- extender `sales_settings`
- extender `sales`
- crear `sales_arca_events`

### Servicio fiscal

`ArcaService` centraliza:

- definicion de servicios
- readiness de configuracion
- resolucion de servicio por tipo de comprobante
- construccion de payload fiscal
- simulacion/localizacion del resultado de autorizacion

### Integracion

- Web: `SalesController`
- API: `Api\V1\SalesController`
- Modelos:
  - `SaleModel`
  - `SalesSettingModel`
  - `SalesArcaEventModel`

## Criterios de aceptacion

- La empresa puede ver si su configuracion fiscal esta lista o incompleta.
- Cada comprobante fiscalizable exhibe estado ARCA.
- Web y API permiten disparar autorizacion y consulta.
- Cada accion deja bitacora persistente.
- La fase queda integrada a ventas sin romper el circuito comercial existente.

## Comparacion con Tango

Con esta fase, `Codex` se acerca más a la capa fiscal operativa que Tango suele resolver en ventas:

- mejor trazabilidad fiscal por documento
- mayor visibilidad de readiness y servicios
- control explícito de autorizacion fiscal

Todavia no reemplaza una homologacion/produccion real con credenciales oficiales y conexion viva a ARCA. Esa parte queda preparada estructuralmente, pero depende de certificados, CUIT y pruebas reales del contribuyente.
