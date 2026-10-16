<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Api\Serializer;

use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\StateMachine\WithdrawalRequestTransitionApplierInterface;
use InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use UnexpectedValueException;

/** @internal */
final class AvailableTransitionsNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public const ALREADY_CALLED = 'emagma_sylius_order_withdrawal_available_transitions_normalizer_already_called';

    private const GROUP = 'emagma:admin:withdrawal_request:show';

    public function __construct(private readonly WithdrawalRequestTransitionApplierInterface $transitionApplier)
    {
    }

    /** @param array<string, mixed> $context */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (!$data instanceof WithdrawalRequest) {
            throw new InvalidArgumentException(sprintf('Expected a %s, got %s.', WithdrawalRequest::class, get_debug_type($data)));
        }

        $context[self::ALREADY_CALLED] = true;
        $normalized = $this->normalizer->normalize($data, $format, $context);
        if (!\is_array($normalized)) {
            throw new UnexpectedValueException(sprintf('Expected the inner normalizer to return an array, got %s.', get_debug_type($normalized)));
        }

        $normalized['availableTransitions'] = $this->transitionApplier->getAvailableTransitions($data);

        return $normalized;
    }

    /** @param array<string, mixed> $context */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof WithdrawalRequest &&
            !isset($context[self::ALREADY_CALLED]) &&
            \in_array(self::GROUP, (array) ($context['groups'] ?? []), true);
    }

    /** @return array<class-string|'*'|'object'|string, bool|null> */
    public function getSupportedTypes(?string $format): array
    {
        return [WithdrawalRequest::class => false];
    }
}
