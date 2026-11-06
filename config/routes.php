<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use VetorZero\VlIdcBundle\Webhook\WebhookController;

return static function (RoutingConfigurator $routes): void {
    $routes->add('vl_idc_webhook', '/')
        ->controller(WebhookController::class)
        ->methods(['POST']);
};
