# level6/turnstile-bundle

Cloudflare Turnstile captcha for Symfony applications: widget rendering, canonical
server-side siteverify, and configurable request gating for any form/POST surface
(login, password reset, registration, contact forms, ...).

Works with Symfony 4.4 through 7.0 and any authenticator system (guard or new),
because the gate runs on `kernel.request` (priority 16: after `RouterListener` at
32, before the security `Firewall` at 8) and never touches application security
code.

## Requirements

- PHP >= 7.1.3
- symfony/http-client, http-kernel, config, dependency-injection ^4.4|^5.4|^6.0|^7.0
- symfony/routing ^4.4|^5.4|^6.0
- twig/twig ^2.7|^3.0

## Installation

### 1. Generate a GitHub access token

The bundle lives in a private repository, so Composer needs a token with read
access to it. Create a fine-grained token here:

**https://github.com/settings/personal-access-tokens/new?contents=read&name=Composer+turnstile-bundle**

After opening the link:

1. **Resource owner** — switch from your personal account to `Level6LLC`. (If
   this organization doesn't appear in the list, you haven't been added as a
   member/collaborator — ask the organization owner to grant access.)
2. **Repository access** — select "Only select repositories" → `turnstile-bundle`
   (or "All repositories" if you'll need other private packages from this
   organization).
3. **Permissions → Repository permissions → Contents** — set to `Read-only`
   (defaults to `No access`).
4. **Expiration** — use a short lifetime, as recommended for CI/deployment
   tokens.

Each developer or CI environment should generate its **own** token from this
link — don't share or reuse the same token across people or environments.

### 2. Add the repository and require the package

```bash
composer config repositories.turnstile-bundle vcs git@github.com:Level6LLC/turnstile-bundle.git

GH_TOKEN='replace_with_your_token' COMPOSER_AUTH="{\"github-oauth\":{\"github.com\":\"$GH_TOKEN\"}}" composer require level6/turnstile-bundle:^1.0 --no-interaction --no-progress

# Fleet reuse: push this folder to its own git repo, then in each consumer:
# composer config repositories.turnstile-bundle '{"type":"vcs","url":"git@host:org/turnstile-bundle.git"}'
# composer require level6/turnstile-bundle
```

Register the bundle in `config/bundles.php`:

```php
Level6\TurnstileBundle\TurnstileBundle::class => ['all' => true],
```

## Configuration

`config/packages/turnstile.yaml`:

```yaml
turnstile:
    sitekey: '%env(TURNSTILE_SITEKEY)%'   # public key, may live in committed .env
    secret:  '%env(TURNSTILE_SECRET)%'    # private; put only in .env.local / deployment env
    hostnames: []                         # optional allowlist checked against siteverify "hostname"; empty = not checked
    surfaces:
        login:
            route: login                  # required route name
            methods: [POST]               # default
            action: login                 # Turnstile action; defaults to the surface key
            on_failure: flash_redirect    # flash_redirect | 403
            flash_type: danger            # flashbag type for flash_redirect
            message: 'Security verification failed. Please try again.'
        reset_password:
            route: app_forgot_password_request
            action: reset_password
            on_failure: flash_redirect
            flash_type: reset_password_error
```

Environment variables stay in the consuming app (never commit the secret):

```bash
# .env (committed, public values only)
###> cloudflare turnstile ###
# Public site key of the Turnstile widget, rendered into the login and
# reset-password pages via the "turnstile_sitekey" Twig global.
TURNSTILE_SITEKEY=0x4...
# Secret key of the Turnstile widget.
# NEVER commit a real value here; put it in .env.local (git-ignored) or in the
# deployment environment. An empty value disables captcha verification.
TURNSTILE_SECRET=
# Optional comma-separated allowlist of frontend hostnames checked against the
# siteverify response (e.g. harmanprorewards.com,www.harmanprorewards.com).
# Empty means the hostname is not checked.
TURNSTILE_HOSTNAMES=
###< cloudflare turnstile ###

# .env.local (git-ignored) or the deployment secret manager
###> cloudflare turnstile ###
TURNSTILE_SECRET=0x4...secret
###< cloudflare turnstile ###
```

An empty `secret` disables verification for that environment so unprovisioned
environments (local dev without the widget domain) are never locked out.

## Rendering the widget

Inside any `<form>` (the widget must be inside the form so its hidden
`cf-turnstile-response` input is submitted with it):

```twig
{{ turnstile_widget('login') }}
```

emits once per request:

```html
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<div class="cf-turnstile" data-sitekey="..." data-action="login"></div>
```

The raw sitekey is also available as the `turnstile_sitekey` Twig global.

## How the gate works

For every configured surface, on a matching route + method, `TurnstileVerifier`
performs the canonical POST to `https://challenges.cloudflare.com/turnstile/v0/siteverify`
(`secret`, `response` = the submitted `cf-turnstile-response`, `remoteip`, 10 s
timeout) and requires `success === true`, a matching `action`, and (when
`hostnames` is configured) a hostname in the allowlist. On failure the request is
short-circuited with either a redirect back to the surface route plus a session
flash message, or a plain 403. On success the bundle does nothing: the existing
handler logic runs, unchanged. Tokens are single-use; Cloudflare enforces replay
rejection at siteverify.

The browser never calls siteverify; verification is always
browser -> application backend -> siteverify.

## Notes

- Keep the `data-action` of each surface in sync with the config `action` value.
- Native full-page form posts need no explicit widget reset; for SPA/inline
  submissions render explicitly and call `window.turnstile.reset(widgetId)` yourself.

## Provisioning & troubleshooting

The bundle is intentionally fail-open on misconfiguration and never breaks pages:

- empty `sitekey` (env var missing) ⇒ `turnstile_widget()` renders nothing;
- empty `secret` ⇒ the server-side gate disables verification.

So an environment missing either variable behaves as if Turnstile were absent,
while a correctly provisioned one is fully protected. Always provision both
*together*, wherever that environment keeps its variables:

```bash
# committed .env (public value) or the deploy platform's public config
TURNSTILE_SITEKEY=0x4...
# git-ignored .env.local or the platform secret manager (never commit)
TURNSTILE_SECRET=0x4...
```

On Symfony deployments that compile env (`composer dump-env prod` producing
`.env.local.php`), re-run `dump-env` after adding the variables.

**Prod symptom of missing `TURNSTILE_SITEKEY` on bundle 1.0.0:** the browser
console shows `Uncaught TurnstileError: [Cloudflare Turnstile] Invalid input for
parameter "sitekey", got ""` on every page that renders the widget. Since
v1.0.1 the widget is simply not rendered in that case; upgrade the pinned
version or provision the variable.

**Maintainer note — versioning via tags only:** this package's composer.json MUST
NOT contain a `"version"` field. Composer skips any vcs tag whose composer.json
`version` differs from the tag name ("Skipped tag vX, tag does not match version
in composer.json"), so a stale in-file version hides releases (tags ≤ v1.0.1 are
unusable for this reason; v1.0.2 is the first proper release). Create releases
with `git tag -a vX.Y.Z && git push origin vX.Y.Z`.

Detecting where a production server keeps its env values (read-only):

```bash
ls -a <release-dir> | grep -E '^\.env'
grep -c TURNSTILE <release-dir>/.env* 2>/dev/null
[ -f <release-dir>/.env.local.php ] && echo 'compiled env present: re-run composer dump-env prod'
```