<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Page\Shop;

use FriendsOfBehat\PageObjectExtension\Page\SymfonyPageInterface;

interface RequestWithdrawalPageInterface extends SymfonyPageInterface
{
    public function specifyFirstName(string $firstName): void;

    public function specifyLastName(string $lastName): void;

    public function specifyOrderNumber(string $orderNumber): void;

    public function specifyOrderDate(string $date): void;

    public function specifyReceptionDate(string $date): void;

    public function specifyEmail(string $email): void;

    public function declareOrderNotReceivedYet(): void;

    public function chooseScope(string $scope): void;

    public function provideDetail(string $detail): void;

    public function acceptLegalNotice(): void;

    public function confirm(): void;

    public function hasContent(string $content): bool;
}
