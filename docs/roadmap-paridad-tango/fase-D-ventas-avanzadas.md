# Fase D - Ventas avanzadas nivel Tango

## Objetivo
Profundizar la logica comercial de `Ventas` hasta un nivel comparable con `Tango`.

## Brecha con Tango
`Codex` ya tiene workflow, POS, kiosco, listas, promociones base, cobranzas y comisiones. Falta madurez comercial avanzada.

## Alcance funcional
- promociones complejas
- riesgo crediticio
- autorizaciones por perfil
- descuentos por medio de pago
- precios especiales por cliente
- entregas parciales mas maduras

## Requerimientos funcionales
- Promociones:
  - AXB
  - A+B
  - 2x1
  - escalas por cantidad
  - descuento por forma de pago
- Riesgo:
  - bloqueo por credito
  - alerta de mora
  - scoring basico de cliente
- Autorizaciones:
  - requerir aprobacion para descuentos mayores a umbral
  - requerir aprobacion para stock comprometido o sobreventa

## Requerimientos tecnicos
- nuevas tablas:
  - `sales_discount_policies`
  - `sales_authorizations`
  - `sales_credit_flags`
- ampliacion del motor de promociones y validacion comercial.

## Criterios de aceptacion
- El sistema puede aplicar promociones complejas sin manipulacion manual.
- La venta bloquea o advierte por credito/mora.
- La autorizacion queda trazable.
