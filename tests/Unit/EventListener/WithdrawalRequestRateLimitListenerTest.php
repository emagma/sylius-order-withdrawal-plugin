<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\EventListener;

use Emagma\SyliusOrderWithdrawalPlugin\EventListener\WithdrawalRequestRateLimitListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class WithdrawalRequestRateLimitListenerTest extends TestCase
{
    private const ROUTE = 'emagma_sylius_order_withdrawal_shop_request';

    public function testItAllowsSubmissionsUpToTheLimit(): void
    {
        $listener = $this->listener(limit: 2);

        $listener($this->event('POST', self::ROUTE));
        $listener($this->event('POST', self::ROUTE));

        $this->expectNotToPerformAssertions();
    }

    public function testItRejectsTheSubmissionThatExceedsTheLimit(): void
    {
        $listener = $this->listener(limit: 1);
        $listener($this->event('POST', self::ROUTE));

        $this->expectException(TooManyRequestsHttpException::class);

        $listener($this->event('POST', self::ROUTE));
    }

    public function testItIgnoresGetRequests(): void
    {
        $listener = $this->listener(limit: 1);

        $listener($this->event('GET', self::ROUTE));
        $listener($this->event('GET', self::ROUTE));

        $this->expectNotToPerformAssertions();
    }

    public function testItIgnoresOtherRoutes(): void
    {
        $listener = $this->listener(limit: 1);

        $listener($this->event('POST', 'sylius_shop_homepage'));
        $listener($this->event('POST', 'sylius_shop_homepage'));

        $this->expectNotToPerformAssertions();
    }

    private function listener(int $limit): WithdrawalRequestRateLimitListener
    {
        $factory = new RateLimiterFactory(
            [
                'id' => 'test',
                'policy' => 'fixed_window',
                'limit' => $limit,
                'interval' => '1 hour',
            ],
            new InMemoryStorage(),
        );

        return new WithdrawalRequestRateLimitListener($factory, self::ROUTE);
    }

    private function event(string $method, string $route): RequestEvent
    {
        $request = Request::create('/withdrawal-request', $method);
        $request->attributes->set('_route', $route);
        $request->server->set('REMOTE_ADDR', '203.0.113.9');

        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
