<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Resolver;

use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;

final readonly class WithdrawalRequestContextResolver implements WithdrawalRequestContextResolverInterface
{
    public function __construct(
        private ChannelContextInterface $channelContext,
        private LocaleContextInterface $localeContext,
    ) {
    }

    /**
     * Prefers the channel the request was originally submitted through — callbacks reached from
     * the admin backend (e.g. the confirm state-machine transition) have no ambient shop channel
     * of their own, so falling back to $channelContext here would pick up the wrong channel.
     */
    public function resolveChannel(WithdrawalRequest $request): ?ChannelInterface
    {
        $channel = $request->getChannel();
        if (null !== $channel) {
            return $channel;
        }

        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return null;
        }

        return $channel instanceof ChannelInterface ? $channel : null;
    }

    public function resolveLocaleCode(WithdrawalRequest $request): string
    {
        return $request->getLocaleCode() ?? $this->localeContext->getLocaleCode();
    }
}
