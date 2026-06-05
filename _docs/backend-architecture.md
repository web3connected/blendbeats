# Backend Architecture

## Direction

BlendBeats uses Laravel as the backend system of record for database-managed identity, API endpoints, and admin workflows.

The React/Vite frontend should not invent persistent auth state on its own. It should ask the Laravel API who is logged in and render login/register buttons or account controls from that response.

## Identity Tables

- `users`: public site accounts, fans, DJs, voters, and competitors.
- `admins`: admin panel accounts, managed separately from public users.

Laravel auth is configured with separate guards and providers:

- `web` guard -> `users` provider -> `App\Models\User`
- `admin` guard -> `admins` provider -> `App\Models\Admin`

## Initial API Surface

- `GET /api/health`
- `POST /api/auth/register`
- `POST /api/auth/login`
- `GET /api/auth/me`
- `POST /api/auth/logout`
- `POST /api/admin/auth/login`
- `GET /api/admin/auth/me`
- `POST /api/admin/auth/logout`

## Admin Surface

- `GET /admin`
- `GET /admin/account`
- `GET /admin/admin-center/admin-users`
- `GET /admin/admin-center/role-manager`
- `GET /admin/admin-center/user-accounts`

The admin page is protected by the custom `admin.auth` middleware and the `admin` guard. Admin roles use `spatie/laravel-permission` with guard name `admin`.

Admin account management uses tabbed edit screens:

- Profile/details
- Password
- Avatar

Avatar behavior is shared through `App\Traits\AvatarTrait` and supports Gravatar, uploaded avatars, and generated initials fallback.

## Deployment Rule

Do not hand-edit live application code. Make changes locally, verify them, commit them, and deploy through scripts/hooks.

The existing frontend deploy script intentionally excludes `backend/` until we define the Laravel backend deployment target and web server config.
