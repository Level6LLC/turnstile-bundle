<?php

namespace Level6\TurnstileBundle\EventSubscriber;

use Level6\TurnstileBundle\Service\TurnstileVerifier;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Generic Turnstile gate for configured surfaces.
 *
 * Runs on kernel.request with priority 16: after RouterListener (32) so the
 * matched route is known, and before the security Firewall listener (8) so the
 * captcha is verified before any authenticator of any Symfony version runs.
 * The existing handler logic stays the same; this only gates failed
 * verifications ("gate, don't replace").
 *
 * The kernel.request event is type-hinted against KernelEvent — the common
 * base of GetResponseEvent (Symfony 4.0-4.2) and RequestEvent (4.3+) — so a
 * single class covers the whole supported Symfony range.
 */
class TurnstileGuardSubscriber implements EventSubscriberInterface
{
    private $turnstileVerifier;
    private $router;
    private $surfaces;

    public function __construct(TurnstileVerifier $turnstileVerifier, UrlGeneratorInterface $router, array $surfaces)
    {
        $this->turnstileVerifier = $turnstileVerifier;
        $this->router = $router;
        $this->surfaces = $surfaces;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 16]],
        ];
    }

    public function onKernelRequest(KernelEvent $event): void
    {
        if (!$this->isMainRequest($event)) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        if (null === $route) {
            return;
        }

        foreach ($this->surfaces as $name => $surface) {
            if (($surface['route'] ?? null) !== $route) {
                continue;
            }

            $methods = $surface['methods'] ?? ['POST'];
            if (!in_array($request->getMethod(), $methods, true)) {
                continue;
            }

            $action = ('' !== (string) ($surface['action'] ?? '')) ? (string) $surface['action'] : (string) $name;
            if ($this->turnstileVerifier->verify($request->request->get('cf-turnstile-response'), $action, $request->getClientIp())) {
                return; // verified: the existing handler logic runs, unchanged
            }

            $message = isset($surface['message']) && '' !== (string) $surface['message']
                ? (string) $surface['message']
                : 'Security verification failed. Please try again.';

            if ('403' === ($surface['on_failure'] ?? 'flash_redirect')) {
                $event->setResponse(new Response('Security verification failed.', 403));
            } else {
                if ($request->hasSession()) {
                    $request->getSession()->getFlashBag()->add((string) ($surface['flash_type'] ?? 'danger'), $message);
                }

                $event->setResponse(new RedirectResponse($this->router->generate($route)));
            }

            $event->stopPropagation();

            return;
        }
    }

    private function isMainRequest(KernelEvent $event): bool
    {
        // isMainRequest() exists since Symfony 4.3; earlier releases only have
        // isMasterRequest().
        if (method_exists($event, 'isMainRequest')) {
            return $event->isMainRequest();
        }

        return $event->isMasterRequest();
    }
}