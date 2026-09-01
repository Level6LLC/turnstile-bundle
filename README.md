# level6/turnstile-bundle

Cloudflare Turnstile captcha for Symfony applications: widget rendering, canonical
server-side siteverify, and configurable request gating for any form/POST surface
(login, password reset, registration, contact forms, ...).

Works with Symfony 4.4 / 5.4 and any authenticator system (guard or new), because
the gate runs on `kernel.request` (priority 16: after `RouterListener` at 32, before
the security `Firewall` at 8) and never touches application security code.

## Requirements

- PHP >= 7.1.3
- symfony/http-client, http-kernel, config, dependency-injection, routing ^4.4|^5.4
- twig/twig ^2.7|^3.0

## Installation

Add the package repository and require it in the consuming project:

```bash
composer config repositories.turnstile-bundle vcs git@github.com:Level6LLC/turnstile-bundle.git
composer require level6/turnstile-bundle

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
TURNSTILE_SITEKEY=0x4...
TURNSTILE_SECRET=
TURNSTILE_HOSTNAMES=

# .env.local (git-ignored) or the deployment secret manager
TURNSTILE_SECRET=0x4...secret
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