<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\Api\State;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Api\State\WithdrawalRequestCreateProcessor;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Submission\WithdrawalRequestSubmissionHandlerInterface;
use PHPUnit\Framework\TestCase;
use stdClass;

final class WithdrawalRequestCreateProcessorTest extends TestCase
{
    public function testItPreparesThenPersistsThenCompletes(): void
    {
        $request = new WithdrawalRequest();
        $calls = [];

        $handler = $this->createMock(WithdrawalRequestSubmissionHandlerInterface::class);
        $handler->method('prepare')->willReturnCallback(static function () use (&$calls): void { $calls[] = 'prepare'; });
        $handler->method('complete')->willReturnCallback(static function () use (&$calls): void { $calls[] = 'complete'; });

        $persist = $this->createMock(ProcessorInterface::class);
        $persist->method('process')->willReturnCallback(static function (mixed $data) use (&$calls): mixed {
            $calls[] = 'persist';

            return $data;
        });

        $result = (new WithdrawalRequestCreateProcessor($persist, $handler))->process($request, new Post());

        self::assertSame($request, $result);
        self::assertSame(['prepare', 'persist', 'complete'], $calls);
    }

    public function testItPassesAnythingElseStraightToPersistence(): void
    {
        $data = new stdClass();
        $handler = $this->createMock(WithdrawalRequestSubmissionHandlerInterface::class);
        $handler->expects(self::never())->method('prepare');

        $persist = $this->createMock(ProcessorInterface::class);
        $persist->expects(self::once())->method('process')->willReturn($data);

        self::assertSame($data, (new WithdrawalRequestCreateProcessor($persist, $handler))->process($data, new Post()));
    }
}
