<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\TestApplication\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest as BaseWithdrawalRequest;

#[ORM\Entity]
#[ORM\Table(name: 'emagma_order_withdrawal_request')]
class WithdrawalRequest extends BaseWithdrawalRequest
{
    #[ORM\Column(name: 'internal_note', type: Types::TEXT, nullable: true)]
    protected ?string $internalNote = null;

    public function getInternalNote(): ?string
    {
        return $this->internalNote;
    }

    public function setInternalNote(?string $internalNote): void
    {
        $this->internalNote = $internalNote;
    }
}
