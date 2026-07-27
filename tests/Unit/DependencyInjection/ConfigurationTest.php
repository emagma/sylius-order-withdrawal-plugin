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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testItDefaultsToAPlaceholderNotificationEmail(): void
    {
        $config = $this->process([]);

        self::assertSame('notifications@example.com', $config['notification_email']);
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
