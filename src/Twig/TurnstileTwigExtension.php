<?php

namespace Level6\TurnstileBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

/**
 * Renders the canonical Turnstile frontend snippet:
 *
 *   {{ turnstile_widget('login') }}
 *
 * emits (once per request) the api.js script tag plus a
 * <div class="cf-turnstile" data-sitekey="..." data-action="..."> container
 * inside the surrounding <form>. Turnstile auto-injects a hidden
 * cf-turnstile-response input on form submission.
 */
class TurnstileTwigExtension extends AbstractExtension implements GlobalsInterface
{
    private const API_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

    private $sitekey;
    private $apiScriptRendered = false;

    public function __construct(string $sitekey)
    {
        $this->sitekey = $sitekey;
    }

    public function getGlobals(): array
    {
        return [
            'turnstile_sitekey' => $this->sitekey,
        ];
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('turnstile_widget', [$this, 'renderWidget'], ['is_safe' => ['html']]),
        ];
    }

    public function renderWidget(string $action): string
    {
        if ('' === trim($this->sitekey)) {
            // Unprovisioned environment (TURNSTILE_SITEKEY missing): render
            // nothing instead of a widget with an empty sitekey, which would
            // make api.js throw "Invalid input for parameter \"sitekey\"". The
            // server-side gate is likewise disabled when the secret is empty,
            // so such an environment behaves as if Turnstile were absent.
            return '';
        }

        $html = '';

        if (!$this->apiScriptRendered) {
            $html .= '<script src="'.self::API_URL.'" async defer></script>'."\n";
            $this->apiScriptRendered = true;
        }

        $html .= sprintf(
            '<div class="cf-turnstile" data-sitekey="%s" data-action="%s"></div>',
            htmlspecialchars($this->sitekey, ENT_QUOTES),
            htmlspecialchars($action, ENT_QUOTES)
        );

        return $html;
    }
}