<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Page\Admin\WithdrawalRequest;

use FriendsOfBehat\PageObjectExtension\Page\SymfonyPage;

final class ShowPage extends SymfonyPage implements ShowPageInterface
{
    public function getRouteName(): string
    {
        return 'emagma_sylius_order_withdrawal_admin_withdrawal_request_show';
    }

    public function takeInCharge(): void
    {
        $this->getElement('take_in_charge')->press();
    }

    public function confirm(): void
    {
        $this->getElement('confirm')->press();
    }

    public function reject(): void
    {
        $this->getElement('reject')->press();
    }

    public function getStateLabel(): string
    {
        return trim($this->getElement('state_badge')->getText());
    }

    public function hasContent(string $content): bool
    {
        return str_contains($this->getDocument()->getText(), $content);
    }

    protected function getDefinedElements(): array
    {
        return [
            'take_in_charge' => '#emagma-withdrawal-transition-start',
            'confirm' => '#emagma-withdrawal-transition-confirm',
            'reject' => '#emagma-withdrawal-transition-reject',
            'state_badge' => '.ui.header .label',
        ];
    }
}
