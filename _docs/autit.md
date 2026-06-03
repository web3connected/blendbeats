# BlendBeats Boot Audit

Date: 2026-06-01
Requested port: 3150
Workspace: `C:\Users\Techmaster\OneDrive\Desktop\BlendBeats`

## What Kind of App This Is

BlendBeats appears to be a Vite + React + TypeScript single-page web app generated from a `v8-app-template` style starter.

Primary signals:

- `index.html` loads `/src/main.tsx`.
- `src/main.tsx` mounts React into `#app`.
- Routing is handled with `react-router-dom`.
- Styling uses Tailwind CSS via `src/styles/globals.css`.
- The app includes a small API route structure under `src/server/api/health/GET.ts`.
- The homepage content is a DJ battle / music community landing experience called "The Blend Battlegrounds."

## Boot Command Tried

```powershell
npm.cmd run dev -- --host 0.0.0.0 --port 3150 --strictPort
```

I used `npm.cmd` instead of `npm` because PowerShell blocks the local `npm.ps1` shim on this machine:

```text
npm : File C:\nvm4w\nodejs\npm.ps1 cannot be loaded because running scripts is disabled on this system.
```

## Confirmed Boot Result

The app did not boot on port `3150`.

The confirmed blocker is that npm cannot find `package.json`:

```text
npm ERR! enoent Could not read package.json: Error: ENOENT: no such file or directory, open 'C:\Users\Techmaster\OneDrive\Desktop\BlendBeats\package.json'
```

There is a file named `packages.json`, and it contains the expected npm metadata, scripts, dependencies, and devDependencies. npm requires the file to be named `package.json`.

## Current Dependency State

`node_modules` is not present, so dependencies are not installed in this workspace.

Even after restoring/renaming `package.json`, the next required step would be dependency installation before Vite can start.

## Likely Next Blockers After Fixing package.json

These were found by inspecting the files and imports. They were not reached during boot because npm stopped at the missing manifest.

1. `vite.config.ts` imports local plugin files/directories that are missing from the workspace:

```text
./source-mapper/src/index
./dev-tools/src/vite-plugin
./fullstory-plugin
./dev-tools/src/vite-error-interceptor
./dev-tools/src/vite-media-versions-plugin
```

Related locations:

- `vite.config.ts:4`
- `vite.config.ts:5`
- `vite.config.ts:6`
- `vite.config.ts:7`
- `vite.config.ts:8`

2. `src/App.tsx` imports a missing dev-tools error boundary:

```text
../dev-tools/src/AiroErrorBoundary
```

Related location:

- `src/App.tsx:9`

3. `src/routes.tsx` references a missing dev-tools 404 page in development:

```text
../dev-tools/src/PageNotFound
```

Related location:

- `src/routes.tsx:11`

4. `src/main.tsx` has relative imports that appear incorrect for the current file structure:

```text
import App from '../App';
import '../styles/globals.css';
```

From `src/main.tsx`, these resolve outside `src`. The existing files are:

```text
src/App.tsx
src/styles/globals.css
```

These likely should be:

```text
import App from './App';
import './styles/globals.css';
```

Related locations:

- `src/main.tsx:5`
- `src/main.tsx:6`

5. `src/App.tsx` tries to lazy import `@/components/CookieBanner`, but the matching file appears to be named `src/components/CookieConsent.tsx` and exports `CookieBanner`.

Related locations:

- `src/App.tsx:15`
- `src/components/CookieConsent.tsx:115`

## Recommended Fix Order

1. Rename `packages.json` to `package.json`.
2. Install dependencies with `npm.cmd install`.
3. Restore the missing generated helper directories/files or remove their imports:

```text
source-mapper/
dev-tools/
fullstory-plugin
```

4. Fix `src/main.tsx` imports to point at files under `src`.
5. Fix the cookie banner lazy import to reference `@/components/CookieConsent` or rename the component file to `CookieBanner.tsx`.
6. Retry boot:

```powershell
npm.cmd run dev -- --host 0.0.0.0 --port 3150 --strictPort
```

## Audit Summary

The app is a React/Vite/TypeScript web app, but it currently cannot boot because the npm manifest is misnamed as `packages.json`. The workspace also appears incomplete: several generated dev helper modules referenced by Vite and React are missing. Port `3150` was requested and used in the attempted boot command, but the dev server never started.

## Retry Results - 2026-06-02

The app was retried after `package.json` was restored.

### Commands Run

```powershell
npm.cmd install
npm.cmd run dev -- --host 0.0.0.0 --port 3150 --strictPort
npm.cmd run type-check
npm.cmd run build
```

### Fixes Applied

- Installed npm dependencies, which created `node_modules` and `package-lock.json`.
- Removed missing generated dev-plugin imports from `vite.config.ts`.
- Changed the Vite React plugin setup back to `react()` because the missing `source-mapper` Babel plugin was not available.
- Removed missing `dev-tools` React imports from `src/App.tsx` and `src/routes.tsx`.
- Fixed `src/main.tsx` imports from `../App` and `../styles/globals.css` to `./App` and `./styles/globals.css`.
- Fixed the cookie banner lazy import to load `@/components/CookieConsent`.
- Added a real Tailwind config in `tailwind.config.ts` with the app's CSS variable tokens and content paths.
- Fixed the SSR sitemap import from `../lib/seo-routes` to `../lib/seo-router`.
- Added the missing `/api/health` handler in `src/server/api/health/GET.ts`.
- Included `vite-env.d.ts` in `tsconfig.json` so `import.meta.env` types are available.
- Updated SSR helmet usage in `src/entry-server.tsx` to use this package's `onServerState` prop.

### Current Boot Status

The dev server is now running successfully on port `3150`.

Verified endpoints:

```text
GET http://localhost:3150/           -> 200 OK
GET http://localhost:3150/api/health -> 200 OK, {"ok":true}
```

Vite log:

```text
VITE v6.4.3 ready in 412 ms
Local:   http://localhost:3150/
```

### Verification Status

`npm.cmd run type-check` passes.

`npm.cmd run build` passes for both client and SSR bundles.

Remaining non-blocking warning:

```text
<script src="/analytics.js"> in "/index.html" can't be bundled without type="module" attribute
```

This warning does not stop the build or dev server.

### Security Note

`npm.cmd install` reported two critical npm audit vulnerabilities. I did not run `npm audit fix --force` because npm says that may apply breaking changes. That should be handled as a separate dependency review.
