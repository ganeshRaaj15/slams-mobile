# Deployment Hygiene

Use this checklist before packaging or publishing SLAMS.

## Run the Audit

```powershell
php spark slams:audit-deployment
```

The audit flags common rollout issues such as:

- non-production environment settings
- localhost base URLs
- debug mode left enabled
- legacy public PDFs under `public/uploads/pdfs`
- generated media left in public upload directories
- runtime artifacts left in `writable/logs`, `writable/debugbar`, or `writable/session`

## Web Root

Point the web server at `public/`, not the project root.

## Secrets

Keep real secrets in `.env` only. Do not package `.env`, SQL dumps, ZIP archives, or runtime files into deployment artifacts.

## Uploaded Files

- Booking PDFs belong in `writable/uploads/pdfs`.
- Legacy requests to `public/uploads/pdfs/*.pdf` are now rewritten through `DocumentController` so authorization still applies.
- Public upload and image directories include `.htaccess` rules to block executable file access.

## Source Control

Generated media and runtime artifacts are intentionally ignored in `.gitignore`. If a deployment needs seed media or placeholders, keep only deliberate static assets under version control.
