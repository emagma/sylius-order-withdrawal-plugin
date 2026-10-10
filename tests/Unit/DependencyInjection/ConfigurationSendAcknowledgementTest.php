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
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationSendAcknowledgementTest extends TestCase
{
    public function testAcknowledgementEmailsAreSentByDefault(): void
    {
        $config = (new Processor())->processConfiguration(
            new Configuration(),
            [['notification_email' => 'sav@example.com']],
        );

        self::assertTrue($config['send_acknowledgement']);
    }

    public function testAcknowledgementEmailsCanBeDisabled(): void
    {
        $config = (new Processor())->processConfiguration(
            new Configuration(),
            [['notification_email' => 'sav@example.com', 'send_acknowledgement' => false]],
        );

        self::assertFalse($config['send_acknowledgement']);
    }
}
