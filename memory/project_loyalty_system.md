---
name: project-loyalty-system
description: Laravel 13 + Filament v4 + spatie/laravel-mobile-pass; tarjetas de lealtad con sellos visuales para cafeterías. Ahora incluye portal web para negocios (Blade puro) con guard separado.
metadata:
  type: project
---

Sistema de tarjetas de lealtad con sellos para negocios (cafeterías etc.).

**Stack:** Laravel 13, Filament v4, spatie/laravel-mobile-pass, intervention/image.

**Modelos principales:**
- `Business` — implementa `Authenticatable` para guard `business`. Tiene `login_email` + `password` para portal de negocios.
- `LoyaltyProgram` — pertenece a Business. Tiene imágenes de sellos, milestones, colores de wallet.
- `LoyaltyCard` — campos cambiados de `holder_name`/`holder_email` a `first_name`, `last_name`, `birth_date`. Tiene método `fullName()`.
- `LoyaltyMilestone`, `StampTransaction`, `RewardRedemption`, `MilestoneRedemption`.

**Guards de auth:**
- `web` — para el admin de Filament (User model).
- `business` — para el portal de negocios (Business model, `login_email` como credencial).

**Panel Filament:** `/admin` — exclusivo para el administrador.

**Portal de negocios (Blade):** `/business/*`
- `/business/login` — login con `login_email` + `password`
- `/business/dashboard` — resumen: tarjetas, clientes recientes
- `/business/loyalty-program` — crear/editar su LoyaltyProgram (1 por negocio en el portal)
- `/business/customers` — ver clientes con búsqueda y progreso
- `/business/qr` — QR con URL pública de registro usando qrcode.js (CDN)
- Middleware: `BusinessAuthenticated` protege las rutas autenticadas

**Formulario público:** `GET/POST /loyalty/{slug}/{program}/register`
- Cliente llena nombre, apellido, fecha de nacimiento.
- `LoyaltyRegistrationController` detecta iOS/Android via user-agent y redirige al wallet correcto.
- Usa `LoyaltyService::createCard(firstName, lastName, birthDate)`.

**Servicios wallet:**
- `AppleWalletService` y `GoogleWalletService` ahora usan `$card->fullName()` en vez de `$card->holder_name`.
- `LoyaltyService::createCard` ahora acepta `firstName`, `lastName`, `birthDate`, `holderIdentifier`.

**Why:** El negocio necesita su propio acceso sin ver datos de otros negocios. Se usó guard separado para aislar la sesión del admin de Filament.

**How to apply:** Al crear un negocio en Filament, asignar `login_email` y `password` en la sección "Acceso al Portal de Negocios". La contraseña se hashea automáticamente con `dehydrateStateUsing`.
