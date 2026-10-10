<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use DateTimeImmutable;
use Doctrine\Persistence\ObjectManager;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class WithdrawalRequestContext implements Context
{
    public function __construct(
        private readonly SharedStorageInterface $sharedStorage,
        private readonly FactoryInterface $withdrawalRequestFactory,
        private readonly ObjectManager $withdrawalRequestManager,
    ) {
    }

    #[Given('there is a withdrawal request for order :orderNumber from :name in state :state')]
    public function thereIsAWithdrawalRequestForOrderFromInState(string $orderNumber, string $name, string $state): void
    {
        $this->createWithdrawalRequest($orderNumber, $name, 'customer@example.com', $state);
    }

    #[Given('there is a withdrawal request for order :orderNumber from :name with email :email in state :state')]
    public function thereIsAWithdrawalRequestForOrderFromWithEmailInState(string $orderNumber, string $name, string $email, string $state): void
    {
        $this->createWithdrawalRequest($orderNumber, $name, $email, $state);
    }

    private function createWithdrawalRequest(string $orderNumber, string $name, string $email, string $state): void
    {
        [$firstName, $lastName] = array_pad(explode(' ', $name, 2), 2, '');

        /** @var WithdrawalRequest $request */
        $request = $this->withdrawalRequestFactory->createNew();
        $request->setFirstName($firstName);
        $request->setLastName($lastName);
        $request->setOrderNumber($orderNumber);
        $request->setOrderDate(new DateTimeImmutable('2026-06-01'));
        $request->setEmail($email);
        $request->setState($this->normalizeState($state));
        $request->setSubmittedAt(new DateTimeImmutable());

        if ($this->sharedStorage->has('channel')) {
            $request->setChannel($this->sharedStorage->get('channel'));
        }

        $this->withdrawalRequestManager->persist($request);
        $this->withdrawalRequestManager->flush();

        $this->sharedStorage->set('withdrawal_request', $request);
    }

    private function normalizeState(string $state): string
    {
        return str_replace(' ', '_', $state);
    }
}
