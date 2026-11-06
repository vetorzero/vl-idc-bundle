<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Api\Exception;

/**
 * 401 ou 403: erro de configuração do client na central (secret, acesso à API, IPs ou papéis).
 */
class AccessDeniedException extends ApiException
{
}
