<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\DependencyInjection;

use Emagma\SyliusOrderWithdrawalPlugin\DependencyInjection\EmagmaSyliusOrderWithdrawalExtension;
use Emagma\SyliusOrderWithdrawalPlugin\EventListener\WithdrawalRequestRateLimitListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\RemoveUnusedDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml;

final class EmagmaSyliusOrderWithdrawalExtensionTest extends TestCase
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private const CONFIG = [['notification_email' => 'sav@example.com']];

    private const LIMITER_FACTORY_ID = 'emagma_sylius_order_withdrawal.rate_limiter.withdrawal_request';

    /**
     * Symfony's PSR-4 loader registers an excluded class as a stub Definition tagged
     * "container.excluded", pruned only when the container compiles. load() alone never
     * compiles, so hasDefinition() would report the listener as present; running
     * RemoveUnusedDefinitionsPass reproduces what a real container ends up with.
     */
    public function testTheRateLimiterIsNotWiredByDefault(): void
    {
        $container = new ContainerBuilder();

        (new EmagmaSyliusOrderWithdrawalExtension())->load(self::CONFIG, $container);

        self::assertTrue(
            $container->getDefinition(WithdrawalRequestRateLimitListener::class)->hasTag('container.excluded'),
            'Expected the listener to only exist as an excluded prototype stub when no rate_limiter_service is configured.',
        );

        (new RemoveUnusedDefinitionsPass())->process($container);

        self::assertFalse($container->hasDefinition(WithdrawalRequestRateLimitListener::class));
    }

    public function testItWiresTheListenerToTheConfiguredLimiterService(): void
    {
        $container = new ContainerBuilder();

        (new EmagmaSyliusOrderWithdrawalExtension())->load(
            [self::CONFIG[0] + ['rate_limiter_service' => 'limiter.withdrawal']],
            $container,
        );

        self::assertTrue($container->hasDefinition(WithdrawalRequestRateLimitListener::class));
        self::assertFalse(
            $container->getDefinition(WithdrawalRequestRateLimitListener::class)->hasTag('container.excluded'),
            'The wired listener must be the real service.xml definition, not the leftover prototype stub.',
        );
        self::assertSame('limiter.withdrawal', (string) $container->getAlias(self::LIMITER_FACTORY_ID));
    }

    public function testItDefinesNoLimiterOfItsOwn(): void
    {
        $container = new ContainerBuilder();

        (new EmagmaSyliusOrderWithdrawalExtension())->load(
            [self::CONFIG[0] + ['rate_limiter_service' => 'limiter.withdrawal']],
            $container,
        );

        self::assertFalse($container->hasDefinition(self::LIMITER_FACTORY_ID));
        self::assertFalse($container->hasDefinition('emagma_sylius_order_withdrawal.rate_limiter.storage'));
    }

    public function testItExposesTheProcessedConfigurationAsParameters(): void
    {
        $container = new ContainerBuilder();

        (new EmagmaSyliusOrderWithdrawalExtension())->load(
            [self::CONFIG[0] + ['prefer_channel_contact_email' => false, 'send_acknowledgement' => false]],
            $container,
        );

        self::assertSame('sav@example.com', $container->getParameter('emagma_sylius_order_withdrawal.notification_email'));
        self::assertFalse($container->getParameter('emagma_sylius_order_withdrawal.prefer_channel_contact_email'));
        self::assertFalse($container->getParameter('emagma_sylius_order_withdrawal.send_acknowledgement'));
    }

    public function testTheNotificationEmailParameterIsNullWhenUnset(): void
    {
        $container = new ContainerBuilder();

        (new EmagmaSyliusOrderWithdrawalExtension())->load([[]], $container);

        self::assertNull($container->getParameter('emagma_sylius_order_withdrawal.notification_email'));
    }

    public function testItRegistersTheDoctrineXmlMapping(): void
    {
        $container = new ContainerBuilder();

        (new EmagmaSyliusOrderWithdrawalExtension())->prepend($container);

        $mapping = $container->getExtensionConfig('doctrine')[0]['orm']['mappings']['EmagmaSyliusOrderWithdrawalPlugin'];

        self::assertSame('xml', $mapping['type']);
        self::assertSame('config/doctrine', $mapping['dir']);
        self::assertTrue($mapping['is_bundle']);
    }

    /**
     * On this line the shop footer is injected through a sylius_ui template event rather than a
     * prepended Twig hook, so the wiring lives in config and is asserted there — this fails if
     * the event key or the template path drifts apart.
     */
    public function testItInjectsTheShopFooterThroughASyliusUiEvent(): void
    {
        /** @var array{sylius_ui: array{events: array<string, array{blocks: array<string, array{template: string}>}>}} $config */
        $config = Yaml::parseFile(__DIR__ . '/../../../config/app/sylius_ui.yaml');

        $blocks = $config['sylius_ui']['events']['sylius.shop.layout.footer.grid']['blocks'];

        self::assertSame(
            '@EmagmaSyliusOrderWithdrawalPlugin/shop/footer/_withdrawal_request.html.twig',
            $blocks['emagma_withdrawal_request']['template'],
        );
    }
}
