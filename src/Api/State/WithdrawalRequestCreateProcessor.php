<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Submission\WithdrawalRequestSubmissionHandlerInterface;

/**
 * @internal
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
final readonly class WithdrawalRequestCreateProcessor implements ProcessorInterface
{
    /** @param ProcessorInterface<mixed, mixed> $persistProcessor */
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private WithdrawalRequestSubmissionHandlerInterface $submissionHandler,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof WithdrawalRequest) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $this->submissionHandler->prepare($data);
        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        $this->submissionHandler->complete($data);

        return $result;
    }
}
