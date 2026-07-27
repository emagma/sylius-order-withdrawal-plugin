<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Notifier;

use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Mailer\RecipientResolverInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;

final readonly class EmailWithdrawalRequestNotifier implements WithdrawalRequestNotifierInterface
{
    public const NOTIFICATION = 'emagma_withdrawal_request_notification';

    public function __construct(
        private SenderInterface $sender,
        private RecipientResolverInterface $recipientResolver,
        private ChannelContextInterface $channelContext,
        private LocaleContextInterface $localeContext,
    ) {
    }

    public function notify(WithdrawalRequest $request): void
    {
        $recipients = $this->recipientResolver->getRecipients($request);

        if ([] === $recipients) {
            return;
        }

        $this->sender->send(self::NOTIFICATION, $recipients, [
            'withdrawalRequest' => $request,
            'channel' => $this->channelContext->getChannel(),
            'localeCode' => $this->localeContext->getLocaleCode(),
        ]);
    }
}
