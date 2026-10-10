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
use Emagma\SyliusOrderWithdrawalPlugin\Resolver\WithdrawalRequestContextResolverInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;

final readonly class WithdrawalRequestEmailer implements WithdrawalRequestEmailerInterface
{
    public const ACKNOWLEDGEMENT = 'emagma_withdrawal_request_acknowledgement';

    public const CONFIRMATION = 'emagma_withdrawal_request_confirmation';

    public function __construct(
        private SenderInterface $sender,
        private WithdrawalRequestContextResolverInterface $contextResolver,
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
            'channel' => $this->contextResolver->resolveChannel($request),
            'localeCode' => $this->contextResolver->resolveLocaleCode($request),
        ];
    }
}
