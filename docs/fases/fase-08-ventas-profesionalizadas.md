# Fase 08 - Ventas profesionalizadas

## Objetivo

Llevar `Codex Ventas` a un nivel comercial más cercano a un ERP profesional:

- maestros comerciales por empresa
- asignacion de vendedor, zona y condicion comercial
- datos comerciales persistidos en clientes y comprobantes
- base lista para comisiones, seguimiento y gestion mas fina

## Alcance funcional

- Alta de vendedores comerciales.
- Alta de zonas comerciales.
- Alta de condiciones de venta.
- Los clientes pueden quedar vinculados a:
  - vendedor
  - zona
  - condicion comercial
- Las ventas pueden tomar esos valores por defecto y editarlos en el borrador.
- La condicion comercial aporta:
  - plazo dias
  - limite de credito
  - requerimiento de factura

## Alcance tecnico

- tablas:
  - `sales_agents`
  - `sales_zones`
  - `sales_conditions`
- extension de:
  - `customers`
  - `sales`
- web y API REST para maestros comerciales

## Criterios de aceptacion

- El usuario puede gestionar maestros comerciales desde `Ventas`.
- El cliente guarda su contexto comercial.
- El borrador de venta puede operar con vendedor, zona y condicion.
