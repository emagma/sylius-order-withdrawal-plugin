<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Only wired when emagma_sylius_order_withdrawal.rate_limiter_service names a limiter; the
 * plugin defines none itself.
 */
final class WithdrawalRequestRateLimitListener
{
    public function __construct(
        private readonly RateLimiterFactory $limiterFactory,
        private readonly string $routeName,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->isMethod('POST') || $this->routeName !== $request->attributes->get('_route')) {
            return;
        }

        $limiter = $this->limiterFactory->create($request->getClientIp() ?? 'unknown');

        if (!$limiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }
    }
}
