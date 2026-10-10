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
use Sylius\Component\Core\Model\ChannelInterface;
use function trim;

final class DefaultRecipientResolver implements RecipientResolverInterface
{
    /**
     * @param string|null $notificationEmail null when unset, and also when it comes from an
     *                                       %env(default::…)% placeholder whose variable is unset
     */
    public function __construct(
        private readonly ?string $notificationEmail,
        private readonly bool $preferChannelContactEmail = true,
    ) {
    }

    public function getRecipients(WithdrawalRequest $request): array
    {
        $channel = $request->getChannel();

        if ($this->preferChannelContactEmail && $channel instanceof ChannelInterface) {
            $contactEmail = trim((string) $channel->getContactEmail());

            if ('' !== $contactEmail) {
                return [$contactEmail];
            }
        }

        $notificationEmail = trim((string) $this->notificationEmail);

        return '' !== $notificationEmail ? [$notificationEmail] : [];
    }
}
