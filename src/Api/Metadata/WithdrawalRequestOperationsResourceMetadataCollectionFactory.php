<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Api\Metadata;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;

/** @internal */
final readonly class WithdrawalRequestOperationsResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    private const ADMIN_PREFIX = 'emagma_sylius_order_withdrawal_api_admin_';

    private const SHOP_PREFIX = 'emagma_sylius_order_withdrawal_api_shop_';

    /** @param class-string $modelClass */
    public function __construct(
        private ResourceMetadataCollectionFactoryInterface $decorated,
        private string $modelClass,
        private bool $admin,
        private bool $shop,
    ) {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = $this->decorated->create($resourceClass);

        if (!is_a($resourceClass, $this->modelClass, true)) {
            return $collection;
        }

        $removedPrefixes = [];
        if (!$this->admin) {
            $removedPrefixes[] = self::ADMIN_PREFIX;
        }
        if (!$this->shop) {
            $removedPrefixes[] = self::SHOP_PREFIX;
        }

        $resources = [];
        foreach ($collection as $resource) {
            $resource = $resource->withGraphQlOperations([]);
            $operations = $resource->getOperations();
            if (null === $operations) {
                $resources[] = $resource;

                continue;
            }

            $kept = clone $operations;
            foreach ($operations as $name => $operation) {
                foreach ($removedPrefixes as $prefix) {
                    if (str_starts_with((string) $name, $prefix)) {
                        $kept->remove((string) $name);

                        break;
                    }
                }
            }

            if (0 === \count($kept)) {
                continue;
            }

            $resources[] = $resource->withOperations($kept);
        }

        return new ResourceMetadataCollection($resourceClass, $resources);
    }
}
