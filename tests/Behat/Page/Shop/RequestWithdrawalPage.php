<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Page\Shop;

use Sylius\Behat\Page\SymfonyPage;

final class RequestWithdrawalPage extends SymfonyPage implements RequestWithdrawalPageInterface
{
    public function getRouteName(): string
    {
        return 'emagma_sylius_order_withdrawal_shop_request';
    }

    public function specifyFirstName(string $firstName): void
    {
        $this->getElement('first_name')->setValue($firstName);
    }

    public function specifyLastName(string $lastName): void
    {
        $this->getElement('last_name')->setValue($lastName);
    }

    public function specifyOrderNumber(string $orderNumber): void
    {
        $this->getElement('order_number')->setValue($orderNumber);
    }

    public function specifyOrderDate(string $date): void
    {
        $this->getElement('order_date')->setValue($date);
    }

    public function specifyReceptionDate(string $date): void
    {
        $this->getElement('reception_date')->setValue($date);
    }

    public function specifyEmail(string $email): void
    {
        $this->getElement('email')->setValue($email);
    }

    public function declareOrderNotReceivedYet(): void
    {
        $this->getElement('not_yet_received')->check();
    }

    public function chooseScope(string $scope): void
    {
        $this->getDocument()->selectFieldOption('emagma_sylius_order_withdrawal_request[scope]', $scope);
    }

    public function provideDetail(string $detail): void
    {
        $this->getElement('message')->setValue($detail);
    }

    public function acceptLegalNotice(): void
    {
        $this->getElement('legal_notice')->check();
    }

    public function confirm(): void
    {
        $this->getElement('submit')->click();
    }

    public function hasContent(string $content): bool
    {
        return str_contains($this->getDocument()->getText(), $content);
    }

    protected function getDefinedElements(): array
    {
        return [
            'first_name' => '#emagma_sylius_order_withdrawal_request_firstName',
            'last_name' => '#emagma_sylius_order_withdrawal_request_lastName',
            'order_number' => '#emagma_sylius_order_withdrawal_request_orderNumber',
            'order_date' => '#emagma_sylius_order_withdrawal_request_orderDate',
            'reception_date' => '#emagma_sylius_order_withdrawal_request_receptionDate',
            'email' => '#emagma_sylius_order_withdrawal_request_email',
            'message' => '#emagma_sylius_order_withdrawal_request_message',
            'not_yet_received' => '#emagma_sylius_order_withdrawal_request_notYetReceived',
            'legal_notice' => '#emagma_sylius_order_withdrawal_request_legalNoticeAccepted',
            'submit' => '#emagma-order-withdrawal-submit',
        ];
    }
}
