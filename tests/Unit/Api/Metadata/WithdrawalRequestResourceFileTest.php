<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\Api\Metadata;

use ApiPlatform\Core\Metadata\Extractor\XmlExtractor;
use ApiPlatform\Core\Metadata\Resource\Factory\ExtractorResourceMetadataFactory;
use ApiPlatform\Core\Metadata\Resource\Factory\OperationResourceMetadataFactory;
use Emagma\SyliusOrderWithdrawalPlugin\Api\Metadata\WithdrawalRequestOperationsResourceMetadataFactory;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;
use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

final class WithdrawalRequestResourceFileTest extends TestCase
{
    private const FILE = __DIR__ . '/../../../../config/api_resources/WithdrawalRequest.xml';

    private const CLASS_PARAMETER = 'emagma_sylius_order_withdrawal.model.withdrawal_request.class';

    private const OPERATION_ID_MAX_LENGTH = 64;

    private const COLLECTION_OPERATIONS = ['admin_get', 'admin_post', 'shop_post'];

    private const ITEM_OPERATIONS = ['admin_get', 'admin_patch', 'admin_start', 'admin_confirm', 'admin_reject'];

    private const TRANSITION_OPERATIONS = [
        'admin_start' => 'emagma_sylius_order_withdrawal.api.controller.apply_transition::start',
        'admin_confirm' => 'emagma_sylius_order_withdrawal.api.controller.apply_transition::confirm',
        'admin_reject' => 'emagma_sylius_order_withdrawal.api.controller.apply_transition::reject',
    ];

    private const OPERATION_IDS = [
        'collection:admin_get' => 'listWithdrawalRequests',
        'collection:admin_post' => 'createWithdrawalRequest',
        'collection:shop_post' => 'submitWithdrawalRequest',
        'item:admin_get' => 'getWithdrawalRequest',
        'item:admin_patch' => 'updateWithdrawalRequest',
        'item:admin_start' => 'startWithdrawalRequest',
        'item:admin_confirm' => 'confirmWithdrawalRequest',
        'item:admin_reject' => 'rejectWithdrawalRequest',
    ];

    public function testTheFileIsValidAgainstTheLegacyMetadataSchema(): void
    {
        XmlUtils::loadFile(self::FILE, XmlExtractor::RESOURCE_SCHEMA);

        $this->addToAssertionCount(1);
    }

    public function testTheFileDeclaresOneResourceForTheModelClass(): void
    {
        $resources = $this->xml()->resource;

        self::assertCount(1, $resources);
        self::assertSame('%' . self::CLASS_PARAMETER . '%', (string) $resources[0]['class']);
        self::assertSame('WithdrawalRequest', (string) $resources[0]['shortName']);
    }

    public function testItDeclaresTheExpectedOperations(): void
    {
        self::assertSame(self::COLLECTION_OPERATIONS, array_keys($this->operations('collection')));
        self::assertSame(self::ITEM_OPERATIONS, array_keys($this->operations('item')));
    }

    public function testEveryOperationBelongsToExactlyOneSwitch(): void
    {
        foreach (['collection', 'item'] as $type) {
            foreach (array_keys($this->operations($type)) as $name) {
                self::assertTrue(
                    str_starts_with($name, 'admin_') xor str_starts_with($name, 'shop_'),
                    \sprintf('Operation "%s" must start with "admin_" or "shop_".', $name),
                );
            }
        }

        foreach (array_keys($this->operations('item')) as $name) {
            self::assertStringStartsWith('admin_', $name, 'Every item operation is dropped with the admin switch.');
        }
    }

    public function testNoForbiddenAttributeIsSet(): void
    {
        foreach (['security', 'route_name', 'item_uri_template'] as $attribute) {
            self::assertSame([], $this->xml()->xpath(\sprintf('//m:attribute[@name="%s"]', $attribute)), \sprintf('"%s" must not be set anywhere.', $attribute));
        }
    }

    public function testThereIsNoDeleteOrPutOperation(): void
    {
        foreach (['collection', 'item'] as $type) {
            foreach ($this->operations($type) as $name => $operation) {
                self::assertNotContains($this->attribute($operation, 'method'), ['DELETE', 'PUT'], \sprintf('Operation "%s".', $name));
            }
        }
    }

    public function testEveryOperationHasAShortUniqueOperationId(): void
    {
        $operationIds = [];
        foreach (['collection', 'item'] as $type) {
            foreach ($this->operations($type) as $name => $operation) {
                $operationId = $this->openApiContext($operation)['operationId'] ?? null;
                self::assertNotNull($operationId, \sprintf('Operation "%s" must set openapi_context.operationId.', $name));
                self::assertLessThanOrEqual(self::OPERATION_ID_MAX_LENGTH, \strlen($operationId));
                self::assertMatchesRegularExpression('/^[a-zA-Z0-9_-]+$/', $operationId);
                $operationIds[$type . ':' . $name] = $operationId;
            }
        }

        self::assertSame(self::OPERATION_IDS, $operationIds);
        self::assertSame(array_values(array_unique($operationIds)), array_values($operationIds));
    }

    public function testEachTransitionOperationCallsItsControllerMethod(): void
    {
        $controllers = [];
        foreach ($this->operations('item') as $name => $operation) {
            $controller = $this->attribute($operation, 'controller');
            if (null !== $controller) {
                $controllers[$name] = $controller;
                self::assertSame('false', $this->attribute($operation, 'input'));
                self::assertSame('false', $this->attribute($operation, 'validate'));
                self::assertSame('PATCH', $this->attribute($operation, 'method'));
            }
        }

        self::assertSame(self::TRANSITION_OPERATIONS, $controllers);
    }

    public function testTheRealExtractorReadsTheOperationIdsAndTheShopResponse(): void
    {
        $metadata = $this->chain(true, true)->create(WithdrawalRequest::class);

        $operationIds = [];
        foreach (['collection' => $metadata->getCollectionOperations(), 'item' => $metadata->getItemOperations()] as $type => $operations) {
            foreach ($operations ?? [] as $name => $operation) {
                $operationIds[$type . ':' . $name] = $operation['openapi_context']['operationId'] ?? null;
            }
        }

        self::assertSame(self::OPERATION_IDS, $operationIds);
        self::assertFalse($metadata->getCollectionOperationAttribute('shop_post', 'output'));
        self::assertSame(202, $metadata->getCollectionOperationAttribute('shop_post', 'status'));
        self::assertFalse($metadata->getItemOperationAttribute('admin_start', 'validate'));
        self::assertSame(['submittedAt' => 'DESC'], $metadata->getAttribute('order'));
        self::assertSame(100, $metadata->getCollectionOperationAttribute('admin_get', 'pagination_maximum_items_per_page'));
    }

    /**
     * @param list<string> $collection
     * @param list<string> $item
     */
    #[DataProvider('switches')]
    public function testTheRealExtractorChainExposesOnlyTheOperationsOfTheSwitchesThatAreOn(bool $admin, bool $shop, array $collection, array $item): void
    {
        $metadata = $this->chain($admin, $shop)->create(WithdrawalRequest::class);

        self::assertSame($collection, array_keys($metadata->getCollectionOperations() ?? []));
        self::assertSame($item, array_keys($metadata->getItemOperations() ?? []));
        self::assertSame([], $metadata->getGraphql(), 'No GraphQL operation may be exposed.');
    }

    /** @return iterable<string, array{bool, bool, list<string>, list<string>}> */
    public static function switches(): iterable
    {
        yield 'admin only' => [true, false, ['admin_get', 'admin_post'], self::ITEM_OPERATIONS];
        yield 'shop only' => [false, true, ['shop_post'], []];
        yield 'both' => [true, true, self::COLLECTION_OPERATIONS, self::ITEM_OPERATIONS];
        yield 'neither' => [false, false, [], []];
    }

    public function testWithoutTheFilterTheExtractorLeavesGraphQlUndecided(): void
    {
        self::assertNull($this->extractorFactory()->create(WithdrawalRequest::class)->getGraphql());
    }

    private function chain(bool $admin, bool $shop): WithdrawalRequestOperationsResourceMetadataFactory
    {
        return new WithdrawalRequestOperationsResourceMetadataFactory(
            new OperationResourceMetadataFactory($this->extractorFactory(), ['json' => ['application/merge-patch+json']]),
            WithdrawalRequest::class,
            $admin,
            $shop,
        );
    }

    private function extractorFactory(): ExtractorResourceMetadataFactory
    {
        $container = new Container(new ParameterBag([self::CLASS_PARAMETER => WithdrawalRequest::class]));

        return new ExtractorResourceMetadataFactory(new XmlExtractor([self::FILE], $container));
    }

    private function xml(): SimpleXMLElement
    {
        $xml = simplexml_load_file(self::FILE);
        self::assertInstanceOf(SimpleXMLElement::class, $xml);
        $xml->registerXPathNamespace('m', 'https://api-platform.com/schema/metadata');

        return $xml;
    }

    /** @return array<string, SimpleXMLElement> */
    private function operations(string $type): array
    {
        $operations = [];
        foreach ($this->xml()->resource[0]->{$type . 'Operations'}->{$type . 'Operation'} as $operation) {
            $operations[(string) $operation['name']] = $operation;
        }

        return $operations;
    }

    private function attribute(SimpleXMLElement $operation, string $name): ?string
    {
        foreach ($operation->attribute as $attribute) {
            if ($name === (string) $attribute['name']) {
                return (string) $attribute;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    private function openApiContext(SimpleXMLElement $operation): array
    {
        $context = [];
        foreach ($operation->attribute as $attribute) {
            if ('openapi_context' === (string) $attribute['name']) {
                foreach ($attribute->attribute as $entry) {
                    $context[(string) $entry['name']] = (string) $entry;
                }
            }
        }

        return $context;
    }
}
