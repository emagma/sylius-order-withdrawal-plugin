<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\Api\Serializer;

use Emagma\SyliusOrderWithdrawalPlugin\Api\Serializer\AvailableTransitionsNormalizer;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\StateMachine\WithdrawalRequestTransitionApplierInterface;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use UnexpectedValueException;

final class AvailableTransitionsNormalizerTest extends TestCase
{
    public function testItAddsTheAvailableTransitionsOnAdminShow(): void
    {
        $request = new WithdrawalRequest();
        $context = ['groups' => ['emagma:admin:withdrawal_request:show']];

        $inner = $this->createMock(NormalizerInterface::class);
        $inner->expects(self::once())->method('normalize')
            ->with($request, 'jsonld', self::callback(static fn (array $ctx): bool => true === ($ctx[AvailableTransitionsNormalizer::ALREADY_CALLED] ?? null)))
            ->willReturn(['state' => 'new'])
        ;

        $applier = $this->createMock(WithdrawalRequestTransitionApplierInterface::class);
        $applier->method('getAvailableTransitions')->with($request)->willReturn(['start', 'confirm', 'reject']);

        $normalizer = new AvailableTransitionsNormalizer($applier);
        $normalizer->setNormalizer($inner);

        self::assertTrue($normalizer->supportsNormalization($request, 'jsonld', $context));
        self::assertSame(
            ['state' => 'new', 'availableTransitions' => ['start', 'confirm', 'reject']],
            $normalizer->normalize($request, 'jsonld', $context),
        );
    }

    public function testItRefusesAnInnerNormalizerThatDoesNotReturnAnArray(): void
    {
        $inner = $this->createMock(NormalizerInterface::class);
        $inner->method('normalize')->willReturn('not-an-array');

        $normalizer = new AvailableTransitionsNormalizer($this->createMock(WithdrawalRequestTransitionApplierInterface::class));
        $normalizer->setNormalizer($inner);

        $this->expectException(UnexpectedValueException::class);

        $normalizer->normalize(new WithdrawalRequest(), 'jsonld', ['groups' => ['emagma:admin:withdrawal_request:show']]);
    }

    public function testItIgnoresOtherGroupsAndAlreadyNormalizedRequests(): void
    {
        $normalizer = new AvailableTransitionsNormalizer($this->createMock(WithdrawalRequestTransitionApplierInterface::class));
        $request = new WithdrawalRequest();

        self::assertFalse($normalizer->supportsNormalization($request, 'jsonld', ['groups' => ['emagma:admin:withdrawal_request:index']]));
        self::assertFalse($normalizer->supportsNormalization($request, 'jsonld', ['groups' => ['emagma:shop:withdrawal_request:create']]));
        self::assertFalse($normalizer->supportsNormalization($request, 'jsonld', [
            'groups' => ['emagma:admin:withdrawal_request:show'],
            AvailableTransitionsNormalizer::ALREADY_CALLED => true,
        ]));
        self::assertFalse($normalizer->supportsNormalization(new stdClass(), 'jsonld', ['groups' => ['emagma:admin:withdrawal_request:show']]));
    }
}
