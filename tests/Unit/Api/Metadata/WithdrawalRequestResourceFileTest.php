<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\Api\Metadata;

use ApiPlatform\Metadata\Extractor\YamlResourceExtractor;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Resource\Factory\ExtractorResourceMetadataCollectionFactory;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use Emagma\SyliusOrderWithdrawalPlugin\Api\Metadata\WithdrawalRequestOperationsResourceMetadataCollectionFactory;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Yaml\Yaml;

final class WithdrawalRequestResourceFileTest extends TestCase
{
    private const FILE = __DIR__ . '/../../../../config/api_platform/WithdrawalRequest.yaml';

    private const CLASS_PARAMETER = 'emagma_sylius_order_withdrawal.model.withdrawal_request.class';

    private const ADMIN_PREFIX = 'emagma_sylius_order_withdrawal_api_admin_';

    private const SHOP_PREFIX = 'emagma_sylius_order_withdrawal_api_shop_';

    private const OPERATION_ID_MAX_LENGTH = 64;

    private const ITEM_OPERATION = 'emagma_sylius_order_withdrawal_api_admin_withdrawal_request_get';

    private const TRANSITION_OPERATIONS = [
        'emagma_sylius_order_withdrawal_api_admin_withdrawal_request_patch_start' => 'start',
        'emagma_sylius_order_withdrawal_api_admin_withdrawal_request_patch_confirm' => 'confirm',
        'emagma_sylius_order_withdrawal_api_admin_withdrawal_request_patch_reject' => 'reject',
    ];

    private const OPERATION_IDS = [
        'emagma_sylius_order_withdrawal_api_admin_withdrawal_request_get_collection' => 'listWithdrawalRequests',
        'emagma_sylius_order_withdrawal_api_admin_withdrawal_request_get' => 'getWithdrawalRequest',
        'emagma_sylius_order_withdrawal_api_admin_withdrawal_request_post' => 'createWithdrawalRequest',
        'emagma_sylius_order_withdrawal_api_admin_withdrawal_request_patch' => 'updateWithdrawalRequest',
        'emagma_sylius_order_withdrawal_api_admin_withdrawal_request_patch_start' => 'startWithdrawalRequest',
        'emagma_sylius_order_withdrawal_api_admin_withdrawal_request_patch_confirm' => 'confirmWithdrawalRequest',
        'emagma_sylius_order_withdrawal_api_admin_withdrawal_request_patch_reject' => 'rejectWithdrawalRequest',
        'emagma_sylius_order_withdrawal_api_shop_withdrawal_request_post' => 'submitWithdrawalRequest',
    ];

    public function testTheFileDeclaresExactlyTwoResourcesForTheModelClass(): void
    {
        $resources = $this->parse()['resources'];

        self::assertSame(['%' . self::CLASS_PARAMETER . '%'], array_keys($resources));
        self::assertCount(2, $this->entries());
        self::assertTrue(array_is_list($this->entries()));
    }

    public function testEveryAdminOperationHasTheAdminPrefixAndLeavesAccessToTheFirewall(): void
    {
        $operations = $this->entries()[0]['operations'];

        self::assertCount(7, $operations);
        foreach ($operations as $name => $operation) {
            self::assertStringStartsWith(self::ADMIN_PREFIX, (string) $name);
            self::assertArrayNotHasKey('security', $operation, \sprintf('Operation "%s": admin access belongs to the Sylius api_admin firewall.', $name));
        }
    }

    public function testTheShopOperationHasTheShopPrefix(): void
    {
        $operations = $this->entries()[1]['operations'];

        self::assertCount(1, $operations);
        foreach ($operations as $name => $operation) {
            self::assertStringStartsWith(self::SHOP_PREFIX, (string) $name);
            self::assertArrayNotHasKey('security', $operation);
        }
    }

    public function testEveryOperationOfTheFileBelongsToExactlyOneSwitch(): void
    {
        $count = 0;
        foreach ($this->entries() as $index => $entry) {
            self::assertArrayHasKey('operations', $entry, \sprintf('Resource entry %d must list its operations explicitly.', $index));
            foreach (array_keys($entry['operations']) as $name) {
                $name = (string) $name;
                self::assertTrue(
                    str_starts_with($name, self::ADMIN_PREFIX) xor str_starts_with($name, self::SHOP_PREFIX),
                    \sprintf('Operation "%s" must start with "%s" or "%s".', $name, self::ADMIN_PREFIX, self::SHOP_PREFIX),
                );
                ++$count;
            }
            self::assertArrayNotHasKey('graphQlOperations', $entry);
        }

        self::assertSame(8, $count);
    }

    public function testEveryOperationHasAShortUniqueOperationId(): void
    {
        $operationIds = [];
        foreach ($this->entries() as $entry) {
            foreach ($entry['operations'] as $name => $operation) {
                $operationId = $operation['openapi']['operationId'] ?? null;
                self::assertIsString($operationId, \sprintf('Operation "%s" must set openapi.operationId.', $name));
                self::assertLessThanOrEqual(self::OPERATION_ID_MAX_LENGTH, \strlen($operationId), \sprintf('The operationId of "%s" exceeds the MCP tool-name limit.', $name));
                self::assertMatchesRegularExpression('/^[a-zA-Z0-9_-]+$/', $operationId);
                $operationIds[(string) $name] = $operationId;
            }
        }

        self::assertSame(self::OPERATION_IDS, $operationIds);
        self::assertSame(array_values(array_unique($operationIds)), array_values($operationIds));
    }

    public function testTheRealExtractorReadsTheOperationIds(): void
    {
        $operationIds = [];
        foreach ($this->chain(true, true)->create(WithdrawalRequest::class) as $resource) {
            foreach ($resource->getOperations() ?? [] as $name => $operation) {
                $operationIds[$name] = $operation->getOpenapi() instanceof OpenApiOperation ? $operation->getOpenapi()->getOperationId() : null;
            }
        }

        self::assertSame(self::OPERATION_IDS, $operationIds);
    }

    public function testTheTransitionsNormalizeTheRequestWithTheCanonicalItemIri(): void
    {
        $operations = [];
        foreach ($this->chain(true, false)->create(WithdrawalRequest::class) as $resource) {
            foreach ($resource->getOperations() ?? [] as $name => $operation) {
                $operations[$name] = $operation;
            }
        }

        $item = $operations[self::ITEM_OPERATION] ?? null;
        self::assertInstanceOf(Get::class, $item);
        self::assertSame('/admin/withdrawal-requests/{id}', $item->getUriTemplate());

        foreach (array_keys(self::TRANSITION_OPERATIONS) as $name) {
            self::assertArrayHasKey($name, $operations);
            self::assertSame(
                $item->getUriTemplate(),
                $operations[$name]->getNormalizationContext()['item_uri_template'] ?? null,
                \sprintf('Operation "%s" must normalize the request with the item IRI.', $name),
            );
        }

        foreach ($operations as $name => $operation) {
            if (!\array_key_exists($name, self::TRANSITION_OPERATIONS)) {
                self::assertArrayNotHasKey('item_uri_template', $operation->getNormalizationContext() ?? [], \sprintf('Operation "%s" already returns its canonical IRI.', $name));
            }
        }
    }

    public function testEachTransitionOperationAppliesItsWorkflowTransition(): void
    {
        $transitions = [];
        foreach ($this->chain(true, true)->create(WithdrawalRequest::class) as $resource) {
            foreach ($resource->getOperations() ?? [] as $name => $operation) {
                if (null !== ($transition = $operation->getExtraProperties()['emagma_transition'] ?? null)) {
                    $transitions[$name] = $transition;
                }
            }
        }

        self::assertSame(self::TRANSITION_OPERATIONS, $transitions);
    }

    public function testBothEntriesShareTheSameResourceLevelConfiguration(): void
    {
        [$admin, $shop] = $this->entries();
        unset($admin['operations'], $shop['operations']);

        self::assertNotEmpty($admin);
        self::assertSame($admin, $shop);
    }

    public function testWithoutTheFilterTheExtractorWouldAddTheDefaultGraphQlOperations(): void
    {
        $container = new Container(new ParameterBag([self::CLASS_PARAMETER => WithdrawalRequest::class]));
        $extractor = new ExtractorResourceMetadataCollectionFactory(new YamlResourceExtractor([self::FILE], $container), graphQlEnabled: true);

        foreach ($extractor->create(WithdrawalRequest::class) as $resource) {
            self::assertNotEmpty($resource->getGraphQlOperations());
        }
    }

    /** @param list<string> $expected */
    #[DataProvider('switches')]
    public function testTheRealExtractorChainExposesOnlyTheRestOperationsOfTheSwitchesThatAreOn(bool $admin, bool $shop, array $expected): void
    {
        $names = [];
        foreach ($this->chain($admin, $shop)->create(WithdrawalRequest::class) as $resource) {
            foreach ($resource->getOperations() ?? [] as $name => $operation) {
                $names[] = $name;
            }
            self::assertSame([], $resource->getGraphQlOperations(), 'No GraphQL operation may be exposed.');
        }

        self::assertSame($expected, $names);
    }

    /** @return iterable<string, array{bool, bool, list<string>}> */
    public static function switches(): iterable
    {
        $adminOperations = [
            'emagma_sylius_order_withdrawal_api_admin_withdrawal_request_get_collection',
            'emagma_sylius_order_withdrawal_api_admin_withdrawal_request_get',
            'emagma_sylius_order_withdrawal_api_admin_withdrawal_request_post',
            'emagma_sylius_order_withdrawal_api_admin_withdrawal_request_patch',
            'emagma_sylius_order_withdrawal_api_admin_withdrawal_request_patch_start',
            'emagma_sylius_order_withdrawal_api_admin_withdrawal_request_patch_confirm',
            'emagma_sylius_order_withdrawal_api_admin_withdrawal_request_patch_reject',
        ];
        $shopOperations = ['emagma_sylius_order_withdrawal_api_shop_withdrawal_request_post'];

        yield 'admin only' => [true, false, $adminOperations];
        yield 'shop only' => [false, true, $shopOperations];
        yield 'both' => [true, true, [...$adminOperations, ...$shopOperations]];
    }

    private function chain(bool $admin, bool $shop): WithdrawalRequestOperationsResourceMetadataCollectionFactory
    {
        $container = new Container(new ParameterBag([self::CLASS_PARAMETER => WithdrawalRequest::class]));

        return new WithdrawalRequestOperationsResourceMetadataCollectionFactory(
            new ExtractorResourceMetadataCollectionFactory(new YamlResourceExtractor([self::FILE], $container), graphQlEnabled: true),
            WithdrawalRequest::class,
            $admin,
            $shop,
        );
    }

    /** @return array<string, mixed> */
    private function parse(): array
    {
        $data = Yaml::parseFile(self::FILE, Yaml::PARSE_CONSTANT);
        self::assertIsArray($data);

        return $data;
    }

    /** @return array<int, array<string, mixed>> */
    private function entries(): array
    {
        return $this->parse()['resources']['%' . self::CLASS_PARAMETER . '%'];
    }
}
