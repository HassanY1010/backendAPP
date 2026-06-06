# Production Secret Rotation Policy

## Scope

Rotate these secrets before any production release and after any suspected exposure:

- `APP_KEY`
- Database credentials
- Redis credentials
- Supabase storage keys
- SMS provider credentials
- Pusher app secret
- Sadad credentials
- Android signing keystore and passwords

## Storage

- Store production secrets only in the deployment platform secret store or a dedicated secrets manager.
- Keep `.env` files out of Git and out of shared chat/email.
- Keep `.env.example` placeholder-only.
- Limit production secret access to deploy operators.

## Rotation Cadence

- Critical payment, database, and storage credentials: every 90 days.
- Application and integration credentials: every 180 days.
- Immediately rotate any secret that was committed, logged, shared in plaintext, or exposed in a diagnostic endpoint.

## Rotation Steps

1. Create the replacement secret in the upstream provider.
2. Add it to the production secret store without deleting the current secret.
3. Deploy using the new secret.
4. Run health checks and a smoke test for login, uploads, payments, and notifications.
5. Revoke the old secret in the upstream provider.
6. Record the rotation date, operator, and verification result.

## Release Gate

Production deployment is blocked until exposed historical secrets are rotated in their providers. Removing hardcoded values from the repository does not invalidate secrets that may already be copied or cached elsewhere.
