<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\Model;

use Emagma\SyliusOrderWithdrawalPlugin\Model\WithdrawalScope;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class WithdrawalScopeTest extends TestCase
{
    public function testItIsABackedEnumWithTwoCases(): void
    {
        self::assertSame('full', WithdrawalScope::Full->value);
        self::assertSame('partial', WithdrawalScope::Partial->value);
    }

    public function testItIsTranslatable(): void
    {
        self::assertInstanceOf(TranslatableInterface::class, WithdrawalScope::Full);
    }

    public function testItTranslatesEachCaseViaItsKey(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);

        $translator
            ->expects($this->once())
            ->method('trans')
            ->with('emagma_sylius_order_withdrawal.scope.partial', [], null, 'fr')
            ->willReturn('Une partie de ma commande');

        self::assertSame('Une partie de ma commande', WithdrawalScope::Partial->trans($translator, 'fr'));
    }
}
