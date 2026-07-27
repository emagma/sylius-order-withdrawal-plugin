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
use Sylius\Component\Core\Model\ChannelInterface;

final readonly class DefaultRecipientResolver implements RecipientResolverInterface
{
    public function __construct(
        private string $notificationEmail,
        private bool $preferChannelContactEmail = true,
    ) {
    }

    public function getRecipients(WithdrawalRequest $request): array
    {
        $channel = $request->getChannel();
        if ($this->preferChannelContactEmail &&
            $channel instanceof ChannelInterface &&
            null !== $channel->getContactEmail()
        ) {
            return [$channel->getContactEmail()];
        }

        return '' !== $this->notificationEmail ? [$this->notificationEmail] : [];
    }
}
