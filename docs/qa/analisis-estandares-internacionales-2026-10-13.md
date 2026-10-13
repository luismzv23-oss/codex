# Análisis preliminar de estándares internacionales

Fecha: 2026-10-13. Fase: análisis y requerimientos; pendiente de aprobación para diseño técnico.

## Conclusión

El sistema tiene controles de seguridad implementados, pero esta revisión no acredita cumplimiento integral ni certificación internacional. Hay dos brechas de autenticación observables en el código que deben priorizarse: exposición del enlace de recuperación al solicitante y ausencia del segundo factor en el login web, aunque la API sí lo exige cuando está habilitado.

La evidencia corresponde a una revisión estática del repositorio. No se realizaron operaciones reales, pruebas de intrusión, cambios de configuración, migraciones ni consultas a secretos de despliegue. Las prioridades son estimaciones iniciales, no puntuaciones CVSS. El alcance no constituye una auditoría exhaustiva de todos los controles.

## Objetivo y alcance propuestos

Establecer requisitos verificables para proteger cuentas y datos, mantener coherencia entre web y API y facilitar el uso accesible del sistema sin perder funcionalidades existentes.

Se propone evaluar autenticación, permisos por rol y empresa, sesiones, API, documentos privados, auditoría y configuración de despliegue. La validación posterior incluirá recorridos representativos de ventas, cobranzas, compras e inventario, además de navegación y formularios compartidos.

Referencias propuestas, sujetas a aprobación:

- **OWASP ASVS 5.0.0:** utilizar sus requisitos para evaluar seguridad técnica, con nivel 2 como objetivo propuesto y aplicabilidad documentada por requisito. Es una base de verificación; esta revisión no equivale a una evaluación completa. [Fuente oficial](https://owasp.org/projects/asvs).
- **WCAG 2.2, nivel AA:** evaluar accesibilidad de las páginas y procesos completos incluidos en el alcance. Las pruebas automáticas deben complementarse con revisión manual. [Recomendación W3C](https://www.w3.org/TR/WCAG22/).
- **ISO/IEC 27001:2022:** preparar un análisis de brechas del sistema de gestión de seguridad de la organización. La norma no se acredita únicamente con cambios en el código. [Descripción oficial ISO](https://www.iso.org/standard/27001).

La integración con ARCA y las pruebas contables no demuestran por sí mismas cumplimiento con estas referencias. La evaluación de obligaciones legales, normas contables y una eventual certificación externa requiere definir alcance y responsables específicos; no está incluida en esta fase.

## Matriz inicial de evidencia y brechas

Los identificadores siguientes son internos; no son números de controles ASVS o cláusulas ISO. “Sin evidencia” significa que no se acreditó en esta revisión del repositorio, no que la organización carezca necesariamente del control.

| ID / prioridad | Área y estado | Evidencia del repositorio | Brecha o verificación necesaria | Criterio de aceptación propuesto |
| --- | --- | --- | --- | --- |
| SEC-01 / P0 | Recuperación de cuentas: brecha observada | `app/Controllers/AuthController.php::sendResetLink`, líneas 45–59, construye el enlace y lo devuelve en un mensaje al navegador; no condiciona el comportamiento al entorno | Si este flujo se publica tal como está, el solicitante puede recibir la capacidad de restablecer una cuenta sin controlar su correo. No se explotó contra cuentas reales | El solicitante nunca recibe el token o enlace utilizable en la respuesta; entrega por canal de recuperación verificado, caducidad y uso único comprobados |
| SEC-02 / P0 | Segundo factor: brecha observada | `AuthController::attemptLogin` y `AuthService::attempt` crean la sesión tras verificar contraseña; `Api/V1/AuthController.php`, desde línea 40, exige TOTP si está activado | La protección configurada en la API no se aplica en el flujo web revisado | Con MFA habilitado no hay sesión autenticada ni acceso protegido antes de completar el segundo factor, tanto en web como en API; recuperación controlada sin bloquear administradores legítimos |
| SEC-03 / P1 | Recuperación: protección parcial | `sendResetLink` distingue correo existente/inexistente; `Config/Filters.php` aplica throttle a `login` y `api/*` | Enumeración de cuentas y ausencia de cobertura de recuperación web en ese filtro | Respuesta pública genérica; límites de solicitudes y reintentos comprobados con cuentas ficticias |
| SEC-04 / P1 | Clave JWT: fallback inseguro observado | `app/Libraries/JwtService.php::__construct` admite una clave constante por defecto | No se verificó si el despliegue utiliza ese valor; no debe asumirse que la clave real esté comprometida | Despliegue productivo rechazado cuando falta una clave adecuada; política de custodia, rotación e invalidación documentada |
| SEC-05 / P1 | Validación JWT: parcial | `JwtService::validateToken` y `decode` verifican firma y expiración, pero no se observó validación de emisor, audiencia y vigencia inicial | Definir contrato de tokens y rechazar contextos no admitidos. No se afirma una vulnerabilidad de aceptación de tokens sin firma | Casos negativos para firma, algoritmo admitido, emisor, audiencia y tiempos según contrato aprobado |
| SEC-06 / P1 | Renovación de tokens: hipótesis a probar | `JwtService::refreshAccessToken` lee, revoca y emite sin consumo atómico visible en el método | Una carrera podría permitir más de una renovación; no se reprodujo | Dos solicitudes simultáneas con el mismo refresh token producen como máximo una renovación válida, en MySQL aislado |
| SEC-07 / P1 | Revocación de acceso web: parcial | `AuthService::storeUser` conserva permisos en sesión; `user` y `check` consultan esa sesión. La API resuelve usuario activo y permisos actuales | Verificar efecto de desactivar usuario o cambiar permisos sobre sesiones existentes | La revocación y los cambios de privilegios surten efecto dentro del plazo aprobado y comprobable; ninguna sesión conserva privilegios más allá de ese plazo |
| SEC-08 / P1 | Aislamiento y autorización: pendiente integral | Filtros `AuthFilter`, `ApiAuthFilter`, `PermissionFilter` y `ApiPermissionFilter` | Existencia de filtros no demuestra cobertura de cada objeto, descarga y operación | Matriz rol/empresa/recurso con pruebas de acceso directo, modificación de identificadores y descargas; denegación sin cambios en datos ajenos |
| SEC-09 / P1 | Transporte y cookies: dependiente del despliegue | `Config/Cookie.php` tiene Secure desactivado por defecto; `Config/App.php` no fuerza HTTPS por defecto; existe filtro de cabeceras | No se inspeccionaron overrides, proxy ni cabeceras reales de producción | HTTPS y cookies de sesión adecuadas comprobados en el entorno objetivo, incluyendo terminación TLS en proxy cuando corresponda |
| SEC-10 / P2 | CSRF y navegador: implementación parcial | CSRF global; `SecurityHeadersFilter` agrega cabeceras y CSP, con permisos para scripts y estilos inline | Revisar excepciones, solicitudes AJAX y compatibilidad antes de endurecer CSP | Operaciones sensibles rechazan solicitudes sin protección válida; política de contenido probada sin romper popups, gráficos o formularios |
| DOC-01 / P1 | Archivo privado: controles presentes | `PurchaseDocumentVault` usa AES-256-GCM, claves por documento, envoltura de claves y contexto empresa/documento; `PurchaseDocumentService` valida tipo y tamaño | Faltan evidencias de rotación, permisos efectivos del sistema operativo y restauración conjunta de documentos y claves | Recuperación comprobada desde respaldo aislado; rechazo de archivos alterados y acceso de otra empresa; custodia y ciclo de vida de claves documentados |
| AUD-01 / P1 | Auditoría: parcial | `AuditService::log` registra actor, empresa y payloads; captura errores de persistencia | No se observó redacción central de datos sensibles ni evidencia integral de integridad y retención. Esto no demuestra que se hayan registrado secretos | Catálogo de eventos, exclusión de secretos, acceso restringido, retención definida y alertas por fallos; trazabilidad verificable de acciones críticas |
| DEP-01 / P1 | Dependencias: control a revisar | `composer.json` configura `audit.block-insecure` como false | No se ejecutó auditoría de paquetes; esta opción no prueba por sí sola la existencia de vulnerabilidades instaladas | Inventario de versiones y revisión de avisos; criterio de bloqueo y excepciones documentadas antes de publicar |
| UX-01 / P2 | Accesibilidad: pendiente integral | Hay etiquetas accesibles en controles de `Views/layouts/app.php`; no se encontró evaluación completa WCAG | ARIA y diseño adaptable por sí solos no acreditan conformidad | Teclado, foco, diálogos, nombres de controles, contraste, errores y mensajes de actualización verificados en los procesos incluidos |
| UX-02 / P2 | Reflujo: pendiente de validación | Layout y estilos compartidos incluyen anchos y zonas con overflow que requieren comprobarse en contexto | No se confirmó fallo visual en esta fase | Contenido y acciones utilizables a 320 px CSS y con ampliación, sin pérdida de información; listados adaptados a la restricción del usuario de no tener scroll horizontal |
| GOV-01 / P1 | Gestión de seguridad: sin evidencia suficiente | Documentos QA funcionales, incluido `docs/qa/certificacion-operativa-pyme.md` | No acreditan un SGSI; las políticas o evidencias pueden estar fuera del repositorio | Responsables, activos, riesgos, tratamiento, incidentes y continuidad documentados; revisión con la organización antes de declarar preparación para certificación |

P0: resolver primero por riesgo de acceso a cuentas. P1: controles necesarios para una evaluación técnica y operativa fiable. P2: completar evaluación y mejoras planificadas; esta prioridad no reduce la importancia de la accesibilidad.

## Historias de usuario y criterios verificables

1. **Como titular de una cuenta, quiero recuperar el acceso sin que terceros obtengan el enlace.**
   - Ninguna respuesta pública incluye credenciales de recuperación.
   - Un enlace usado o vencido se rechaza; un intento inválido no altera la cuenta.
   - El flujo no permite distinguir públicamente si un correo existe y dispone de límites de intentos.

2. **Como usuario con MFA, quiero la misma protección en web y API.**
   - Una contraseña correcta no completa la autenticación cuando falta el segundo factor requerido.
   - Códigos incorrectos y códigos de respaldo reutilizados se rechazan.
   - La recuperación y habilitación de MFA preservan un procedimiento aprobado para administradores.

3. **Como administrador, quiero que los permisos y el aislamiento de empresas se respeten en todas las operaciones.**
   - Usuario desactivado o permiso retirado deja de acceder dentro del plazo definido en el diseño.
   - Las pruebas negativas cubren consultas, cambios, exportaciones y documentos de otra empresa.
   - La renovación concurrente no multiplica tokens válidos a partir del mismo token consumido.

4. **Como responsable del negocio, quiero conservar documentos y evidencias recuperables.**
   - Un respaldo aislado permite recuperar documentos y claves sin usar datos reales.
   - La alteración de un documento se detecta y el acceso no autorizado se rechaza.
   - Los eventos críticos se pueden rastrear sin exponer contraseñas, tokens ni claves.

5. **Como usuario, quiero operar sin barreras visuales o de interacción.**
   - Los procesos seleccionados funcionan con teclado, foco visible y diálogos que gestionan correctamente el foco.
   - Los controles de iconos tienen nombre accesible; validaciones y resultados dinámicos se comunican adecuadamente.
   - La adaptación de filtros y listados conserva información y acciones sin scroll horizontal.

## Plan de validación posterior, pendiente de diseño

- Elaborar el inventario completo de requisitos aplicables; registrar exclusiones con justificación. Esta matriz inicial no sustituye ese inventario.
- Usar usuarios, correos, archivos y empresas ficticios, con base MySQL aislada. Mantener la restricción de no efectuar operaciones reales.
- Combinar pruebas de unidad e integración, recorridos web/API y concurrencia para autenticación y autorización.
- Evaluar accesibilidad manual y automáticamente sobre procesos completos; conservar evidencias reproducibles y limitaciones de cobertura.
- Revisar configuración productiva de forma controlada, sin divulgar secretos ni asumir que los valores por defecto coinciden con los efectivos.
- Registrar para cada requisito: versión de referencia, estado, evidencia, responsable, fecha y riesgo residual. No asignar porcentaje antes de definir el universo de requisitos.
- Afirmar cumplimiento únicamente dentro del alcance y versión realmente verificados. Una certificación ISO requiere su proceso organizacional y evaluación correspondiente.

## Decisión solicitada

Aprobar el alcance preliminar y las prioridades: recuperación y MFA primero; después sesiones/tokens, autorización, documentos, despliegue y auditoría; completar accesibilidad y requisitos organizacionales en el alcance acordado.

Con esa aprobación se preparará el diseño técnico, incluyendo dependencias, compatibilidad, estrategia de despliegue y pruebas. No se han diseñado contratos definitivos ni implementado correcciones en esta fase, conforme al punto de control solicitado en las instrucciones AGENTS.md del usuario.
