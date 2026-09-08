<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Level6\TurnstileBundle\EventSubscriber\TurnstileGuardSubscriber;
use Level6\TurnstileBundle\Service\TurnstileVerifier;
use Level6\TurnstileBundle\Twig\TurnstileTwigExtension;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(TurnstileVerifier::class)
        ->args([
            param('turnstile.secret'),
            param('turnstile.hostnames'),
        ]);

    $services->set(TurnstileGuardSubscriber::class)
        ->args([
            service(TurnstileVerifier::class),
            service('router'),
            param('turnstile.surfaces'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(TurnstileTwigExtension::class)
        ->args([
            param('turnstile.sitekey'),
        ])
        ->tag('twig.extension');
};
