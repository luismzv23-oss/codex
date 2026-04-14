# Codex

Proyecto base del sistema `codex` con Core inicial funcional:

- CodeIgniter 4.4.8
- PHP 8.0+
- MySQL
- Bootstrap 5

## Modulos Core incluidos

- Autenticacion
- Usuarios
- Roles y permisos RBAC
- Multiempresa
- Configuracion por empresa
- Sucursales
- Impuestos
- Monedas
- Numeracion de comprobantes

## Arranque

```powershell
cd C:\Users\luism\.gemini\antigravity\scratch\codex
php spark serve
```

## Credenciales demo

- `superadmin` / `SuperAdmin123*`
- `admin` / `Admin123*`
- `operador` / `Operador123*`

## Comandos ya ejecutados

- `php spark migrate`
- `php spark db:seed CoreSeeder`
