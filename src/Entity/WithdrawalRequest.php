<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Entity;

use DateTimeImmutable;
use Emagma\SyliusOrderWithdrawalPlugin\Model\WithdrawalRequestState;
use Emagma\SyliusOrderWithdrawalPlugin\Model\WithdrawalScope;
use function sprintf;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use function trim;

class WithdrawalRequest implements ResourceInterface
{
    protected ?int $id = null;

    protected ?string $firstName = null;

    protected ?string $lastName = null;

    protected ?string $orderNumber = null;

    protected ?DateTimeImmutable $orderDate = null;

    protected bool $notYetReceived = false;

    protected ?DateTimeImmutable $receptionDate = null;

    protected ?string $email = null;

    protected WithdrawalScope $scope = WithdrawalScope::Full;

    protected ?string $message = null;

    protected bool $legalNoticeAccepted = false;

    protected WithdrawalRequestState $state = WithdrawalRequestState::New;

    protected ?ChannelInterface $channel = null;

    protected ?string $localeCode = null;

    protected ?DateTimeImmutable $submittedAt = null;

    protected ?DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getFullName(): string
    {
        return trim(sprintf('%s %s', (string) $this->firstName, (string) $this->lastName));
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(?string $orderNumber): void
    {
        $this->orderNumber = $orderNumber;
    }

    public function getOrderDate(): ?DateTimeImmutable
    {
        return $this->orderDate;
    }

    public function setOrderDate(?DateTimeImmutable $orderDate): void
    {
        $this->orderDate = $orderDate;
    }

    public function isNotYetReceived(): bool
    {
        return $this->notYetReceived;
    }

    public function setNotYetReceived(bool $notYetReceived): void
    {
        $this->notYetReceived = $notYetReceived;
    }

    public function getReceptionDate(): ?DateTimeImmutable
    {
        return $this->receptionDate;
    }

    public function setReceptionDate(?DateTimeImmutable $receptionDate): void
    {
        $this->receptionDate = $receptionDate;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getScope(): WithdrawalScope
    {
        return $this->scope;
    }

    public function setScope(WithdrawalScope $scope): void
    {
        $this->scope = $scope;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }

    public function isLegalNoticeAccepted(): bool
    {
        return $this->legalNoticeAccepted;
    }

    public function setLegalNoticeAccepted(bool $legalNoticeAccepted): void
    {
        $this->legalNoticeAccepted = $legalNoticeAccepted;
    }

    public function getStateEnum(): WithdrawalRequestState
    {
        return $this->state;
    }

    public function getState(): string
    {
        return $this->state->value;
    }

    public function setState(string $state): void
    {
        $this->state = WithdrawalRequestState::from($state);
    }

    public function getChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    public function setChannel(?ChannelInterface $channel): void
    {
        $this->channel = $channel;
    }

    public function getLocaleCode(): ?string
    {
        return $this->localeCode;
    }

    public function setLocaleCode(?string $localeCode): void
    {
        $this->localeCode = $localeCode;
    }

    public function getSubmittedAt(): ?DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(?DateTimeImmutable $submittedAt): void
    {
        $this->submittedAt = $submittedAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
