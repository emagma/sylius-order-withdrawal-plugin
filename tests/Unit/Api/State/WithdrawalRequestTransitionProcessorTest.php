<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\Api\State;

use ApiPlatform\Metadata\Patch;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Emagma\SyliusOrderWithdrawalPlugin\Api\State\WithdrawalRequestTransitionProcessor;
use Emagma\SyliusOrderWithdrawalPlugin\Confirmation\PendingConfirmationQueueInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\StateMachine\WithdrawalRequestTransitionApplierInterface;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sylius\Abstraction\StateMachine\Exception\StateMachineExecutionException;
use Throwable;

final class WithdrawalRequestTransitionProcessorTest extends TestCase
{
    /** @var list<string> */
    private array $calls = [];

    public function testItLocksAppliesCommitsThenDrains(): void
    {
        $request = new WithdrawalRequest();

        $applier = $this->createMock(WithdrawalRequestTransitionApplierInterface::class);
        $applier->method('apply')->with($request, 'confirm')->willReturnCallback(function (): void { $this->calls[] = 'apply'; });

        $queue = $this->createMock(PendingConfirmationQueueInterface::class);
        $queue->method('drain')->willReturnCallback(function (): void { $this->calls[] = 'drain'; });
        $queue->expects(self::never())->method('reset');
        $queue->expects(self::never())->method('enqueue');

        $result = $this->processor($request, $applier, $queue)->process($request, new Patch(extraProperties: ['emagma_transition' => 'confirm']));

        self::assertSame($request, $result);
        self::assertSame(['begin', 'refresh-locked', 'apply', 'flush', 'commit', 'drain'], $this->calls);
    }

    public function testARefusedTransitionRollsBackAndForgetsQueuedEmails(): void
    {
        $request = new WithdrawalRequest();

        $applier = $this->createMock(WithdrawalRequestTransitionApplierInterface::class);
        $applier->method('apply')->willThrowException(new StateMachineExecutionException('nope'));

        $queue = $this->failureQueue();

        try {
            $this->processor($request, $applier, $queue)->process($request, new Patch(extraProperties: ['emagma_transition' => 'confirm']));
            self::fail('The refusal must propagate (mapped to 422).');
        } catch (StateMachineExecutionException) {
        }

        self::assertSame(['begin', 'refresh-locked', 'reset', 'rollback'], $this->calls);
    }

    public function testAFailedFlushRollsBackAndForgetsQueuedEmails(): void
    {
        $request = new WithdrawalRequest();
        $failure = new RuntimeException('flush failed');

        $applier = $this->createMock(WithdrawalRequestTransitionApplierInterface::class);
        $applier->expects(self::once())->method('apply')->willReturnCallback(function (): void { $this->calls[] = 'apply'; });

        try {
            $this->processor($request, $applier, $this->failureQueue(), flushFailure: $failure)
                ->process($request, new Patch(extraProperties: ['emagma_transition' => 'confirm']))
            ;
            self::fail('The flush failure must propagate.');
        } catch (RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertSame(['begin', 'refresh-locked', 'apply', 'flush', 'reset', 'rollback'], $this->calls);
    }

    public function testAFailedRollbackNeitherSkipsTheResetNorMasksTheOriginalFailure(): void
    {
        $request = new WithdrawalRequest();
        $failure = new StateMachineExecutionException('nope');

        $applier = $this->createMock(WithdrawalRequestTransitionApplierInterface::class);
        $applier->method('apply')->willThrowException($failure);

        try {
            $this->processor($request, $applier, $this->failureQueue(), rollBackFailure: new RuntimeException('connection lost'))
                ->process($request, new Patch(extraProperties: ['emagma_transition' => 'confirm']))
            ;
            self::fail('The original failure must propagate.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertSame(['begin', 'refresh-locked', 'reset', 'rollback'], $this->calls);
    }

    public function testAnOperationWithoutTransitionIsAConfigurationError(): void
    {
        $this->expectException(LogicException::class);

        (new WithdrawalRequestTransitionProcessor(
            $this->createMock(ManagerRegistry::class),
            $this->createMock(WithdrawalRequestTransitionApplierInterface::class),
            $this->createMock(PendingConfirmationQueueInterface::class),
        ))->process(new WithdrawalRequest(), new Patch());
    }

    private function failureQueue(): PendingConfirmationQueueInterface
    {
        $queue = $this->createMock(PendingConfirmationQueueInterface::class);
        $queue->expects(self::never())->method('drain');
        $queue->expects(self::never())->method('enqueue');
        $queue->expects(self::once())->method('reset')->willReturnCallback(function (): void { $this->calls[] = 'reset'; });

        return $queue;
    }

    private function processor(
        WithdrawalRequest $request,
        WithdrawalRequestTransitionApplierInterface $applier,
        PendingConfirmationQueueInterface $queue,
        ?Throwable $flushFailure = null,
        ?Throwable $rollBackFailure = null,
    ): WithdrawalRequestTransitionProcessor {
        $connection = $this->createMock(Connection::class);
        $connection->method('beginTransaction')->willReturnCallback(function (): void { $this->calls[] = 'begin'; });
        $connection->method('commit')->willReturnCallback(function (): void { $this->calls[] = 'commit'; });
        $connection->expects(self::atMost(1))->method('rollBack')->willReturnCallback(function () use ($rollBackFailure): void {
            $this->calls[] = 'rollback';
            if (null !== $rollBackFailure) {
                throw $rollBackFailure;
            }
        });
        $connection->method('isTransactionActive')->willReturnCallback(fn (): bool => !\in_array('commit', $this->calls, true) && !\in_array('rollback', $this->calls, true));

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getConnection')->willReturn($connection);
        $manager->method('refresh')->with($request, LockMode::PESSIMISTIC_WRITE)->willReturnCallback(function (): void { $this->calls[] = 'refresh-locked'; });
        $manager->method('flush')->willReturnCallback(function () use ($flushFailure): void {
            $this->calls[] = 'flush';
            if (null !== $flushFailure) {
                throw $flushFailure;
            }
        });

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(WithdrawalRequest::class)->willReturn($manager);

        return new WithdrawalRequestTransitionProcessor($registry, $applier, $queue);
    }
}
