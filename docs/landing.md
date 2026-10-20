# Portada comercial Codex ERP

La ruta existente GET / usa Home::index y app/Views/home/index.php, con el layout independiente app/Views/layouts/landing.php.

## Recursos

- public/assets/css/landing.css: estilos exclusivos y adaptación responsive.
- public/assets/js/landing.js: componente Alpine codexLanding.
- public/assets/vendor/alpinejs/: Alpine CSP 3.17.4 local y licencia MIT.

Las interacciones usan Alpine.data, x-data, x-show, x-bind y x-on. La edición CSP funciona con la política de seguridad existente. La portada no importa Vue, jQuery ni Bootstrap. Los textos públicos de módulos se mantienen en la vista PHP; sus enlaces se generan en Home. No hay migraciones ni API nuevas.

## Comprobar en el servidor existente

1. Abrir / y comprobar navegación, contenido y acceso al login. Con sesión iniciada, comprobar que los accesos apuntan al dashboard.
2. Seleccionar los cinco módulos. Debe mostrarse únicamente el panel correspondiente.
3. En el explorador usar flechas izquierda/derecha, Home y End: cambian selección y foco. Tab permite continuar por la página.
4. Revisar 320, 390, 768 y 1440 px. En móvil, abrir el menú, seguir un enlace y cerrarlo con Escape. El menú debe cerrarse también al volver a escritorio.
5. Activar la preferencia de movimiento reducido del sistema: detener animaciones y desplazamiento suave.
6. Desactivar JavaScript: deben seguir disponibles la presentación, beneficios, descripción de Ventas, resumen de todos los módulos y enlaces de acceso.
7. Revisar consola: sin errores Alpine ni infracciones CSP. El explorador contiene descripciones y procesos ilustrativos; no consulta datos empresariales.

## Integración y despliegue

Publicar juntos Home.php, ambas vistas y los recursos nuevos, conservando la estructura de rutas. No se requiere instalación npm, compilación ni cambios de base de datos. Si hay caché de página u opcode en el destino, renovarla por el procedimiento habitual del entorno. Al modificar CSS o JS, actualizar el parámetro de versión del layout para renovar caché del navegador. No iniciar php spark serve; usar el servidor gestionado del proyecto.

## Validación realizada

En el servidor local: HTTP 200 de la portada, login y los tres recursos; render de PHP sin error; cinco tabs y cinco paneles; referencias de acceso correctas. Comprobación aislada de sintaxis JS y estado del componente: selección de los cinco módulos, rechazo de clave inválida, navegación de teclado, apertura/cierre de menú, cierre al ampliar a escritorio y movimiento reducido.

Pendiente: comprobación visual en navegador, ejecución real de directivas Alpine en DOM, validación móvil y recorrido con sesión iniciada. El entorno de herramientas no ofreció un navegador conectado ni permitió crear procesos para CLI.
