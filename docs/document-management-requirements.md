# Gestión de Documentos: análisis para validación

## Situación comprobada en este checkout

El catálogo actual contiene siete sistemas: Caja, Comercial, Compras, Contabilidad, Impuestos, Inventario y Ventas. No existe una entrada con slug `documentos`, un manifiesto de Gestión Documental ni tablas específicas de su ciclo de vida. La creación deberá ser aditiva, preservando los siete registros y todas sus asignaciones.

Core ya ofrece autenticación compartida, contexto de empresa, autorización por sistema, manifiestos, navegación derivada, configuración por sistema y eventos persistentes. No corresponde crear usuarios, empresas o sesiones paralelas dentro de Documentos.

Hay almacenamiento privado de comprobantes de Compras, pero sus reglas de PDF/imágenes y su finalidad no equivalen a un repositorio documental. Se reutilizarán convenciones de seguridad apropiadas, sin cambiar ese circuito ni convertir sus tablas en el repositorio nuevo.

El servicio de notificaciones actual captura errores de inserción sin propagarlos. Para una entrega durable de avisos se necesita un adaptador que confirme la escritura o comunique el fallo; no se debe marcar una notificación como entregada solo porque el método actual terminó.

La ejecución operativa del worker de la entrega D continúa pendiente de autorización. En la última comprobación no había eventos en su cola. El piloto de procesos asíncronos deberá comprobar su ejecución real, además de sus pruebas aisladas.

## Objetivo y reglas

Crear un sistema único de Gestión de Documentos, continuando la identidad previamente propuesta: nombre funcional Gestión Documental, slug `documentos` y entrada `/documentos`. El nombre visible se podrá ajustar sin cambiar el slug ni duplicar el sistema.

Permitir identificar autor, revisores, aprobadores, versión vigente, clasificación, destinatarios, accesos, revisión prevista, conservación y evidencia de acciones. Distinguir documentos que evolucionan de registros que acreditan hechos: un registro no se sobrescribe y su corrección se realiza mediante una rectificación relacionada.

Reglas acordadas:

- CodeIgniter y MySQL existentes; interfaz interactiva con Alpine.js CSP y coherencia visual con Core. Sin otro framework JavaScript para el nuevo módulo.
- Configuraciones, catálogos documentales, plantillas y parámetros bajo **Configuración**. La operación de documentos no tendrá una administración paralela de parámetros.
- Asignación de sistema y facultades de usuarios bajo **Sistemas**. Se necesitan facultades separadas para consultar, crear/editar, revisar, aprobar, publicar, descargar y autorizar disposición; `manage` por sí solo no debe otorgarlas indistintamente. Su adopción será un piloto aditivo de Documentos, sin reinterpretar los accesos de los demás sistemas.
- Autorización por empresa, área/alcance, rol y documento, aplicada igualmente a web, API, búsqueda, metadatos, versiones, vistas previas, exportaciones y descarga directa.
- Aprobación ligada a una versión inmutable e identificable. Una versión nueva no hereda la aprobación de la anterior. La versión publicada anterior sigue vigente mientras la nueva está en preparación/revisión, hasta su sustitución autorizada.
- Archivos privados, huella digital, validación de contenido y cuarentena. Un archivo pendiente, rechazado o no analizado no queda habilitado para revisión, publicación, vista previa o descarga ordinaria.
- El usuario indicó anteriormente que dispone de ClamAV, pero su ruta/configuración no está validada en este checkout. No instalar servicios ni declarar un análisis antivirus correcto sin comprobar ejecutable, firmas y resultados. Si no está disponible, conservar cuarentena y un estado recuperable.
- Conservación y bloqueos documentales impiden disponer del archivo aunque un usuario tenga permisos generales de gestión. El acceso de administración no debe saltarse un bloqueo de conservación.
- Auditoría documental de acciones reales; el registro genérico de eventos no sustituye la evidencia de quién aprobó una versión o quién descargó un archivo.
- La alineación normativa requiere una matriz de aplicabilidad, procedimientos, responsables y pruebas, con ediciones verificadas y textos disponibles. No se declarará certificación ISO por implementar pantallas.

## Entregas funcionales propuestas

| Entrega | Resultado revisable |
|---|---|
| 1. Integración y repositorio | Manifiesto, alta técnica, accesos según asignaciones, configuración central, facultades específicas en Sistemas, repositorio por empresa, clasificación, metadatos, carga privada/cuarentena, búsqueda por metadatos, versiones y auditoría básica. |
| 2. Revisión, aprobación y vigencia | Responsables, circuitos secuenciales o paralelos, observaciones, devolución, aprobación de versión, publicación, sustitución de vigente, distribución y constancias de lectura. Avisos mediante entregas durables verificadas. |
| 3. Expedientes, conservación y auditoría | Completitud, calendarios aprobados, revisiones/vencimientos, bloqueos de disposición, autorización y acta de disposición, exportación de evidencias y tablero de indicadores reales. |
| 4. Capacidades adicionales | OCR/búsqueda de contenido, cargas masivas/migración, firma externa e IA opcional, cada una con su integración, autorización y criterios de validación. No son requisitos para comenzar el núcleo. |

Se mantiene el alcance integral solicitado; las entregas lo dividen para validar resultados operativos sin construir todo de una vez. La primera entrega no se presentará como el sistema integral terminado.

## Integración con los sistemas actuales

Documentos podrá vincular un documento o expediente con una entidad de otro sistema mediante su sistema, tipo e ID de origen. Antes de vincular, consultar o seguir el enlace se verificará la empresa y el permiso sobre ambos recursos. Se evitarán copias de clientes, proveedores, productos y comprobantes como catálogos paralelos.

Primeros casos de uso de integración:

- Compras: documentación de proveedores, contratos y expedientes de recepción/compra.
- Ventas/Comercial: contratos, documentación de clientes y evidencias relacionadas con operaciones.
- Inventario: fichas técnicas, instructivos y certificados de productos o procesos.
- Contabilidad/Impuestos: documentación respaldatoria y paquetes de evidencia.
- Caja: comprobantes y evidencias relacionadas con movimientos, sin alterar importes ni estados financieros.

La disponibilidad de un enlace no concede acceso al documento ni al comprobante. Los vínculos se implementarán progresivamente con un contrato por origen, evitando modificar de golpe los siete sistemas.

Los eventos documentales nuevos se registrarán de forma aditiva en la infraestructura durable: carga aceptada, análisis finalizado, revisión solicitada, aprobación, publicación y revisión vencida según los estados que defina el diseño. El archivo original no viajará dentro del payload y ninguna notificación podrá eludir los permisos del destinatario.

## Historias de usuario principales

1. Como usuario autorizado, cargo y clasifico un documento de mi empresa y consulto su estado de validación.
2. Como autor, preparo una versión nueva sin alterar la versión publicada.
3. Como revisor/aprobador asignado, evalúo una versión concreta y dejo decisión y observaciones trazables.
4. Como destinatario, encuentro la versión vigente y, cuando corresponde, dejo constancia de lectura.
5. Como responsable documental, identifico revisiones pendientes, expedientes incompletos y plazos de conservación.
6. Como administrador autorizado, configuro reglas en Configuración y asigno facultades en Sistemas.
7. Como auditor autorizado, consulto y exporto evidencia sin alterar documentos o decisiones.

## Criterios de aceptación

- Se conservan los sistemas y permisos anteriores al incorporar el nuevo módulo.
- Su alta no concede accesos automáticamente. Las asignaciones existentes de Core gobiernan su aparición en la navegación.
- Dos empresas no comparten archivos, metadatos, resultados ni historial por errores de enlaces o identificadores.
- Un usuario sin autorización no obtiene información ni archivos mediante URL directa, API, búsqueda o vista previa.
- Consultar no permite modificar; modificar no concede automáticamente facultad de aprobación o publicación.
- Cada versión conserva sus archivos y huellas; cada decisión referencia una versión concreta.
- Subir una versión nueva no sustituye la vigente hasta completar el circuito autorizado.
- Un archivo en cuarentena no puede circular como documento aprobado. Un fallo del escáner no se trata como archivo limpio.
- Un registro no se sobrescribe y sus rectificaciones mantienen la relación con el original.
- Un bloqueo de conservación impide la disposición y queda evidencia de los intentos/decisiones relevantes.
- Los avisos fallidos se pueden reintentar sin duplicar decisiones, publicaciones o constancias.
- Se verifica restauración de archivos, metadatos, relaciones y evidencias en el piloto; un respaldo existente no basta como prueba de recuperación.

## Siguiente fase

Validar este alcance. Después, presentar el diseño técnico del primer incremento: estructura del módulo, esquema de datos, matriz de facultades, estados documentales/antivirus, rutas web/API, contratos de vinculación y eventos, tratamiento de archivos y pruebas. Su implementación se iniciará después de aprobar ese diseño, conforme al protocolo de fases acordado.
