<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\Model;

use Emagma\SyliusOrderWithdrawalPlugin\Model\WithdrawalRequestState;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class WithdrawalRequestStateTest extends TestCase
{
    public function testItIsABackedEnumWithFourStates(): void
    {
        self::assertSame('new', WithdrawalRequestState::New->value);
        self::assertSame('in_progress', WithdrawalRequestState::InProgress->value);
        self::assertSame('confirmed', WithdrawalRequestState::Confirmed->value);
        self::assertSame('rejected', WithdrawalRequestState::Rejected->value);
    }

    public function testItIsTranslatable(): void
    {
        self::assertInstanceOf(TranslatableInterface::class, WithdrawalRequestState::New);
    }

    public function testItTranslatesEachCaseViaItsKey(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);

        $translator
            ->expects($this->once())
            ->method('trans')
            ->with('emagma_sylius_order_withdrawal.state.confirmed', [], null, 'fr')
            ->willReturn('Confirmée')
        ;

        $label = WithdrawalRequestState::Confirmed->trans($translator, 'fr');

        self::assertSame('Confirmée', $label);
    }
}
