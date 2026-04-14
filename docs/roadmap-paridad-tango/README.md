# Roadmap de Paridad Operativa Codex vs Tango

## Objetivo
Definir y ejecutar el plan de desarrollo necesario para llevar `Codex` a un nivel de operacion comparable con `Tango Gestion`, enfocando las brechas reales detectadas en:

- Compras
- Inventario
- Ventas
- Caja y Tesoreria
- Fiscal Argentina / ARCA
- Contabilidad e IVA
- QA y hardening productivo

## Estado actual resumido

### Ya cubierto en Codex
- Multiempresa
- Usuarios, roles y permisos por sistema
- Compras base con proveedores, ordenes, recepciones, devoluciones y cuentas a pagar
- Inventario con depositos, ubicaciones, lotes, series, vencimientos, reservas, costeo por capas y kardex valorizado
- Ventas con workflow documental base, POS, kiosco, clientes, listas, promociones, cobranzas, caja y reportes
- Auditoria, eventos documentales y logs de integracion
- API REST para los modulos principales

### Brechas principales frente a Tango
- ARCA productivo real con credenciales vivas
- Libro IVA compras y ventas
- Asientos contables automaticos
- Tesoreria avanzada y conciliacion
- Promociones comerciales complejas
- Compras financieras mas profundas
- Inventario con ensamble/desensamble operativo y cierre por periodo
- Integracion con hardware de punto de venta
- QA funcional end-to-end y hardening de concurrencia

## Fases del roadmap
1. `Fase A`: Fiscal real y ARCA productivo
2. `Fase B`: Contabilidad e IVA
3. `Fase C`: Tesoreria avanzada
4. `Fase D`: Ventas avanzadas nivel Tango
5. `Fase E`: Compras avanzadas nivel Tango
6. `Fase F`: Inventario avanzado operativo
7. `Fase G`: Hardware y operacion de mostrador
8. `Fase H`: QA integral y hardening productivo

## Orden recomendado de ejecucion
1. Fase A
2. Fase B
3. Fase C
4. Fase D
5. Fase E
6. Fase F
7. Fase G
8. Fase H

## Criterio de paridad operativa con Tango
Consideraremos `Codex` a la altura operativa de `Tango` cuando cumpla, como minimo:

- circuito completo compra -> stock -> venta -> cobranza -> caja
- fiscalidad argentina viva en homologacion y produccion
- libro IVA compras y ventas
- integracion contable automatica por comprobante
- tesoreria operativa con conciliacion y medios de pago profundos
- promociones y politica comercial avanzadas
- inventario con costeo maduro y operaciones logisticas completas
- QA funcional integral y control de concurrencia real

## Archivos de esta carpeta
- [00-matriz-brechas-codex-vs-tango.md](./00-matriz-brechas-codex-vs-tango.md)
- [fase-A-fiscal-arca-productivo.md](./fase-A-fiscal-arca-productivo.md)
- [fase-B-contabilidad-iva.md](./fase-B-contabilidad-iva.md)
- [fase-C-tesoreria-avanzada.md](./fase-C-tesoreria-avanzada.md)
- [fase-D-ventas-avanzadas.md](./fase-D-ventas-avanzadas.md)
- [fase-E-compras-avanzadas.md](./fase-E-compras-avanzadas.md)
- [fase-F-inventario-operativo.md](./fase-F-inventario-operativo.md)
- [fase-G-hardware-mostrador.md](./fase-G-hardware-mostrador.md)
- [fase-H-qa-hardening.md](./fase-H-qa-hardening.md)
