<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\Entity;

use DateTimeImmutable;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Model\WithdrawalRequestState;
use Emagma\SyliusOrderWithdrawalPlugin\Model\WithdrawalScope;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Resource\Model\ResourceInterface;

final class WithdrawalRequestTest extends TestCase
{
    public function testItIsASyliusResource(): void
    {
        self::assertInstanceOf(ResourceInterface::class, new WithdrawalRequest());
    }

    public function testItStartsInTheNewState(): void
    {
        $request = new WithdrawalRequest();

        self::assertSame(WithdrawalRequestState::New, $request->getStateEnum());
        self::assertSame('new', $request->getState());
    }

    public function testItExposesStateAsAStringForTheStateMachine(): void
    {
        $request = new WithdrawalRequest();

        $request->setState('confirmed');

        self::assertSame('confirmed', $request->getState());
        self::assertSame(WithdrawalRequestState::Confirmed, $request->getStateEnum());
    }

    public function testItDefaultsToAFullWithdrawalThatIsReceived(): void
    {
        $request = new WithdrawalRequest();

        self::assertSame(WithdrawalScope::Full, $request->getScope());
        self::assertFalse($request->isNotYetReceived());
    }

    public function testItComposesTheFullNameFromFirstAndLastName(): void
    {
        $request = new WithdrawalRequest();

        $request->setFirstName('Jean');
        $request->setLastName('Dupont');

        self::assertSame('Jean', $request->getFirstName());
        self::assertSame('Dupont', $request->getLastName());
        self::assertSame('Jean Dupont', $request->getFullName());
    }

    public function testItHoldsImmutableDates(): void
    {
        $request = new WithdrawalRequest();
        $orderDate = new DateTimeImmutable('2026-06-01');
        $submittedAt = new DateTimeImmutable('2026-06-20 14:30:00');

        $request->setOrderDate($orderDate);
        $request->setSubmittedAt($submittedAt);

        self::assertSame($orderDate, $request->getOrderDate());
        self::assertSame($submittedAt, $request->getSubmittedAt());
    }

    public function testItStoresTheCustomerData(): void
    {
        $request = new WithdrawalRequest();

        $request->setFirstName('Jean');
        $request->setLastName('Dupont');
        $request->setOrderNumber('000000123');
        $request->setEmail('jean@example.com');
        $request->setScope(WithdrawalScope::Partial);
        $request->setMessage('Renoncement partiel : article B uniquement.');
        $request->setLegalNoticeAccepted(true);

        self::assertSame('000000123', $request->getOrderNumber());
        self::assertSame('jean@example.com', $request->getEmail());
        self::assertSame(WithdrawalScope::Partial, $request->getScope());
        self::assertSame('Renoncement partiel : article B uniquement.', $request->getMessage());
        self::assertTrue($request->isLegalNoticeAccepted());
    }

    public function testItHasNoLocaleByDefault(): void
    {
        $request = new WithdrawalRequest();

        self::assertNull($request->getLocaleCode());

        $request->setLocaleCode('fr_FR');

        self::assertSame('fr_FR', $request->getLocaleCode());
    }

    public function testTouchRecordsWhenTheRequestWasLastUpdated(): void
    {
        $request = new WithdrawalRequest();

        self::assertNull($request->getUpdatedAt());

        $before = new DateTimeImmutable();
        $request->touch();
        $after = new DateTimeImmutable();

        $updatedAt = $request->getUpdatedAt();
        self::assertNotNull($updatedAt);
        self::assertGreaterThanOrEqual($before, $updatedAt);
        self::assertLessThanOrEqual($after, $updatedAt);
    }
}
