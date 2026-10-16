<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\Api\Metadata;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use Emagma\SyliusOrderWithdrawalPlugin\Api\Metadata\WithdrawalRequestOperationsResourceMetadataFactory;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Emagma\SyliusOrderWithdrawalPlugin\TestApplication\Entity\WithdrawalRequest as ExtendedWithdrawalRequest;

final class WithdrawalRequestOperationsResourceMetadataFactoryTest extends TestCase
{
    private const ADMIN_COLLECTION_OPERATIONS = ['admin_get', 'admin_post'];

    private const SHOP_COLLECTION_OPERATIONS = ['shop_post'];

    private const ITEM_OPERATIONS = ['admin_get', 'admin_patch', 'admin_start', 'admin_confirm', 'admin_reject'];

    /**
     * @param list<string> $collection
     * @param list<string> $item
     */
    #[DataProvider('switches')]
    public function testItKeepsOnlyTheOperationsOfTheSwitchesThatAreOn(bool $admin, bool $shop, array $collection, array $item): void
    {
        $metadata = $this->factory(WithdrawalRequest::class, $admin, $shop)->create(WithdrawalRequest::class);

        self::assertSame($collection, array_keys($metadata->getCollectionOperations() ?? []));
        self::assertSame($item, array_keys($metadata->getItemOperations() ?? []));
    }

    /** @return iterable<string, array{bool, bool, list<string>, list<string>}> */
    public static function switches(): iterable
    {
        yield 'admin only' => [true, false, self::ADMIN_COLLECTION_OPERATIONS, self::ITEM_OPERATIONS];
        yield 'shop only' => [false, true, self::SHOP_COLLECTION_OPERATIONS, []];
        yield 'both' => [true, true, [...self::ADMIN_COLLECTION_OPERATIONS, ...self::SHOP_COLLECTION_OPERATIONS], self::ITEM_OPERATIONS];
        yield 'neither' => [false, false, [], []];
    }

    #[DataProvider('switches')]
    public function testGraphQlIsDisabledWhateverTheSwitches(bool $admin, bool $shop): void
    {
        $metadata = $this->factory(WithdrawalRequest::class, $admin, $shop)->create(WithdrawalRequest::class);

        self::assertSame([], $metadata->getGraphql());
    }

    public function testItKeepsTheResourceLevelMetadata(): void
    {
        $metadata = $this->factory(WithdrawalRequest::class, false, true)->create(WithdrawalRequest::class);

        self::assertSame('WithdrawalRequest', $metadata->getShortName());
        self::assertSame(['validation_groups' => ['sylius']], $metadata->getAttributes());
    }

    public function testOperationsWithoutThePrefixesAreKept(): void
    {
        $inner = $this->createMock(ResourceMetadataFactoryInterface::class);
        $inner->method('create')->willReturn(new ResourceMetadata(
            'WithdrawalRequest',
            collectionOperations: ['shop_post' => ['method' => 'POST'], 'app_get' => ['method' => 'GET']],
        ));

        $metadata = (new WithdrawalRequestOperationsResourceMetadataFactory($inner, WithdrawalRequest::class, true, false))
            ->create(WithdrawalRequest::class)
        ;

        self::assertSame(['app_get'], array_keys($metadata->getCollectionOperations() ?? []));
    }

    public function testUndefinedOperationsStayUndefinedWhenNothingIsRemoved(): void
    {
        $inner = $this->createMock(ResourceMetadataFactoryInterface::class);
        $inner->method('create')->willReturn(new ResourceMetadata('WithdrawalRequest'));

        $metadata = (new WithdrawalRequestOperationsResourceMetadataFactory($inner, WithdrawalRequest::class, true, true))
            ->create(WithdrawalRequest::class)
        ;

        self::assertNull($metadata->getCollectionOperations());
        self::assertNull($metadata->getItemOperations());
        self::assertSame([], $metadata->getGraphql());
    }

    public function testAnotherResourceClassIsUntouched(): void
    {
        $metadata = $this->metadata();
        $inner = $this->createMock(ResourceMetadataFactoryInterface::class);
        $inner->method('create')->with(stdClass::class)->willReturn($metadata);

        $factory = new WithdrawalRequestOperationsResourceMetadataFactory($inner, WithdrawalRequest::class, false, false);

        self::assertSame($metadata, $factory->create(stdClass::class));
    }

    public function testAnExtendedEntityIsFilteredToo(): void
    {
        $metadata = $this->factory(ExtendedWithdrawalRequest::class, false, true, WithdrawalRequest::class)
            ->create(ExtendedWithdrawalRequest::class)
        ;

        self::assertSame(self::SHOP_COLLECTION_OPERATIONS, array_keys($metadata->getCollectionOperations() ?? []));
        self::assertSame([], $metadata->getItemOperations());
    }

    /**
     * @param class-string      $resourceClass
     * @param class-string|null $modelClass
     */
    private function factory(string $resourceClass, bool $admin, bool $shop, ?string $modelClass = null): WithdrawalRequestOperationsResourceMetadataFactory
    {
        $inner = $this->createMock(ResourceMetadataFactoryInterface::class);
        $inner->method('create')->with($resourceClass)->willReturn($this->metadata());

        return new WithdrawalRequestOperationsResourceMetadataFactory($inner, $modelClass ?? $resourceClass, $admin, $shop);
    }

    private function metadata(): ResourceMetadata
    {
        return new ResourceMetadata(
            'WithdrawalRequest',
            null,
            null,
            [
                'admin_get' => ['method' => 'GET'],
                'admin_patch' => ['method' => 'PATCH'],
                'admin_start' => ['method' => 'PATCH'],
                'admin_confirm' => ['method' => 'PATCH'],
                'admin_reject' => ['method' => 'PATCH'],
            ],
            [
                'admin_get' => ['method' => 'GET'],
                'admin_post' => ['method' => 'POST'],
                'shop_post' => ['method' => 'POST'],
            ],
            ['validation_groups' => ['sylius']],
            null,
            ['item_query' => [], 'create' => []],
        );
    }
}
