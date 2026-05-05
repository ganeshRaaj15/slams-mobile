# SLAMS Mobile

This project now contains two mobile-facing layers:

- the existing mobile/PWA experience inside the CodeIgniter app
- the new real native client in [native-app/README.md](native-app/README.md)

It keeps the same CodeIgniter controllers, models, routes, authentication, and database connection as the main website. The `.env` file still points to `slams_db`, so bookings, labs, assets, users, approvals, notifications, and reports use the same data.

## Run Locally

```powershell
cd c:\laragon\www\slams-mobile
php spark serve --port 8081
```

Open:

```text
http://localhost:8081
```

## Web/PWA Layer

- `public/manifest.webmanifest` makes the app installable.
- `public/sw.js` now caches the app shell, public pages, and an offline fallback page.
- `public/css/mobile-app.css` adds bottom navigation, floating mobile quick actions, app-status banners, and touch-friendly controls.
- `public/js/mobile-app.js` registers the service worker, handles install/update prompts, shows online/offline state, and manages device push subscriptions.
- `app/Views/components/mobile_bottom_nav.php` adds role-aware bottom navigation.
- `app/Views/components/mobile_quick_actions.php` adds a role-aware quick-action sheet with live badges for alerts and queue counts.
- `php spark slams:generate-web-push-keys` generates VAPID keys for browser push setup.

The mobile app mirrors the shared SLAMS authentication and recovery flow.

## Native App Layer

The native frontend lives in `native-app/` and uses:

- Expo React Native
- Shield access-token authentication
- JSON endpoints under `/api/native/*`
- `expo-secure-store` for bearer-token persistence

The current native slice includes login, role-aware home, lab browsing, the full student booking composer with PDF upload, booking history, issue reporting, PIC/manager/admin approval actions, technician maintenance workflows, external requester tracking, PIC/manager/admin external-request review, native report viewing with PDF/CSV export, admin settings, admin user management, native profile editing, protected PDF handoff to the device viewer, notifications, and native push registration.

For push notifications, the Expo client can also use `EXPO_PUBLIC_EAS_PROJECT_ID` when you want to pin the project ID explicitly in the native environment.

## Deployment Hygiene

Run the built-in deployment audit before packaging or publishing:

```powershell
php spark slams:audit-deployment
```

See [DEPLOYMENT_HYGIENE.md](DEPLOYMENT_HYGIENE.md) for the rollout checklist.
