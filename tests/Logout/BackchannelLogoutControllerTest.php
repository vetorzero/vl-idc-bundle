<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Tests\Logout;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use VetorZero\VlIdcBundle\Jwt\InvalidTokenException;
use VetorZero\VlIdcBundle\Logout\BackchannelLogoutController;
use VetorZero\VlIdcBundle\Logout\BackchannelLogoutReceived;
use VetorZero\VlIdcBundle\Logout\LogoutToken;
use VetorZero\VlIdcBundle\Logout\LogoutTokenVerifier;

class BackchannelLogoutControllerTest extends TestCase
{
    private EventDispatcher $dispatcher;
    /** @var BackchannelLogoutReceived[] */
    private array $dispatched = [];


    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->dispatcher->addListener(BackchannelLogoutReceived::class, function (BackchannelLogoutReceived $e) {
            $this->dispatched[] = $e;
        });
    }


    public function testAcceptsAndDispatches(): void
    {
        $token = new LogoutToken('vlidc_usr_01', 'sess-1');

        $response = $this->controller($token)(self::request(['logout_token' => "jwt\n"]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('no-store', (string)$response->headers->get('Cache-Control'));
        $this->assertCount(1, $this->dispatched);
        $this->assertSame($token, $this->dispatched[0]->token);
    }


    public function testRejectsInvalidToken(): void
    {
        $response = $this->controller(new InvalidTokenException('bad signature'))(self::request(['logout_token' => 'jwt']));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('{"error":"invalid_request"}', $response->getContent());
        $this->assertStringContainsString('no-store', (string)$response->headers->get('Cache-Control'));
        $this->assertEmpty($this->dispatched);
    }


    public function testRejectsMissingToken(): void
    {
        $response = $this->controller(new InvalidTokenException('empty'), expected: '')(self::request([]));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertEmpty($this->dispatched);
    }


    public function testRejectsTokenSentAsArray(): void
    {
        $response = $this->controller(new InvalidTokenException('empty'), expected: '')(self::request(['logout_token' => ['jwt']]));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('{"error":"invalid_request"}', $response->getContent());
    }


    public function testListenerFailureBubblesUp(): void
    {
        $this->dispatcher->addListener(BackchannelLogoutReceived::class, fn() => throw new \RuntimeException('database down'));

        $this->expectExceptionMessage('database down');
        $this->controller(new LogoutToken('vlidc_usr_01', null))(self::request(['logout_token' => 'jwt']));
    }


    private function controller(
        LogoutToken|InvalidTokenException $outcome,
        string $expected = 'jwt',
    ): BackchannelLogoutController {
        $verifier = $this->createMock(LogoutTokenVerifier::class);
        $verify = $verifier->method('verify')->with($expected);
        $outcome instanceof LogoutToken ? $verify->willReturn($outcome) : $verify->willThrowException($outcome);

        return new BackchannelLogoutController($verifier, $this->dispatcher, new NullLogger());
    }


    /** @param array<string, mixed> $form */
    private static function request(array $form): Request
    {
        return Request::create('/webhooks/idc/backchannel-logout', 'POST', $form, server: [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ]);
    }
}
