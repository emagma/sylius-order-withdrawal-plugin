<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Page\Admin\WithdrawalRequest;

use Sylius\Behat\Page\Admin\Crud\IndexPage as BaseIndexPage;

final class IndexPage extends BaseIndexPage implements IndexPageInterface
{
    public function filterByState(string $state): void
    {
        $this->getElement('state_filter')->selectOption($state);
        $this->filter();
    }

    public function hasRequestForOrder(string $orderNumber): bool
    {
        return $this->isSingleResourceOnPage(['orderNumber' => $orderNumber]) ||
            str_contains($this->getDocument()->getText(), $orderNumber);
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'state_filter' => '#criteria_state',
        ]);
    }
}
