<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Tests\Webhook;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use VetorZero\VlIdcBundle\Jwt\InvalidTokenException;
use VetorZero\VlIdcBundle\Webhook\SecurityEvent;
use VetorZero\VlIdcBundle\Webhook\SecurityEventReceived;
use VetorZero\VlIdcBundle\Webhook\SecurityEventVerifier;
use VetorZero\VlIdcBundle\Webhook\Subject;
use VetorZero\VlIdcBundle\Webhook\WebhookController;

class WebhookControllerTest extends TestCase
{
    private EventDispatcher $dispatcher;
    /** @var SecurityEventReceived[] */
    private array $dispatched = [];


    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->dispatcher->addListener(SecurityEventReceived::class, function (SecurityEventReceived $e) {
            $this->dispatched[] = $e;
        });
    }


    public function testAcceptsAndDispatchesEvent(): void
    {
        $event = new SecurityEvent('evt-1', 'urn:vl-idc:event:user.updated', new Subject('user', 'vlidc_usr_01'), null, false);

        $response = $this->controller($event)(self::request("jwt\n"));

        $this->assertSame(202, $response->getStatusCode());
        $this->assertCount(1, $this->dispatched);
        $this->assertSame($event, $this->dispatched[0]->event);
    }


    public function testAcceptsVerificationWithoutDispatching(): void
    {
        $event = new SecurityEvent('evt-1', SecurityEvent::VERIFICATION, null, null, false);

        $this->assertSame(202, $this->controller($event)(self::request('jwt'))->getStatusCode());
        $this->assertEmpty($this->dispatched);
    }


    public function testRejectsInvalidToken(): void
    {
        $response = $this->controller(new InvalidTokenException('bad signature'))(self::request('jwt'));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertEmpty($this->dispatched);
    }


    public function testListenerFailureBubblesUp(): void
    {
        $this->dispatcher->addListener(SecurityEventReceived::class, fn() => throw new \RuntimeException('database down'));
        $event = new SecurityEvent('evt-1', 'urn:vl-idc:event:user.updated', new Subject('user', 'vlidc_usr_01'), null, false);

        $this->expectExceptionMessage('database down');
        $this->controller($event)(self::request('jwt'));
    }


    private function controller(SecurityEvent|InvalidTokenException $outcome): WebhookController
    {
        $verifier = $this->createMock(SecurityEventVerifier::class);
        $verify = $verifier->method('verify')->with('jwt');
        $outcome instanceof SecurityEvent ? $verify->willReturn($outcome) : $verify->willThrowException($outcome);

        return new WebhookController($verifier, $this->dispatcher, new NullLogger());
    }


    private static function request(string $body): Request
    {
        return Request::create('/webhooks/idc', 'POST', server: ['CONTENT_TYPE' => 'application/secevent+jwt'], content: $body);
    }
}
