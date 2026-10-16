<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Api\DataPersister;

use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Submission\WithdrawalRequestSubmissionHandlerInterface;

/** @internal */
final class WithdrawalRequestDataPersister implements ContextAwareDataPersisterInterface
{
    private const CREATE_OPERATIONS = ['admin_post', 'shop_post'];

    public function __construct(
        private readonly ContextAwareDataPersisterInterface $decoratedDataPersister,
        private readonly WithdrawalRequestSubmissionHandlerInterface $submissionHandler,
    ) {
    }

    /**
     * @param mixed                $data
     * @param array<string, mixed> $context
     */
    public function supports($data, array $context = []): bool
    {
        return $data instanceof WithdrawalRequest &&
            \in_array($context['collection_operation_name'] ?? null, self::CREATE_OPERATIONS, true);
    }

    /**
     * @param mixed                $data
     * @param array<string, mixed> $context
     *
     * @return mixed
     */
    public function persist($data, array $context = [])
    {
        if (!$data instanceof WithdrawalRequest) {
            return $this->decoratedDataPersister->persist($data, $context);
        }

        $this->submissionHandler->prepare($data);
        $result = $this->decoratedDataPersister->persist($data, $context);
        $this->submissionHandler->complete($data);

        return $result;
    }

    /**
     * @param mixed                $data
     * @param array<string, mixed> $context
     *
     * @return mixed
     */
    public function remove($data, array $context = [])
    {
        return $this->decoratedDataPersister->remove($data, $context);
    }
}
