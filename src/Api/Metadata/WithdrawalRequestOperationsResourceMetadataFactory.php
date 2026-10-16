<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Api\Metadata;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;

/** @internal */
final class WithdrawalRequestOperationsResourceMetadataFactory implements ResourceMetadataFactoryInterface
{
    private const ADMIN_PREFIX = 'admin_';

    private const SHOP_PREFIX = 'shop_';

    /** @param class-string $modelClass */
    public function __construct(
        private readonly ResourceMetadataFactoryInterface $decorated,
        private readonly string $modelClass,
        private readonly bool $admin,
        private readonly bool $shop,
    ) {
    }

    public function create(string $resourceClass): ResourceMetadata
    {
        $resourceMetadata = $this->decorated->create($resourceClass);

        if (!is_a($resourceClass, $this->modelClass, true)) {
            return $resourceMetadata;
        }

        $removedPrefixes = [];
        if (!$this->admin) {
            $removedPrefixes[] = self::ADMIN_PREFIX;
            $resourceMetadata = $resourceMetadata->withItemOperations([]);
        }
        if (!$this->shop) {
            $removedPrefixes[] = self::SHOP_PREFIX;
        }

        $collectionOperations = $resourceMetadata->getCollectionOperations();
        if (null !== $collectionOperations && [] !== $removedPrefixes) {
            $resourceMetadata = $resourceMetadata->withCollectionOperations(
                array_filter(
                    $collectionOperations,
                    static function (int|string $name) use ($removedPrefixes): bool {
                        foreach ($removedPrefixes as $prefix) {
                            if (str_starts_with((string) $name, $prefix)) {
                                return false;
                            }
                        }

                        return true;
                    },
                    \ARRAY_FILTER_USE_KEY,
                ),
            );
        }

        return $resourceMetadata->withGraphql([]);
    }
}
