# Carga asistida de facturas de proveedores

## Acceso y recorrido

En Compras, un admin o superadmin con gestión del módulo puede abrir el icono de nube «Cargar factura desde archivo». El acceso directo es /compras/importaciones?company_id=UUID.

1. Arrastrar un PDF, JPG o PNG. Una factura por archivo; el PDF puede tener hasta 20 páginas. Máximo 10 MB.
2. Con el motor local, la lectura se solicita automáticamente al subir el archivo o retomar una carga pendiente. El procesador se ejecuta cada minuto. El icono de estrellas permite reintentar; se puede continuar manualmente con la flecha.
3. Revisar proveedor, CUIT del emisor y destinatario, tipo, punto del proveedor, numeración, fechas y moneda.
4. Seleccionar si la mercadería tiene recepción existente, está pendiente de recibir, o se trata de servicios/gastos. Asociar productos y revisar conceptos, IVA y otros importes.
5. Comparar el total calculado con el total del original, marcar la revisión y confirmar con el icono de doble comprobación.

Al terminar la lectura, las sugerencias completan solamente campos vacíos y el asistente avanza a la revisión. El icono de varita permite volver a aplicarlas. Los valores ya escritos no se sobrescriben. Cada campo detectado muestra su estado; al enfocarlo se resalta su ubicación cuando está disponible. El OCR local aporta coordenadas de la línea; el texto PDF nativo puede no tener una ubicación disponible. En PDF se puede cambiar de página con los iconos del visor.

El guardado automático conserva el borrador en el servidor. Si aparece un conflicto o falla una petición de guardado, el asistente detiene los guardados para proteger los cambios. El icono de descarga permite guardar una copia JSON de los datos en edición antes de actualizar desde el servidor. Una confirmación que tarda demasiado se puede reintentar con la misma clave durante esa sesión; al recargar, el servidor informa si ya quedó registrada.

El icono de documento de la lista de facturas abre los datos registrados y el original. Los borradores pendientes y los últimos 50 registros también se pueden abrir desde el asistente.

## Qué modifica cada operación

Subir, leer y guardar un borrador no crean facturas, deuda, pagos ni movimientos de inventario.

La confirmación registra cabecera y conceptos, conserva el original, actualiza la cuenta a pagar y guarda asociaciones entre códigos del proveedor y productos que el admin haya confirmado. No recibe stock ni paga al proveedor.

Si se vincula una recepción, se verifica empresa, proveedor, moneda y cantidades acumuladas. Se reemplaza el costo provisional de las cantidades facturadas y se agregan los ajustes explícitos una sola vez sobre la cuenta a pagar existente, conservando pagos anteriores.

Las correspondencias de productos solo se guardan después de confirmar. Para proveedores nuevos, primero crear el proveedor desde Compras y luego volver al borrador.

Las facturas manuales y la API de compras utilizan PurchaseInvoiceService. Los clientes anteriores pueden seguir enviando invoice_number sin tipo separado; esos registros se consideran LEGACY. La numeración estructurada utiliza document_type, point_of_sale e invoice_sequence. Una identidad confirmada no se puede duplicar ni siquiera si el registro original se anuló. Los históricos sin identidad suficiente no se reclasifican por inferencia.

El IVA de los conceptos se calcula desde cantidad, costo y tasa cuando no se informa un importe explícito. El importe explícito prevalece, incluido cero. El desglose permite IVA adicional, otros impuestos, cargos y descuentos. Los impuestos de cabecera se proponen como importes pendientes de clasificación cuando no están explicados por las líneas; el admin debe verificar la clasificación contra el original. Los resultados OCR no constituyen validación fiscal.

## Instalación

~~~powershell
composer install
php spark migrate
pwsh -NoProfile -File scripts/setup-local-purchase-ocr.ps1
php spark compras:facturas-estado
~~~

Se agrega smalot/pdfparser 2.13.1 para validar y contar páginas de PDFs, con límite de descompresión de streams. Los archivos cifrados, PDFs no interpretables y documentos con acciones/formularios/adjuntos deben exportarse a una copia estática o subirse como imagen.

El visor local PDF.js 6.4.299 está distribuido en public/assets/lib/pdfjs, con licencia e integridad del paquete de origen. No necesita npm en el servidor. El original se renderiza en canvas sin ejecutar acciones PDF. Las imágenes admiten hasta 25 megapíxeles y de 50 a 10000 píxeles por lado.

PHP debe permitir upload_max_filesize de al menos 10M y post_max_size superior al límite de archivo; se recomienda 12M o más. La configuración inspeccionada de PHP CLI reportó 40M para ambos, pero el servidor web puede usar otro php.ini.

Los originales se guardan en writable/private/purchase_documents/EMPRESA/UUID.ext. Esta carpeta debe quedar fuera del document root y entrar en la política de respaldo junto con la base de datos. Nunca guardar originales en public. .gitignore excluye writable/private.

## Motor local predeterminado

La lectura predeterminada utiliza PHP, Smalot PDFParser, Tesseract 5.5.0 con español e inglés y Poppler 26.09.0. No requiere cuenta de Azure, API key ni llamadas de red para leer documentos.

El instalador PowerShell descarga herramientas de sus repositorios de origen y comprueba SHA-256 antes de ejecutarlas. El instalador oficial de Tesseract puede crear una instalación en el perfil del usuario; el script prepara además la copia portátil que utiliza Codex en writable/tools. Los ejecutables no se incluyen en Git. Los términos y licencias acompañan las distribuciones de las herramientas.

En Windows, las rutas predeterminadas son writable/tools/tesseract/tesseract.exe y writable/tools/poppler/poppler-26.09.0/Library/bin/pdftoppm.exe. En Linux, instalar tesseract-ocr, los idiomas español e inglés y poppler-utils mediante el gestor de paquetes del servidor.

Se pueden ajustar las rutas en el entorno:

~~~dotenv
PURCHASE_OCR_PROVIDER=local
PURCHASE_OCR_LANGUAGE=spa+eng
# Solo si las herramientas se instalaron en otras ubicaciones:
# PURCHASE_OCR_TESSERACT_PATH=C:/ruta/tesseract.exe
# PURCHASE_OCR_PDFTOPPM_PATH=C:/ruta/pdftoppm.exe
# PURCHASE_OCR_TESSDATA_PATH=C:/ruta/tessdata
~~~

Para cada página PDF se intenta extraer texto conservando su distribución visual mediante pdftotext. Cuando no hay suficiente texto, Poppler genera una imagen de hasta 2500 píxeles por lado y Tesseract la lee. Las imágenes JPG y PNG se leen directamente. Las páginas digitales y escaneadas se pueden combinar en un mismo PDF.

La interpretación es conservadora: propone los datos identificables y deja sin completar totales, fechas o CUIT ambiguos. Los renglones solo se proponen cuando se reconocen los encabezados y la cantidad de columnas; admite descripciones continuadas en otra línea. No confunde el número de establecimiento con el comprobante ni la vigencia del CAE/CAEA con el vencimiento de pago. Las percepciones y los impuestos internos de cabecera se conservan separados. Una tasa de IVA única se traslada a los conceptos solamente si su cálculo coincide con el IVA de cabecera; diferencias positivas de redondeo conservan su clasificación de IVA. Otros formatos requieren carga manual. No se garantiza reconocer todos los diseños de factura. Se conservan seis decimales para cantidades y costos. El precio de los conceptos debe revisarse como costo neto antes de registrar.

El estado «Detectado» significa que existe evidencia en el documento; no es un porcentaje de certeza. La confianza de Tesseract por palabra no se presenta como confianza contable de un campo.

Los procesos se ejecutan con argumentos separados, sin shell. Hay un límite de 25 segundos por proceso y 120 segundos por documento, además de límites de archivo, páginas, píxeles y tamaño de salida. No se impone una cuota dura de RAM del sistema operativo: se limita el tamaño de imágenes, streams PDF y salidas. Los temporales privados se retiran al finalizar, incluso cuando falla la lectura.

## Procesador periódico

Programar cada minuto:

~~~powershell
php spark compras:procesar-facturas --limit 10
~~~

En el Programador de tareas de Windows, utilizar la ruta absoluta de php.exe, pasar la ruta absoluta a spark y el comando anterior como argumentos, y establecer el directorio del proyecto en «Iniciar en». Configurar una única instancia. En Linux puede usarse cron con el mismo comando.

En este equipo se configuró la tarea Codex-PurchaseInvoiceImports para ejecutarse oculta cada minuto, con el usuario actual y sin superponer instancias. Requiere que ese usuario tenga sesión iniciada. En un servidor permanente debe configurarse con una cuenta de servicio y permisos sobre el proyecto y su almacenamiento.

El worker no mantiene transacciones abiertas durante OCR ni llamadas HTTP. Reserva trabajos con una concesión temporal y reintenta errores hasta tres veces. Una ejecución interrumpida se puede recuperar al vencer la concesión. Un resultado tardío de otro worker no sustituye un trabajo más reciente. Cada trabajo registra su motor, método por página y resultado; los registros anteriores mantienen el motor Azure.

No ejecutar php spark serve: el servidor existente es administrado por el usuario.

## Compatibilidad opcional con Azure

El adaptador anterior se conserva para el historial y para instalaciones que lo elijan expresamente con PURCHASE_OCR_PROVIDER=azure. Con el motor local, las nuevas lecturas no usan credenciales ni servicios externos.

Para una instalación que elija Azure, configurar PURCHASE_OCR_AZURE_ENDPOINT, PURCHASE_OCR_AZURE_KEY y PURCHASE_OCR_AZURE_TIER en el entorno seguro del servidor. No almacenar claves en el archivo .env versionado de este checkout. Los trabajos Azure existentes conservan ese motor al reanudarse; cambiar la configuración no convierte trabajos históricos a OCR local.

## Seguridad y trazabilidad

Cada lectura, edición, descarga y confirmación comprueba rol, empresa y acceso al módulo. Los archivos se identifican por UUID y se deduplican por SHA-256 dentro de cada empresa. El navegador nunca recibe rutas de disco, credenciales ni URLs de operación de Azure.

La confirmación y la numeración usan transacciones y bloqueo de empresa. Un índice único protege las identidades estructuradas. La versión del borrador impide sobrescribir otra revisión. El historial conserva cargas, revisiones y confirmación; cada trabajo finalizado conserva su extracción.

Los archivos OCR se tratan como datos: nunca se ejecutan instrucciones contenidas en ellos ni se utiliza su contenido para elegir URLs de red o rutas de almacenamiento.

## Verificación y límites

Las pruebas utilizan SQLite aislado, documentos sintéticos y respuestas Azure simuladas. Las pruebas locales de integración ejecutan Tesseract y Poppler sobre imágenes y un PDF mixto generados para la prueba. Se omiten cuando faltan las herramientas o fuentes necesarias. No generan compras reales ni envían documentos externos.

~~~powershell
php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage --filter 'PurchaseImportTest|LocalInvoiceExtractorTest|InvoiceTextInterpreterTest|AzureInvoiceExtractorTest|PurchaseIntegrityTest|PurchasesDashboardTest' tests
node --test tests/javascript/purchase-imports.test.mjs
~~~

Ejecutar un piloto representativo de proveedores, revisar encabezados, renglones, impuestos y páginas y medir las correcciones necesarias. El estado «disponible» confirma dependencias detectadas, no precisión para todos los formatos.

Esta entrega no incluye carga masiva, consulta fiscal en línea ni pagos. La conciliación de impuestos sigue requiriendo revisión humana.

Fuentes técnicas: [Tesseract](https://tesseract-ocr.github.io/tessdoc/), [Poppler para Windows](https://github.com/oschwartz10612/poppler-windows/releases), [PDF.js](https://mozilla.github.io/pdf.js/examples/), [Smalot PDF parser](https://github.com/smalot/pdfparser), [OWASP File Upload](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html).
