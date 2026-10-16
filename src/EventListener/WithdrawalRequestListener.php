<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\EventListener;

use Emagma\SyliusOrderWithdrawalPlugin\Confirmation\PendingConfirmationQueue;
use Emagma\SyliusOrderWithdrawalPlugin\Confirmation\PendingConfirmationQueueInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Mailer\WithdrawalRequestEmailerInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Model\WithdrawalRequestTransition;
use Emagma\SyliusOrderWithdrawalPlugin\Notifier\WithdrawalRequestNotifierAggregatorInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Submission\WithdrawalRequestSubmissionHandler;
use Emagma\SyliusOrderWithdrawalPlugin\Submission\WithdrawalRequestSubmissionHandlerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;
use function trigger_deprecation;

#[AsEventListener(event: 'emagma_sylius_order_withdrawal.withdrawal_request.pre_create', method: 'onPreCreate')]
#[AsEventListener(event: 'emagma_sylius_order_withdrawal.withdrawal_request.post_create', method: 'onPostCreate')]
#[AsEventListener(event: 'workflow.' . WithdrawalRequestTransition::GRAPH . '.completed', method: 'onTransitionCompleted')]
#[AsEventListener(event: 'emagma_sylius_order_withdrawal.withdrawal_request.post_confirm', method: 'onPostConfirm')]
final readonly class WithdrawalRequestListener implements ResetInterface
{
    private WithdrawalRequestSubmissionHandlerInterface $submissionHandler;

    private PendingConfirmationQueueInterface $confirmationQueue;

    public function __construct(
        WithdrawalRequestEmailerInterface $emailer,
        WithdrawalRequestNotifierAggregatorInterface $notifier,
        EventDispatcherInterface $eventDispatcher,
        ChannelContextInterface $channelContext,
        LocaleContextInterface $localeContext,
        bool $sendAcknowledgement = true,
        ?LoggerInterface $logger = null,
        private ?ClockInterface $clock = null,
        ?WithdrawalRequestSubmissionHandlerInterface $submissionHandler = null,
        ?PendingConfirmationQueueInterface $confirmationQueue = null,
    ) {
        if (null === $submissionHandler || null === $confirmationQueue) {
            trigger_deprecation(
                'emagma/sylius-order-withdrawal-plugin',
                '2.2',
                'Not passing a "%s" and a "%s" to "%s" is deprecated; both will be required in 3.0. Without them, withdrawal requests confirmed through the API get no confirmation email.',
                WithdrawalRequestSubmissionHandlerInterface::class,
                PendingConfirmationQueueInterface::class,
                self::class,
            );
        }

        $this->submissionHandler = $submissionHandler ?? new WithdrawalRequestSubmissionHandler(
            $emailer,
            $notifier,
            $eventDispatcher,
            $channelContext,
            $localeContext,
            $sendAcknowledgement,
            $logger,
            $clock,
        );
        $this->confirmationQueue = $confirmationQueue ?? new PendingConfirmationQueue($emailer, $logger);
    }

    public function onPreCreate(ResourceControllerEvent $event): void
    {
        $request = $event->getSubject();
        if ($request instanceof WithdrawalRequest) {
            $this->submissionHandler->prepare($request);
        }
    }

    public function onPostCreate(ResourceControllerEvent $event): void
    {
        $request = $event->getSubject();
        if ($request instanceof WithdrawalRequest) {
            $this->submissionHandler->complete($request);
        }
    }

    public function onTransitionCompleted(CompletedEvent $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof WithdrawalRequest) {
            return;
        }

        $subject->touch($this->clock?->now());

        $transition = $event->getTransition();
        if (null !== $transition && WithdrawalRequestTransition::Confirm->value === $transition->getName()) {
            $this->confirmationQueue->enqueue($subject);
        }
    }

    /**
     * ResourceController flushes the transition before dispatching this event, so the
     * confirmation only goes out once the new state is committed. The name is `post_confirm`,
     * not `post_update`, because the admin route sets `_sylius.event: confirm`.
     */
    public function onPostConfirm(ResourceControllerEvent $event): void
    {
        $this->confirmationQueue->drain();
    }

    public function reset(): void
    {
        $this->confirmationQueue->reset();
    }
}
