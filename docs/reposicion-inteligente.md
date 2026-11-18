# Reposición inteligente (Compras)

Análisis del inventario para comprar a tiempo, en la cantidad justa y sin inmovilizar capital.
Pantalla: **Compras → ✨ Reposición inteligente** (`/compras/reposicion`).

## Qué hace

1. **Mide la demanda real** de cada producto simple activo: egresos de stock por ventas y remitos (incluye los componentes que consumen los kits), menos devoluciones y anulaciones. La demanda se agrupa por semana. Los ajustes manuales no cuentan como demanda.
2. **Pronostica la demanda semanal**:
   - Holt amortiguado (nivel + tendencia) para demanda regular.
   - Croston-SBA para demanda intermitente (semanas sin venta frecuentes).
   - Promedio simple con menos de 3 semanas con ventas.
3. **Determina el plazo de entrega** con este orden de prioridad: plazo observado (emisión de la OC → recepción, con al menos 2 casos), plazo cargado en la ficha del producto, promedio del proveedor y, por último, el valor por defecto.
4. **Calcula la política de compra (R, S)**:
   - Stock de seguridad = z × σ diario × √plazo, nunca menor al stock mínimo del producto.
   - Punto de pedido = demanda diaria × plazo + stock de seguridad.
   - Nivel objetivo = demanda × (plazo + frecuencia de compra) + seguridad, limitado por el stock máximo.
   - Se compra cuando disponible + en camino (OC en borrador, aprobadas o parcialmente recibidas) no alcanza hasta la próxima revisión. La cantidad se redondea al lote mínimo y al múltiplo de compra.
5. **Asigna prioridad**:
   - **Crítica**: sin stock, o se quiebra antes de que llegue la compra.
   - **Alta**: por debajo del punto de pedido.
   - **Media**: llegaría al punto de pedido antes de la próxima revisión.
6. **Clasifica los productos**:
   - **ABC** por consumo valorizado anual (A: 80 %, B: 15 %, C: 5 %). Si el producto no tiene costo, se usa el precio de venta.
   - **XYZ** por coeficiente de variación semanal.
7. **Detecta capital a liberar**:
   - Stock inmovilizado: productos con stock pero sin ventas en la ventana analizada.
   - Sobrestock: productos con stock por encima del máximo o de la cobertura razonable (nivel objetivo + 90 días).
8. **Informe ejecutivo con IA**: el resultado se envía resumido a `CodexAssistService`, que usa Ollama u OpenAI según `.env`. Si el modelo no responde, se muestran los hallazgos calculados por reglas.
9. **Genera órdenes de compra en borrador**, una por proveedor, con las líneas seleccionadas. Antes de generarlas se pueden ajustar la cantidad, el proveedor y el costo de cada línea. Las órdenes siguen el circuito habitual: aprobación, recepción y factura.

## Parámetros por producto

En **Inventario → producto → Reposición** se cargan el proveedor habitual, el plazo de entrega, el lote mínimo y el múltiplo de compra.
Si se dejan vacíos, se usan el proveedor de la última compra y el plazo observado.

## Ejecución programada

```bash
php spark compras:reposicion                     # todas las empresas, guarda la corrida
php spark compras:reposicion <company_id> --ia   # una empresa + informe IA
php spark compras:reposicion --simular           # muestra el resultado sin guardar
php spark compras:reposicion --dias 180 --servicio 97.5 --revision 14
```

Sugerencia: programarlo con cron o con el Programador de tareas de Windows los lunes a las 7:00, para que el responsable de compras encuentre el análisis listo.

## Configuración de IA (`.env`)

```ini
codex.assist.provider = ollama        # u openai
codex.assist.model = llama3.2
codex.assist.url = http://localhost:11434
codex.assist.api_key =                # solo para openai
```

## API

`POST /api/v1/mrp/calculate` usa el mismo motor. Acepta `warehouse_id`, `history_days`, `service_level`, `review_days` y `default_lead_time`.

## Calidad de datos

La precisión del análisis depende de la calidad de los datos cargados:
- **Costos de compra**: sin ellos, la inversión estimada queda subestimada. El análisis indica cuántos productos no tienen costo.
- **Proveedor habitual**: sin él, la línea no se puede convertir en orden de compra hasta que se elija un proveedor.
- **Recepciones vinculadas a sus órdenes**: son las que permiten medir el plazo de entrega real.
