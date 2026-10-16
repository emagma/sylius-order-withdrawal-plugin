<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\Api\Metadata;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\DeleteMutation;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Emagma\SyliusOrderWithdrawalPlugin\Api\Metadata\WithdrawalRequestOperationsResourceMetadataCollectionFactory;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Emagma\SyliusOrderWithdrawalPlugin\TestApplication\Entity\WithdrawalRequest as ExtendedWithdrawalRequest;

final class WithdrawalRequestOperationsResourceMetadataCollectionFactoryTest extends TestCase
{
    private const ADMIN_OPERATIONS = [
        'emagma_sylius_order_withdrawal_api_admin_withdrawal_request_get_collection',
        'emagma_sylius_order_withdrawal_api_admin_withdrawal_request_get',
        'emagma_sylius_order_withdrawal_api_admin_withdrawal_request_patch',
    ];

    private const SHOP_OPERATIONS = ['emagma_sylius_order_withdrawal_api_shop_withdrawal_request_post'];

    public function testAdminOnlyKeepsTheAdminResourceAndDropsTheEmptiedShopResource(): void
    {
        $collection = $this->factory(WithdrawalRequest::class, admin: true, shop: false)->create(WithdrawalRequest::class);

        self::assertSame([self::ADMIN_OPERATIONS], $this->operationNames($collection));
    }

    public function testShopOnlyKeepsTheShopOperationOnly(): void
    {
        $collection = $this->factory(WithdrawalRequest::class, admin: false, shop: true)->create(WithdrawalRequest::class);

        self::assertSame([self::SHOP_OPERATIONS], $this->operationNames($collection));
    }

    public function testBothSwitchesKeepEverything(): void
    {
        $collection = $this->factory(WithdrawalRequest::class, admin: true, shop: true)->create(WithdrawalRequest::class);

        self::assertSame([self::ADMIN_OPERATIONS, self::SHOP_OPERATIONS], $this->operationNames($collection));
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function switches(): iterable
    {
        yield 'admin only' => [true, false];
        yield 'shop only' => [false, true];
        yield 'both' => [true, true];
    }

    #[DataProvider('switches')]
    public function testGraphQlOperationsAreStrippedWhateverTheSwitches(bool $admin, bool $shop): void
    {
        $inner = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $inner->method('create')->willReturn(new ResourceMetadataCollection(WithdrawalRequest::class, [
            (new ApiResource(class: WithdrawalRequest::class))
                ->withOperations(new Operations([self::ADMIN_OPERATIONS[1] => new Get(name: self::ADMIN_OPERATIONS[1])]))
                ->withGraphQlOperations(['item_query' => new Query(name: 'item_query'), 'delete' => new DeleteMutation(name: 'delete')]),
            (new ApiResource(class: WithdrawalRequest::class))
                ->withOperations(new Operations([self::SHOP_OPERATIONS[0] => new Post(name: self::SHOP_OPERATIONS[0])]))
                ->withGraphQlOperations(['create' => new Mutation(name: 'create')]),
        ]));

        $collection = (new WithdrawalRequestOperationsResourceMetadataCollectionFactory($inner, WithdrawalRequest::class, $admin, $shop))
            ->create(WithdrawalRequest::class)
        ;

        self::assertCount((int) $admin + (int) $shop, $collection);
        foreach ($collection as $resource) {
            self::assertSame([], $resource->getGraphQlOperations());
        }
    }

    public function testOperationsWithoutThePrefixesAreKeptInAFilteredResource(): void
    {
        $inner = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $inner->method('create')->willReturn(new ResourceMetadataCollection(WithdrawalRequest::class, [
            (new ApiResource())->withOperations(new Operations([
                'emagma_sylius_order_withdrawal_api_shop_withdrawal_request_post' => new Post(name: 'emagma_sylius_order_withdrawal_api_shop_withdrawal_request_post'),
                'app_withdrawal_request_get' => new Get(name: 'app_withdrawal_request_get'),
            ])),
        ]));

        $factory = new WithdrawalRequestOperationsResourceMetadataCollectionFactory($inner, WithdrawalRequest::class, true, false);

        self::assertSame([['app_withdrawal_request_get']], $this->operationNames($factory->create(WithdrawalRequest::class)));
    }

    public function testAnotherResourceClassIsUntouched(): void
    {
        $collection = $this->collection(stdClass::class);
        $inner = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $inner->method('create')->with(stdClass::class)->willReturn($collection);

        $factory = new WithdrawalRequestOperationsResourceMetadataCollectionFactory($inner, WithdrawalRequest::class, false, false);

        self::assertSame($collection, $factory->create(stdClass::class));
    }

    public function testAnExtendedEntityIsFilteredToo(): void
    {
        $collection = $this->factory(ExtendedWithdrawalRequest::class, admin: false, shop: true, modelClass: WithdrawalRequest::class)
            ->create(ExtendedWithdrawalRequest::class)
        ;

        self::assertSame([self::SHOP_OPERATIONS], $this->operationNames($collection));
        self::assertSame(ExtendedWithdrawalRequest::class, (fn (): string => $this->resourceClass)->call($collection));
    }

    /**
     * @param class-string $resourceClass
     * @param class-string|null $modelClass
     */
    private function factory(string $resourceClass, bool $admin, bool $shop, ?string $modelClass = null): WithdrawalRequestOperationsResourceMetadataCollectionFactory
    {
        $inner = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $inner->method('create')->with($resourceClass)->willReturn($this->collection($resourceClass));

        return new WithdrawalRequestOperationsResourceMetadataCollectionFactory($inner, $modelClass ?? $resourceClass, $admin, $shop);
    }

    /** @param class-string $resourceClass */
    private function collection(string $resourceClass): ResourceMetadataCollection
    {
        $admin = [];
        foreach (self::ADMIN_OPERATIONS as $name) {
            $admin[$name] = match (true) {
                str_ends_with($name, '_get_collection') => new GetCollection(name: $name),
                str_ends_with($name, '_patch') => new Patch(name: $name),
                default => new Get(name: $name),
            };
        }

        return new ResourceMetadataCollection($resourceClass, [
            (new ApiResource(class: $resourceClass))->withOperations(new Operations($admin)),
            (new ApiResource(class: $resourceClass))->withOperations(new Operations([
                self::SHOP_OPERATIONS[0] => new Post(name: self::SHOP_OPERATIONS[0]),
            ])),
        ]);
    }

    /** @return list<list<string>> */
    private function operationNames(ResourceMetadataCollection $collection): array
    {
        $names = [];
        foreach ($collection as $resource) {
            $names[] = array_map(strval(...), array_keys(iterator_to_array($resource->getOperations() ?? new Operations())));
        }

        return $names;
    }
}
