# Fase G - Hardware y operacion de mostrador

## Objetivo
Acercar la operacion POS/Kiosco al ecosistema retail real.

## Brecha con Tango
Tango suele integrar dispositivos y perifericos de punto de venta. Codex hoy no.

## Alcance funcional
- impresora fiscal / ticketera 80mm
- cajon portamonedas
- lector de codigo de barras
- integracion con POSNet/Lapos o gateway equivalente

## Requerimientos funcionales
- Enviar ticket a impresora configurada.
- Abrir cajon al registrar pago en efectivo.
- Leer codigo y autocompletar producto.
- Registrar identificador de transaccion del adquirente.

## Requerimientos tecnicos
- capa de adaptadores de dispositivos
- settings por empresa y puesto
- logs de errores de hardware e integracion

## Criterios de aceptacion
- El POS puede imprimir ticket real.
- El sistema registra medios electronicos con referencia externa.
