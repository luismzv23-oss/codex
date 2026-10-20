# Login de Codex ERP

La vista app/Views/auth/login.php utiliza el layout exclusivo app/Views/layouts/login.php. Comparte marca, paleta, tipografía y botones de public/assets/css/landing.css; login.css añade composición, formulario y adaptación móvil. No modifica el layout auth.php usado por recuperación y restablecimiento.

## Alpine y formulario

public/assets/js/login.js registra codexLogin antes de cargar Alpine CSP 3.17.4 local. Controla visibilidad de contraseña, estado de envío, restauración con pageshow y preferencia de movimiento. No mantiene usuario ni contraseña en estado JavaScript.

El primer submit conserva el POST nativo a /login; los siguientes se previenen mientras el formulario está enviando. Las restricciones nativas del navegador se ejecutan antes del evento submit. El botón se deshabilita visualmente mientras continúa el POST. Volver con el navegador restablece el botón y oculta la contraseña mediante pageshow.

Se conservan csrf_field(), old('login'), nombres login/password y los mensajes escapados del servidor. AuthController y sus validaciones y redirecciones no fueron modificados. El formulario y los enlaces funcionan sin JavaScript; los controles que requieren Alpine permanecen ocultos en ese caso.

## Integración

Publicar las dos vistas nuevas/modificadas, login.css y login.js junto con los recursos existentes de la landing y Alpine local. No requiere migraciones, dependencias npm ni compilación. Si se modifican los recursos, incrementar sus parámetros de versión en el layout. Usar el servidor existente; no ejecutar php spark serve.

## Validación realizada

- HTTP 200: /login, /, /forgot-password y los cuatro recursos del layout.
- Render PHP sin error; formulario POST, CSRF, autocompletado y contraseña oculta por defecto presentes.
- El layout del login no carga Vue, jQuery ni Bootstrap y no requiere unsafe-eval.
- Sintaxis JS y lógica del componente en contexto aislado: mostrar/ocultar contraseña, primer submit sin interceptar, bloqueo de duplicado, restauración pageshow, cambio de preferencia de movimiento y limpieza del listener.

## Revisión manual pendiente

No hubo navegador conectado para comprobar representación visual ni ejecutar directivas Alpine en DOM. Revisar escritorio, 768, 390 y 320 px; teclado y foco; mensajes reales de validación y credenciales inválidas; acceso correcto y redirección según rol; recuperación; autocompletado y gestores de contraseña; funcionamiento sin JavaScript; ausencia de errores CSP; volver con navegador después del envío. No se enviaron credenciales ni se realizaron intentos de autenticación durante las comprobaciones automáticas.
