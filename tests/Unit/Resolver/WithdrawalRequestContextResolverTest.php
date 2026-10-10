<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\Resolver;

use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Resolver\WithdrawalRequestContextResolver;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;

final class WithdrawalRequestContextResolverTest extends TestCase
{
    public function testItPrefersTheRequestsOwnChannelOverTheAmbientOne(): void
    {
        $requestChannel = $this->createMock(ChannelInterface::class);
        $ambientChannel = $this->createMock(ChannelInterface::class);

        $request = new WithdrawalRequest();
        $request->setChannel($requestChannel);

        $resolver = new WithdrawalRequestContextResolver($this->channelContext($ambientChannel), $this->localeContext());

        self::assertSame($requestChannel, $resolver->resolveChannel($request));
    }

    public function testItFallsBackToTheAmbientChannelWhenTheRequestHasNone(): void
    {
        $ambientChannel = $this->createMock(ChannelInterface::class);
        $request = new WithdrawalRequest();

        $resolver = new WithdrawalRequestContextResolver($this->channelContext($ambientChannel), $this->localeContext());

        self::assertSame($ambientChannel, $resolver->resolveChannel($request));
    }

    public function testItResolvesToNoChannelWhenTheRequestHasNoneAndTheAmbientChannelCannotBeResolved(): void
    {
        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willThrowException(new ChannelNotFoundException());

        $resolver = new WithdrawalRequestContextResolver($channelContext, $this->localeContext());

        self::assertNull($resolver->resolveChannel(new WithdrawalRequest()));
    }

    public function testItPrefersTheRequestsOwnLocaleCodeOverTheAmbientOne(): void
    {
        $request = new WithdrawalRequest();
        $request->setLocaleCode('fr_FR');

        $resolver = new WithdrawalRequestContextResolver($this->createMock(ChannelContextInterface::class), $this->localeContext());

        self::assertSame('fr_FR', $resolver->resolveLocaleCode($request));
    }

    public function testItFallsBackToTheAmbientLocaleCodeWhenTheRequestHasNone(): void
    {
        $resolver = new WithdrawalRequestContextResolver($this->createMock(ChannelContextInterface::class), $this->localeContext());

        self::assertSame('en_US', $resolver->resolveLocaleCode(new WithdrawalRequest()));
    }

    private function channelContext(ChannelInterface $channel): ChannelContextInterface
    {
        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);

        return $channelContext;
    }

    private function localeContext(): LocaleContextInterface
    {
        $localeContext = $this->createMock(LocaleContextInterface::class);
        $localeContext->method('getLocaleCode')->willReturn('en_US');

        return $localeContext;
    }
}
