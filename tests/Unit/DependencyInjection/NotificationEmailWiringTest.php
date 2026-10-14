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
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Resolver\DefaultRecipientResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Configuration and DefaultRecipientResolver are each tested alone; this covers the chain between
 * them — config, services.php, a compiled container resolving the env var, the resolver built from
 * it. An unset `%env(default::…)%` reaches the constructor as null, so a non-nullable parameter
 * fails here with a TypeError and nowhere else.
 */
final class NotificationEmailWiringTest extends TestCase
{
    private const ENV_VAR = 'EMAGMA_WITHDRAWAL_WIRING_TEST_EMAIL';

    protected function tearDown(): void
    {
        unset($_SERVER[self::ENV_VAR], $_ENV[self::ENV_VAR]);
    }

    public function testAnUnsetEnvironmentVariableResolvesToNoRecipient(): void
    {
        $resolver = $this->compileResolver(['notification_email' => '%env(default::' . self::ENV_VAR . ')%']);

        self::assertSame([], $resolver->getRecipients(new WithdrawalRequest()));
    }

    public function testAnEmptyEnvironmentVariableResolvesToNoRecipient(): void
    {
        $_SERVER[self::ENV_VAR] = '';

        $resolver = $this->compileResolver(['notification_email' => '%env(default::' . self::ENV_VAR . ')%']);

        self::assertSame([], $resolver->getRecipients(new WithdrawalRequest()));
    }

    public function testASetEnvironmentVariableBecomesTheRecipient(): void
    {
        $_SERVER[self::ENV_VAR] = 'sav@example.com';

        $resolver = $this->compileResolver(['notification_email' => '%env(default::' . self::ENV_VAR . ')%']);

        self::assertSame(['sav@example.com'], $resolver->getRecipients(new WithdrawalRequest()));
    }

    public function testAnOmittedNotificationEmailResolvesToNoRecipient(): void
    {
        $resolver = $this->compileResolver([]);

        self::assertSame([], $resolver->getRecipients(new WithdrawalRequest()));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function compileResolver(array $config): DefaultRecipientResolver
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new EmagmaSyliusOrderWithdrawalExtension());
        $container->loadFromExtension('emagma_sylius_order_withdrawal', $config);

        // The plugin's other services need Sylius and the mailer; only the resolver is under test.
        $container->addCompilerPass(new class() implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach (array_keys($container->getDefinitions()) as $id) {
                    if (DefaultRecipientResolver::class !== $id && 'service_container' !== $id) {
                        $container->removeDefinition($id);
                    }
                }

                $container->getDefinition(DefaultRecipientResolver::class)->setPublic(true);
            }
        });

        $container->compile(true);

        $resolver = $container->get(DefaultRecipientResolver::class);
        self::assertInstanceOf(DefaultRecipientResolver::class, $resolver);

        return $resolver;
    }
}
