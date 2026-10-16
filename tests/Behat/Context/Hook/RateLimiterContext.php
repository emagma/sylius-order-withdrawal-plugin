<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Context\Hook;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Psr\Cache\CacheItemPoolInterface;

final readonly class RateLimiterContext implements Context
{
    public function __construct(private CacheItemPoolInterface $pool)
    {
    }

    #[BeforeScenario]
    public function resetTheWithdrawalRequestRateLimiter(): void
    {
        $this->pool->clear();
    }
}
