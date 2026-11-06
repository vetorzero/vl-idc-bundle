<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Jwt;

/**
 * O JWT não passou na verificação: formato, assinatura, validade ou claims.
 */
class InvalidTokenException extends \RuntimeException
{
}
