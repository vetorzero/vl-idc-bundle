<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Logout;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use VetorZero\VlIdcBundle\Jwt\InvalidTokenException;

/**
 * Recebe o OIDC Back-Channel Logout da central: um POST com `logout_token=<JWT>` no corpo.
 *
 * Não tem sessão nem CSRF: quem autentica é a assinatura do token.
 *
 * @see https://openid.net/specs/openid-connect-backchannel-1_0.html#BCRequest
 */
final class BackchannelLogoutController
{
    public function __construct(
        private readonly LogoutTokenVerifier $verifier,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }


    public function __invoke(Request $request): Response
    {
        // lido cru: `get()` estoura fora do try se o campo vier como array
        $jwt = $request->request->all()['logout_token'] ?? null;

        try {
            $token = $this->verifier->verify(\is_string($jwt) ? trim($jwt) : '');
        } catch (InvalidTokenException $e) {
            $this->logger->warning('Rejected a VL-IdC logout token: {reason}', [
                'reason' => $e->getMessage(),
                'exception' => $e,
            ]);

            return self::noStore(new JsonResponse(['error' => 'invalid_request'], Response::HTTP_BAD_REQUEST));
        }

        $this->dispatcher->dispatch(new BackchannelLogoutReceived($token));

        return self::noStore(new Response());
    }


    private static function noStore(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
