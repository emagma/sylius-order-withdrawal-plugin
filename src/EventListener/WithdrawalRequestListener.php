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
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsEventListener(event: 'emagma_sylius_order_withdrawal.withdrawal_request.pre_create', method: 'onPreCreate')]
#[AsEventListener(event: 'emagma_sylius_order_withdrawal.withdrawal_request.post_create', method: 'onPostCreate')]
#[AsEventListener(event: 'workflow.emagma_sylius_order_withdrawal_request.completed', method: 'onTransitionCompleted')]
final readonly class WithdrawalRequestListener
{
    public function __construct(
        private WithdrawalRequestEmailerInterface $emailer,
        private WithdrawalRequestNotifierAggregatorInterface $notifier,
        private EventDispatcherInterface $eventDispatcher,
        private ChannelContextInterface $channelContext,
        private LocaleContextInterface $localeContext,
    ) {
    }

    public function onPreCreate(ResourceControllerEvent $event): void
    {
        $request = $event->getSubject();
        if (!$request instanceof WithdrawalRequest) {
            return;
        }

        if (null === $request->getSubmittedAt()) {
            $request->setSubmittedAt(new DateTimeImmutable());
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

        $this->emailer->sendAcknowledgementEmail($request);
        $this->notifier->notify($request);
        $this->eventDispatcher->dispatch(new WithdrawalRequestSubmitted($request));
    }

    public function onTransitionCompleted(CompletedEvent $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof WithdrawalRequest) {
            return;
        }

        $subject->touch();

        $transition = $event->getTransition();
        if (null !== $transition && 'confirm' === $transition->getName()) {
            $this->emailer->sendConfirmationEmail($subject);
        }
    }
}
