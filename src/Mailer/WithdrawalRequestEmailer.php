<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Mailer;

use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;

final readonly class WithdrawalRequestEmailer implements WithdrawalRequestEmailerInterface
{
    public const ACKNOWLEDGEMENT = 'emagma_withdrawal_request_acknowledgement';

    public const CONFIRMATION = 'emagma_withdrawal_request_confirmation';

    public function __construct(
        private SenderInterface $sender,
        private ChannelContextInterface $channelContext,
        private LocaleContextInterface $localeContext,
    ) {
    }

    public function sendAcknowledgementEmail(WithdrawalRequest $request): void
    {
        $this->sender->send(self::ACKNOWLEDGEMENT, [(string) $request->getEmail()], $this->emailContext($request));
    }

    public function sendConfirmationEmail(WithdrawalRequest $request): void
    {
        $this->sender->send(self::CONFIRMATION, [(string) $request->getEmail()], $this->emailContext($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function emailContext(WithdrawalRequest $request): array
    {
        return [
            'withdrawalRequest' => $request,
            'channel' => $this->resolveChannel($request),
            'localeCode' => $request->getLocaleCode() ?? $this->localeContext->getLocaleCode(),
        ];
    }

    /**
     * Prefers the channel the request was originally submitted through — callbacks reached from
     * the admin backend (e.g. the confirm state-machine transition) have no ambient shop channel
     * of their own, so falling back to $channelContext here would pick up the wrong channel.
     */
    private function resolveChannel(WithdrawalRequest $request): ?ChannelInterface
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
}
