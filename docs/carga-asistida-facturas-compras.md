# Carga asistida de facturas de proveedores

## Acceso y recorrido

En Compras, un admin o superadmin con gestión del módulo puede abrir el icono de nube «Cargar factura desde archivo». El acceso directo es /compras/importaciones?company_id=UUID.

1. Arrastrar un PDF, JPG o PNG. Una factura por archivo; el PDF puede tener hasta 20 páginas. Máximo 10 MB.
2. Solicitar la lectura con el icono de estrellas o continuar manualmente con la flecha.
3. Revisar proveedor, CUIT del emisor y destinatario, tipo, punto del proveedor, numeración, fechas y moneda.
4. Seleccionar si la mercadería tiene recepción existente, está pendiente de recibir, o se trata de servicios/gastos. Asociar productos y revisar conceptos, IVA y otros importes.
5. Comparar el total calculado con el total del original, marcar la revisión y confirmar con el icono de doble comprobación.

Las sugerencias se aplican mediante el icono de varita y completan solamente campos vacíos. Los valores ya escritos no se sobrescriben. Cada campo detectado muestra su estado; al enfocarlo se resalta su ubicación si Azure la informó. En PDF se puede cambiar de página con los iconos del visor.

El guardado automático conserva el borrador en el servidor. Si aparece un conflicto o falla una petición de guardado, el asistente detiene los guardados para proteger los cambios. El icono de descarga permite guardar una copia JSON de los datos en edición antes de actualizar desde el servidor. Una confirmación que tarda demasiado se puede reintentar con la misma clave durante esa sesión; al recargar, el servidor informa si ya quedó registrada.

El icono de documento de la lista de facturas abre los datos registrados y el original. Los borradores pendientes y los últimos 50 registros también se pueden abrir desde el asistente.

## Qué modifica cada operación

Subir, leer y guardar un borrador no crean facturas, deuda, pagos ni movimientos de inventario.

La confirmación registra cabecera y conceptos, conserva el original, actualiza la cuenta a pagar y guarda asociaciones entre códigos del proveedor y productos que el admin haya confirmado. No recibe stock ni paga al proveedor.

Si se vincula una recepción, se verifica empresa, proveedor, moneda y cantidades acumuladas. Se reemplaza el costo provisional de las cantidades facturadas y se agregan los ajustes explícitos una sola vez sobre la cuenta a pagar existente, conservando pagos anteriores.

Las correspondencias de productos solo se guardan después de confirmar. Para proveedores nuevos, primero crear el proveedor desde Compras y luego volver al borrador.

Las facturas manuales y la API de compras utilizan PurchaseInvoiceService. Los clientes anteriores pueden seguir enviando invoice_number sin tipo separado; esos registros se consideran LEGACY. La numeración estructurada utiliza document_type, point_of_sale e invoice_sequence. Una identidad confirmada no se puede duplicar ni siquiera si el registro original se anuló. Los históricos sin identidad suficiente no se reclasifican por inferencia.

El IVA de los conceptos se calcula desde cantidad, costo y tasa cuando no se informa un importe explícito. El importe explícito prevalece, incluido cero. El desglose permite IVA adicional, otros impuestos, cargos y descuentos. «TotalTax» de Azure se propone como impuesto pendiente de clasificación cuando no está explicado por las líneas; el admin debe verificar la clasificación contra el original. Los resultados OCR no constituyen validación fiscal.

## Instalación

~~~powershell
composer install
php spark migrate
php spark compras:facturas-estado
~~~

Se agrega smalot/pdfparser 2.13.1 para validar y contar páginas de PDFs, con límite de descompresión de streams. Los archivos cifrados, PDFs no interpretables y documentos con acciones/formularios/adjuntos deben exportarse a una copia estática o subirse como imagen.

El visor local PDF.js 6.4.299 está distribuido en public/assets/lib/pdfjs, con licencia e integridad del paquete de origen. No necesita npm en el servidor. El original se renderiza en canvas sin ejecutar acciones PDF. Las imágenes admiten hasta 25 megapíxeles y de 50 a 10000 píxeles por lado.

PHP debe permitir upload_max_filesize de al menos 10M y post_max_size superior al límite de archivo; se recomienda 12M o más. La configuración inspeccionada de PHP CLI reportó 40M para ambos, pero el servidor web puede usar otro php.ini.

Los originales se guardan en writable/private/purchase_documents/EMPRESA/UUID.ext. Esta carpeta debe quedar fuera del document root y entrar en la política de respaldo junto con la base de datos. Nunca guardar originales en public. .gitignore excluye writable/private.

## Activar Azure Document Intelligence

Configurar en el entorno del servidor, sin publicar valores ni guardarlos en Git:

~~~dotenv
PURCHASE_OCR_AZURE_ENDPOINT=https://NOMBRE-DEL-RECURSO.cognitiveservices.azure.com
PURCHASE_OCR_AZURE_KEY=CLAVE_DEL_RECURSO
PURCHASE_OCR_AZURE_TIER=S0
~~~

El endpoint y la clave son de Azure Document Intelligence. La versión del adaptador es 2024-11-30 y el modelo es prebuilt-invoice. El botón de lectura envía el original al servicio externo configurado; sus condiciones, región y costos corresponden a la cuenta de Azure del operador.

Para el nivel F0, indicar F0. El adaptador limita la lectura de ese nivel a 2 páginas y 4 MB. Además verifica que Azure devuelva todas las páginas esperadas para evitar interpretar como completa una respuesta truncada.

Programar cada minuto:

~~~powershell
php spark compras:procesar-facturas --limit 10
~~~

En el Programador de tareas de Windows, utilizar la ruta absoluta de php.exe, pasar la ruta absoluta a spark y el comando anterior como argumentos, y establecer el directorio del proyecto en «Iniciar en». Configurar una única instancia. En Linux puede usarse cron con el mismo comando.

El worker no mantiene transacciones abiertas durante llamadas HTTP. Reserva trabajos con una concesión temporal, persiste la URL de la operación para continuar la consulta y reintenta errores hasta tres veces. Una ejecución interrumpida se puede recuperar al vencer la concesión. Un resultado tardío de otro worker no sustituye un trabajo más reciente.

No ejecutar php spark serve: el servidor existente es administrado por el usuario.

## Seguridad y trazabilidad

Cada lectura, edición, descarga y confirmación comprueba rol, empresa y acceso al módulo. Los archivos se identifican por UUID y se deduplican por SHA-256 dentro de cada empresa. El navegador nunca recibe rutas de disco, credenciales ni URLs de operación de Azure.

La confirmación y la numeración usan transacciones y bloqueo de empresa. Un índice único protege las identidades estructuradas. La versión del borrador impide sobrescribir otra revisión. El historial conserva cargas, revisiones y confirmación; cada trabajo finalizado conserva su extracción.

Los archivos OCR se tratan como datos: nunca se ejecutan instrucciones contenidas en ellos ni se utiliza su contenido para elegir URLs de red o rutas de almacenamiento.

## Verificación y límites

Las pruebas utilizan SQLite aislado, documentos sintéticos y respuestas Azure simuladas. No generan compras reales ni envían documentos externos.

~~~powershell
php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage --filter 'PurchaseImportTest|AzureInvoiceExtractorTest|PurchaseIntegrityTest|PurchasesDashboardTest' tests
node --test tests/javascript/purchase-imports.test.mjs
~~~

Antes de habilitar la lectura con documentos reales, ejecutar un piloto representativo de proveedores, revisar encabezados, renglones, impuestos y páginas y medir las correcciones necesarias. El estado «configurado» no confirma credenciales, conectividad ni precisión.

Esta entrega no incluye carga masiva, consulta fiscal en línea ni pagos. La conciliación de impuestos sigue requiriendo revisión humana.

Fuentes técnicas: [Azure Invoice](https://learn.microsoft.com/en-us/azure/ai-services/document-intelligence/prebuilt/invoice?view=doc-intel-4.0.0), [PDF.js](https://mozilla.github.io/pdf.js/examples/), [Smalot PDF parser](https://github.com/smalot/pdfparser), [OWASP File Upload](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html).
