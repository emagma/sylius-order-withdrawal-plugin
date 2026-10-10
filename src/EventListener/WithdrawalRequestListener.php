<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\EventListener;

use DateTimeImmutable;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Event\WithdrawalRequestSubmitted;
use Emagma\SyliusOrderWithdrawalPlugin\Mailer\WithdrawalRequestEmailerInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Notifier\WithdrawalRequestNotifierAggregatorInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use SM\Event\SMEvents;
use SM\Event\TransitionEvent;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

#[AsEventListener(event: 'emagma_sylius_order_withdrawal.withdrawal_request.pre_create', method: 'onPreCreate')]
#[AsEventListener(event: 'emagma_sylius_order_withdrawal.withdrawal_request.post_create', method: 'onPostCreate')]
#[AsEventListener(event: SMEvents::POST_TRANSITION, method: 'onTransitionApplied')]
#[AsEventListener(event: 'emagma_sylius_order_withdrawal.withdrawal_request.post_confirm', method: 'onPostConfirm')]
final class WithdrawalRequestListener
{
    public const GRAPH = 'emagma_sylius_order_withdrawal_request';

    /** @var list<WithdrawalRequest> */
    private array $pendingConfirmations = [];

    public function __construct(
        private readonly WithdrawalRequestEmailerInterface $emailer,
        private readonly WithdrawalRequestNotifierAggregatorInterface $notifier,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ChannelContextInterface $channelContext,
        private readonly LocaleContextInterface $localeContext,
        private readonly bool $sendAcknowledgement = true,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?ClockInterface $clock = null,
    ) {
    }

    public function onPreCreate(ResourceControllerEvent $event): void
    {
        $request = $event->getSubject();
        if (!$request instanceof WithdrawalRequest) {
            return;
        }

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

    private function resolveChannel(): ?ChannelInterface
    {
        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return null;
        }

        return $channel instanceof ChannelInterface ? $channel : null;
    }

    public function onPostCreate(ResourceControllerEvent $event): void
    {
        $request = $event->getSubject();
        if (!$request instanceof WithdrawalRequest) {
            return;
        }

        if ($this->sendAcknowledgement) {
            $this->guard(fn () => $this->emailer->sendAcknowledgementEmail($request), 'acknowledgement email', $request);
        }

        $this->guard(fn () => $this->notifier->notify($request), 'notification', $request);
        $this->guard(fn () => $this->eventDispatcher->dispatch(new WithdrawalRequestSubmitted($request)), 'submitted event', $request);
    }

    /**
     * The resource is already flushed when post_create runs, so a failing side effect must be
     * logged rather than turned into a 500 the customer would retry.
     */
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

    /**
     * winzou dispatches POST_TRANSITION once the new state is set, before the controller
     * flushes — the same point Sylius 2.x's `workflow.<graph>.completed` fires at.
     *
     * An event rather than a `callbacks` entry in the graph, because redeclaring a winzou graph
     * replaces its callbacks wholesale: a host app that adds one state would silently lose the
     * timestamp and the confirmation email. The event survives any redefinition of the graph.
     *
     * The event is global to every winzou graph in the application, hence the two guards.
     */
    public function onTransitionApplied(TransitionEvent $event): void
    {
        $stateMachine = $event->getStateMachine();

        if (self::GRAPH !== $stateMachine->getGraph()) {
            return;
        }

        $subject = $stateMachine->getObject();
        if (!$subject instanceof WithdrawalRequest) {
            return;
        }

        $subject->touch($this->clock?->now());

        if ('confirm' === $event->getTransition()) {
            $this->pendingConfirmations[] = $subject;
        }
    }

    /**
     * ResourceController flushes the transition before dispatching this event, so the
     * confirmation only goes out once the new state is committed. The name is `post_confirm`,
     * not `post_update`, because the admin route sets `_sylius.event: confirm`.
     */
    public function onPostConfirm(ResourceControllerEvent $event): void
    {
        $pending = $this->pendingConfirmations;
        $this->pendingConfirmations = [];

        foreach ($pending as $request) {
            $this->guard(fn () => $this->emailer->sendConfirmationEmail($request), 'confirmation email', $request);
        }
    }
}
