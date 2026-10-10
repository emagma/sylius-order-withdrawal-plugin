<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\DependencyInjection;

use Emagma\SyliusOrderWithdrawalPlugin\DependencyInjection\Configuration;
use Emagma\SyliusOrderWithdrawalPlugin\DependencyInjection\EmagmaSyliusOrderWithdrawalExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Compiler\ValidateEnvPlaceholdersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;

final class ConfigurationTest extends TestCase
{
    public function testNotificationEmailIsOptional(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), []);

        self::assertNull($config['notification_email']);
    }

    public function testItAcceptsAnEmptyNotificationEmail(): void
    {
        self::assertSame('', $this->process(['notification_email' => ''])['notification_email']);
    }

    /**
     * The reported failure: a scalar node that is cannotBeEmpty() *and* carries a validation
     * closure rejects env placeholders outright, because Symfony feeds the closure the empty
     * string it substitutes for an unresolved env var. Priming the placeholder bag and running
     * ValidateEnvPlaceholdersPass is what a real container compile does — a bare Processor run
     * would never reach that code path.
     */
    public function testItAcceptsAnEnvironmentVariableAsNotificationEmail(): void
    {
        $bag = new EnvPlaceholderParameterBag();
        $bag->get('env(SAV_EMAIL)');

        $container = new ContainerBuilder($bag);
        $container->registerExtension(new EmagmaSyliusOrderWithdrawalExtension());
        $container->loadFromExtension('emagma_sylius_order_withdrawal', [
            'notification_email' => '%env(SAV_EMAIL)%',
        ]);

        (new ValidateEnvPlaceholdersPass())->process($container);

        $this->expectNotToPerformAssertions();
    }

    public function testItDefaultsThePreferChannelContactEmailFlagToTrue(): void
    {
        $config = $this->process(['notification_email' => 'sav@example.com']);

        self::assertSame('sav@example.com', $config['notification_email']);
        self::assertTrue($config['prefer_channel_contact_email']);
    }

    public function testItAcceptsAValidNotificationEmail(): void
    {
        $config = $this->process(['notification_email' => 'sav@example.com']);

        self::assertSame('sav@example.com', $config['notification_email']);
    }

    public function testItRejectsAMalformedNotificationEmail(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/not a valid email address/');

        $this->process(['notification_email' => 'not-an-email']);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
