# Admin Panel

## Access

The Laravel admin panel lives under `/admin` and uses the separate `admin` guard. Admin accounts are stored in the `admins` table, separate from public site users in `users`.

Seed the initial admin account with environment variables instead of committing credentials:

- `ADMIN_NAME`
- `ADMIN_EMAIL`
- `ADMIN_PASSWORD`

Run the seeders after migrations:

```sh
cd backend
php artisan migrate --seed
```

## Admin Center

Admin Center includes:

- Admin Users
- Role Manager

User Accounts is intentionally a separate top-level menu item outside Admin Center.

## Roles

Admin roles are managed through `spatie/laravel-permission` on the `admin` guard. Default roles are seeded by `Database\Seeders\AdminRoleSeeder`:

- `sys-admin`
- `admin`
- `content-manager`
- `support`
- `viewer`

The `admins.role` column is kept in sync as a lightweight display/backward-compatibility value, but Spatie roles are the source of truth for role assignments.

## Account Editing

The signed-in admin Account page has tabs for:

- Profile
- Password
- Avatar

Admin Center edit screens for Admin Users and User Accounts also use separate tabs for:

- Details
- Password
- Avatar

The selected edit tab persists in browser storage per account record.

## Avatars

`App\Traits\AvatarTrait` is shared by `Admin` and `User`.

Avatar resolution is:

1. If `use_gravatar` is enabled, use Gravatar for the account email.
2. If Gravatar is disabled and an uploaded avatar exists, use the uploaded avatar.
3. If no uploaded avatar exists, use the generated initials image.

Uploaded avatars are stored under `public/media/accounts/avatars` and saved in the database as `accounts/avatars/{file}`.
