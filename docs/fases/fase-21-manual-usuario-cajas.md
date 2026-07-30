# Manual de Usuario: Sistema de Cajas y Tesorería

¡Bienvenido al **Manual del Sistema de Cajas**! Esta guía te explicará de forma sencilla cómo usar el sistema en tu día a día, desde que abres tu caja por la mañana hasta que realizas el arqueo de cierre y rindes tus fondos.

---

## 1. Inicio del Día: Apertura de Caja

Antes de poder registrar cualquier venta en el Punto de Venta (POS), debes iniciar una sesión de caja. Esto le indica al sistema con cuánto dinero físico estás comenzando tu jornada.

### ¿Cómo abrir una caja?
1. Ve al módulo de **Caja y Tesorería**.
2. Presiona el botón **Abrir Caja** (o selecciona tu caja correspondiente si tienes varias opciones).
3. Introduce el **Monto de Apertura** (dinero en efectivo inicial para dar vuelto / cambio).
4. *(Opcional)* Agrega observaciones en el campo de **Notas**.
5. Presiona **Confirmar Apertura**.

> [!IMPORTANT]
> **Regla para Vendedores:**
> Si eres vendedor, el sistema solo te permitirá tener **una caja abierta a la vez**. Esto garantiza que tu dinero esté asociado únicamente a tu sesión de trabajo activa.

---

## 2. Operaciones Diarias

Una vez abierta la caja, el sistema registrará los movimientos de forma automática o manual según corresponda.

### A. Ventas desde el Punto de Venta (POS)
Cada vez que confirmes y cobres una venta desde la pantalla de facturación, el sistema registrará automáticamente el ingreso de dinero bajo el concepto `Ingreso por Venta` (`sale_income`). 
* El sistema sumará el monto exacto según el método de pago que elijas (Efectivo, Tarjeta, QR, etc.) para mantener actualizado tu **Saldo Esperado**.

### B. Movimientos Manuales (Entradas y Salidas)
Si necesitas realizar un movimiento que no proviene de una venta directa (por ejemplo, pagarle a un proveedor menor en efectivo o retirar efectivo para cambiar cambio), debes usar la función de **Movimientos Manuales**:
1. En el panel de Caja, selecciona **Registrar Movimiento**.
2. Elige el **Tipo de Movimiento**:
   * **Ingreso Manual:** Entrada de dinero extra.
   * **Egreso Manual / Retiro:** Salida de dinero para compras rápidas u otros motivos.
3. Coloca el **Monto** y el **Medio de Pago** (generalmente efectivo).
4. Escribe el **Motivo / Nota** (esto es crucial para la auditoría contable posterior).
5. Confirma el registro.

---

## 3. Administración de Cheques de Terceros

Si un cliente te paga con un cheque físico, este ingresa a tu "cartera" y puedes ver su historial. Dependiendo de lo que decida hacer la administración, puedes registrar tres acciones:

1. **Endoso a Proveedores:** Si usas el cheque de un cliente para pagarle a un proveedor. Al registrarlo en el sistema, saldrá de tu cartera y restará ese monto del saldo de la sesión de caja activa.
2. **Depósito Bancario:** Si llevas el cheque físico para depositarlo en la cuenta bancaria de la empresa. Cambia su estado a "Depositado" y reduce el dinero físico de la caja.
3. **Rechazo de Cheque:** Si el banco rechaza un cheque (debido a falta de fondos, firmas no válidas, etc.), puedes marcarlo como "Rechazado" en la sesión activa para realizar los ajustes correspondientes en los saldos.

---

## 4. Fin del Turno: Arqueo y Cierre de Caja

Al finalizar tu horario o jornada de trabajo, debes contar físicamente todo el dinero que tienes en tu caja registradora y declararlo en el sistema para cerrar la sesión.

### Paso a paso para cerrar caja:
1. En el módulo de Caja, busca tu sesión activa y haz clic en **Cerrar Caja**.
2. El sistema te mostrará tu **Saldo Inicial** y el **Saldo Esperado** (lo que el sistema calcula que deberías tener según las ventas y movimientos declarados).
3. Cuenta detenidamente el dinero físico real y colócalo en el campo **Monto de Cierre Real** (Arqueo).

### ¿Qué pasa si el dinero no coincide? (Discrepancias)
El sistema compara el dinero que declaras vs. lo que calcula que debería haber.
* **Si la caja cuadra perfectamente (Diferencia de $0):** El sistema se cierra al instante sin demoras.
* **Si falta o sobra dinero (Diferencia > $0.01):** 
  * Si eres **Administrador / Supervisor**, podrás proceder con el cierre y el sistema registrará la diferencia de forma automática.
  * Si eres **Vendedor**, el sistema bloqueará el cierre y **requerirá que un Supervisor ingrese su usuario y contraseña** directamente en tu pantalla para autorizar y firmar digitalmente el cierre con diferencias.

---

## 5. Rendición y Transferencia de Fondos al Cierre

Para evitar dejar demasiado efectivo en una caja POS durante la noche, puedes realizar una **Rendición de Fondos** al momento de cerrar:
* En el formulario de cierre, activa la opción **Transferir fondos al cerrar**.
* Selecciona la caja de destino (por ejemplo, **Caja Principal**).
* Ingresa el **Monto a Transferir** (generalmente la recaudación total del día, dejando solo el cambio inicial en la caja).
* Al confirmar el cierre, el dinero se descontará de tu sesión y se sumará automáticamente a la caja principal de forma segura.

---

## 6. Reporte PDF de Cierre

Una vez cerrada la caja, puedes descargar el **Reporte de Cierre de Caja** en PDF para imprimirlo o enviarlo a administración. Este reporte contiene:
1. **Datos Generales:** Fecha y hora exactas de apertura y cierre, y los operadores responsables.
2. **Resumen de Saldos:** Cuánto dinero inicial había, cuánto debía haber teóricamente, cuánto declaraste físicamente y cuál fue la diferencia (Sobrante o Faltante).
3. **Detalle por Medio de Pago:** Cuánto dinero ingresó en Efectivo, cuánto por Tarjeta, Transferencia, etc.
4. **Listado de Ventas:** Todas las facturas y comprobantes emitidos en el turno.
5. **Observaciones:** Notas escritas durante el día o al momento del cierre.
6. **Espacios de Firma:** Espacio impreso para que firme el cajero y el tesorero/auditor responsable.
