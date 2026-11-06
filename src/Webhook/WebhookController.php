<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Webhook;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use VetorZero\VlIdcBundle\Jwt\InvalidTokenException;

/**
 * Recebe os avisos da central (RFC 8935): um SET por POST, com o JWT como corpo.
 */
final class WebhookController
{
    public function __construct(
        private readonly SecurityEventVerifier $verifier,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }


    public function __invoke(Request $request): Response
    {
        try {
            $event = $this->verifier->verify(trim($request->getContent()));
        } catch (InvalidTokenException $e) {
            $this->logger->warning('Rejected a VL-IdC security event: {reason}', [
                'reason' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new Response(status: Response::HTTP_BAD_REQUEST);
        }

        if (!$event->isVerification()) {
            $this->dispatcher->dispatch(new SecurityEventReceived($event));
        }

        return new Response(status: Response::HTTP_ACCEPTED);
    }
}
