<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\Api\DataPersister;

use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Api\DataPersister\WithdrawalRequestDataPersister;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Submission\WithdrawalRequestSubmissionHandlerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

final class WithdrawalRequestDataPersisterTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function createOperations(): iterable
    {
        yield 'admin' => ['admin_post'];
        yield 'shop' => ['shop_post'];
    }

    /** @dataProvider createOperations */
    public function testItPreparesThenPersistsThenCompletes(string $operation): void
    {
        $request = new WithdrawalRequest();
        $calls = [];

        $handler = $this->createMock(WithdrawalRequestSubmissionHandlerInterface::class);
        $handler->method('prepare')->willReturnCallback(static function () use (&$calls): void { $calls[] = 'prepare'; });
        $handler->method('complete')->willReturnCallback(static function () use (&$calls): void { $calls[] = 'complete'; });

        $decorated = $this->createMock(ContextAwareDataPersisterInterface::class);
        $decorated->method('persist')->willReturnCallback(static function (mixed $data) use (&$calls): mixed {
            $calls[] = 'persist';

            return $data;
        });

        $persister = new WithdrawalRequestDataPersister($decorated, $handler);
        $context = ['collection_operation_name' => $operation];

        self::assertTrue($persister->supports($request, $context));
        self::assertSame($request, $persister->persist($request, $context));
        self::assertSame(['prepare', 'persist', 'complete'], $calls);
    }

    public function testAFailedPersistDoesNotComplete(): void
    {
        $request = new WithdrawalRequest();
        $failure = new RuntimeException('persist failed');

        $handler = $this->createMock(WithdrawalRequestSubmissionHandlerInterface::class);
        $handler->expects(self::once())->method('prepare');
        $handler->expects(self::never())->method('complete');

        $decorated = $this->createMock(ContextAwareDataPersisterInterface::class);
        $decorated->method('persist')->willThrowException($failure);

        try {
            (new WithdrawalRequestDataPersister($decorated, $handler))->persist($request, ['collection_operation_name' => 'shop_post']);
            self::fail('The persist failure must propagate.');
        } catch (RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    /** @return iterable<string, array{object, array<string, mixed>}> */
    public static function unsupported(): iterable
    {
        yield 'patch' => [new WithdrawalRequest(), ['item_operation_name' => 'admin_patch']];
        yield 'transition' => [new WithdrawalRequest(), ['item_operation_name' => 'admin_confirm']];
        yield 'other resource' => [new stdClass(), ['collection_operation_name' => 'admin_post']];
        yield 'no operation' => [new WithdrawalRequest(), []];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @dataProvider unsupported
     */
    public function testItLeavesEverythingElseToTheNextPersister(object $data, array $context): void
    {
        $persister = new WithdrawalRequestDataPersister(
            $this->createMock(ContextAwareDataPersisterInterface::class),
            $this->createMock(WithdrawalRequestSubmissionHandlerInterface::class),
        );

        self::assertFalse($persister->supports($data, $context));
    }

    public function testPersistingAnythingElseIsPassedStraightThrough(): void
    {
        $data = new stdClass();
        $handler = $this->createMock(WithdrawalRequestSubmissionHandlerInterface::class);
        $handler->expects(self::never())->method('prepare');
        $handler->expects(self::never())->method('complete');

        $decorated = $this->createMock(ContextAwareDataPersisterInterface::class);
        $decorated->expects(self::once())->method('persist')->with($data)->willReturn($data);

        self::assertSame($data, (new WithdrawalRequestDataPersister($decorated, $handler))->persist($data));
    }

    public function testRemoveIsDelegated(): void
    {
        $request = new WithdrawalRequest();
        $decorated = $this->createMock(ContextAwareDataPersisterInterface::class);
        $decorated->expects(self::once())->method('remove')->with($request, ['x' => 1]);

        (new WithdrawalRequestDataPersister($decorated, $this->createMock(WithdrawalRequestSubmissionHandlerInterface::class)))->remove($request, ['x' => 1]);
    }
}
