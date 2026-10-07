# Importacion de facturas de proveedores

En Compras, el acceso **Importar factura** abre un asistente de cuatro pasos: documento, datos, productos y revision. Acepta PDF, JPG, JPEG y PNG de hasta 10 MB. La lectura local admite hasta 20 paginas de PDF o imagenes de 20 megapixeles. Los datos sugeridos requieren revision; los formatos no reconocidos pueden completarse manualmente.

El original se archiva cifrado al cargarlo. El borrador permite continuar despues y usa control de revision para evitar sobrescrituras concurrentes. Los productos y documentos se paginan de cinco en cinco sin recargar la pantalla. Antes de registrar se comparan neto, IVA y total con los importes del original y se exige confirmar la revision.

La confirmacion reutiliza el registro de facturas de Compras y su sincronizacion de obligaciones. No crea una recepcion de mercaderia ni un pago adicional. Puede vincularse una recepcion existente. El original puede descargarse desde la factura. Un mismo archivo dentro de una empresa recupera su documento existente; confirmar nuevamente no genera otra factura.

## Instalacion

1. Ejecutar `php spark migrate`.
2. Crear un entorno Python en `.venv-pdf` e instalar `scripts/pdf/requirements.txt`.
3. Ejecutar `php spark purchase-documents:setup` una sola vez; si existe una clave, se verifica sin reemplazarla.
4. El proceso PHP necesita OpenSSL, fileinfo, `proc_open` y acceso al Python configurado. Los limites PHP `upload_max_filesize` y `post_max_size` deben permitir la carga de 10 MB, incluyendo el margen del formulario.

Se pueden configurar `purchaseDocuments.python` y `purchaseDocuments.keyFile` en el entorno. Por defecto, Python se busca en `.venv-pdf/Scripts/python.exe` (Windows) o `.venv-pdf/bin/python` (Linux).

Los originales se guardan en `{writable}/private/purchase-documents/`; la clave predeterminada esta en `{writable}/keys/purchase-documents.key`. Ambos quedan fuera del directorio publico y del repositorio. Restringir sus permisos al usuario del servicio; en Windows configurar permisos NTFS. Respaldar la base de datos y los archivos cifrados, y custodiar una copia separada de la clave. Perder la clave impide recuperar documentos y borradores. No regenerarla al restaurar una instalacion.

Se usa AES-256-GCM con una clave aleatoria por objeto, protegida por la clave maestra. La autenticacion vincula el contenido a empresa, documento y tipo de objeto. Se verifica tambien el hash del original. Las consultas respetan el permiso de Compras y la empresa activa, y registran eventos de carga, borrador, registro, vista y descarga.

La extraccion y las vistas previas se procesan localmente, sin enviar documentos a servicios de IA externos. La vista previa es una imagen renderizada; la descarga conserva el archivo original. El OCR propone datos, no garantiza interpretar cualquier distribucion de factura. Los impuestos adicionales no representados deben resolverse antes de confirmar; no se ajustan importes automaticamente para forzar coincidencias.

## Verificacion 2026-10-05

- Suite PHP: 165 pruebas, 804 aserciones correctas (SQLite habilitado para pruebas).
- Casos nuevos: cifrado y alteracion, aislamiento por empresa, duplicados, borradores y concurrencia, confirmacion idempotente, validacion de importes y productos, conversion de unidades y descuentos.
- Lectura real local de fixtures PNG, JPG, JPEG, PDF escaneado y PDF con texto; vistas previas PNG correctas para los cinco formatos.
- Navegador Chrome: carga, revision, productos y confirmacion con API simulada, sin errores JavaScript y sin desbordamiento horizontal a 390 px.
- No se registro una compra real durante estas verificaciones. La prueba de navegador no sustituye una prueba autenticada con un comprobante real del proveedor.
