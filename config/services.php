<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use VetorZero\VlIdcBundle\Discovery\Discovery;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->bind('string $issuer', param('vl_idc.issuer'));

    $services->set(Discovery::class);
};
