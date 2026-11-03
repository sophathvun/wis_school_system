# Document verification domains

Application configuration:

```dotenv
APP_URL=https://registrar.western.edu.kh
VERIFICATION_URL=https://verify.western.edu.kh
SESSION_DOMAIN=null
```

`VERIFICATION_URL` controls new student ID and K3/G9/G12 certificate verification links. The previous `DOCUMENT_VERIFICATION_BASE_URL` remains a URL-generation fallback. Set `VERIFICATION_URL` to enable hostname isolation; the old setting alone does not enable that isolation. The two configured hosts must differ, and the verification URL must use HTTPS.

Only GET/HEAD requests to these public document handlers are accepted on the verification host:

- `/student-card/{stored-token}`
- `/student-card/{stored-token}/qr.svg`
- `/certificates/k3/{stored-token}`
- `/certificates/g9/{stored-token}`
- `/certificates/g12/{stored-token}`

These handlers do not start SIS sessions or run user-presence tracking. They are rate limited and return no-store/noindex headers. Main-host legacy links redirect to the corresponding verification URL without changing tokens. Other hosts cannot use these verification paths. Unknown tokens return 404. Do not regenerate existing tokens during rollout.

Student ID pages publish only names, student identification, academic year, campus, grade and validity information. Parent/home phone numbers and student photographs are excluded. Certificate pages publish only the existing issued-certificate summary. Private scans, addresses, family details and internal notes are not public verification fields.

## Other official documents

New official document types must use `DocumentVerificationUrl::route()` and an explicit read-only verification controller with an approved field allowlist. Add its exact route pattern to `IsolateVerificationHost`, apply the stateless public middleware exclusions and rate limiting, and test its hostname and token rules. The guard intentionally does not expose arbitrary uploaded documents or internal print/download routes. Staff contact cards, placement-test access links and administrative QR codes remain outside the public document-verification host.

## Deployment boundary

No server, DNS, SSL or deployment changes are performed by this feature. The operator must arrange HTTPS and routing to this application for both hosts separately. Only trust forwarded-host headers from explicitly configured trusted proxies.

The early Laravel middleware rejects administrative pages, login, internal APIs, the health endpoint, and Laravel file-serving routes on the verification host. **It cannot intercept files served directly by Nginx/Apache**, including the existing `public/storage` symlink. Complete file isolation therefore requires the infrastructure owner to review static-file routing before enabling the verification hostname publicly. Automated Laravel tests do not prove web-server isolation. This change neither moves nor deletes stored files.

Existing printed QR codes on the main SIS hostname continue through redirects as long as that hostname remains available. If older printed codes use another domain, that domain must remain available and be handled by the infrastructure owner; it is not implicitly trusted by the application.

After an approved release, the operator must refresh Laravel's configuration cache through the normal release process. This work does not deploy or run database migrations. No student records or stored verification tokens need changing.
