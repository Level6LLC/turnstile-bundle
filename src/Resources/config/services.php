<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Level6\TurnstileBundle\EventSubscriber\TurnstileGuardSubscriber;
use Level6\TurnstileBundle\Service\TurnstileVerifier;
use Level6\TurnstileBundle\Twig\TurnstileTwigExtension;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(TurnstileVerifier::class)
        ->args([
            '%turnstile.secret%',
            '%turnstile.hostnames%',
        ]);

    $services->set(TurnstileGuardSubscriber::class)
        ->args([
            new Reference(TurnstileVerifier::class),
            new Reference('router'),
            '%turnstile.surfaces%',
        ])
        ->tag('kernel.event_subscriber');

    $services->set(TurnstileTwigExtension::class)
        ->args([
            '%turnstile.sitekey%',
        ])
        ->tag('twig.extension');
};
