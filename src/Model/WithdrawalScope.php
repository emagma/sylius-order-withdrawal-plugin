<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Model;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum WithdrawalScope: string implements TranslatableInterface
{
    case Full = 'full';
    case Partial = 'partial';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('emagma_sylius_order_withdrawal.scope.' . $this->value, [], null, $locale);
    }
}
