<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Submission;

use DateTimeImmutable;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Event\WithdrawalRequestSubmitted;
use Emagma\SyliusOrderWithdrawalPlugin\Mailer\WithdrawalRequestEmailerInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Notifier\WithdrawalRequestNotifierAggregatorInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * @internal
 */
final readonly class WithdrawalRequestSubmissionHandler implements WithdrawalRequestSubmissionHandlerInterface
{
    public function __construct(
        private WithdrawalRequestEmailerInterface $emailer,
        private WithdrawalRequestNotifierAggregatorInterface $notifier,
        private EventDispatcherInterface $eventDispatcher,
        private ChannelContextInterface $channelContext,
        private LocaleContextInterface $localeContext,
        private bool $sendAcknowledgement = true,
        private ?LoggerInterface $logger = null,
        private ?ClockInterface $clock = null,
    ) {
    }

    public function prepare(WithdrawalRequest $request): void
    {
        if (null === $request->getSubmittedAt()) {
            $request->setSubmittedAt($this->clock?->now() ?? new DateTimeImmutable());
        }

        if (null === $request->getChannel()) {
            $request->setChannel($this->resolveChannel());
        }

        if (null === $request->getLocaleCode()) {
            $request->setLocaleCode($this->localeContext->getLocaleCode());
        }
    }

    public function complete(WithdrawalRequest $request): void
    {
        if ($this->sendAcknowledgement) {
            $this->guard(fn () => $this->emailer->sendAcknowledgementEmail($request), 'acknowledgement email', $request);
        }

        $this->guard(fn () => $this->notifier->notify($request), 'notification', $request);
        $this->guard(fn () => $this->eventDispatcher->dispatch(new WithdrawalRequestSubmitted($request)), 'submitted event', $request);
    }

    private function resolveChannel(): ?ChannelInterface
    {
        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return null;
        }

        return $channel instanceof ChannelInterface ? $channel : null;
    }

    private function guard(callable $step, string $description, WithdrawalRequest $request): void
    {
        try {
            $step();
        } catch (Throwable $exception) {
            $this->logger?->error(sprintf('Withdrawal request %s failed.', $description), [
                'withdrawalRequest' => $request->getId(),
                'exception' => $exception,
            ]);
        }
    }
}
