<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Unit\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * The service definitions moved from XML to PHP for Symfony 8. The XML files stay as deprecated
 * shims, and must keep resolving to exactly what the PHP files define.
 */
final class DeprecatedXmlServicesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideShims(): iterable
    {
        yield 'services' => ['services.xml', 'services.php'];
        yield 'rate limiter' => ['services/rate_limiter.xml', 'services/rate_limiter.php'];
    }

    #[DataProvider('provideShims')]
    public function testTheXmlShimDefinesTheSameServicesAsThePhpFile(string $xmlFile, string $phpFile): void
    {
        if (!class_exists(XmlFileLoader::class)) {
            self::markTestSkipped('Symfony 8 has no XML service loader, so the shim cannot be imported there.');
        }

        $locator = new FileLocator(__DIR__ . '/../../../config');

        // The shim imports the PHP file, so the XML loader needs a resolver that can hand it over.
        $fromXml = new ContainerBuilder();
        $loader = new DelegatingLoader(new LoaderResolver([
            new XmlFileLoader($fromXml, $locator),
            new PhpFileLoader($fromXml, $locator),
        ]));
        $loader->load($xmlFile);

        $fromPhp = new ContainerBuilder();
        (new PhpFileLoader($fromPhp, $locator))->load($phpFile);

        self::assertNotEmpty($fromPhp->getDefinitions());
        self::assertEquals($fromPhp->getDefinitions(), $fromXml->getDefinitions());
        self::assertEquals($fromPhp->getAliases(), $fromXml->getAliases());
        self::assertEquals($fromPhp->getParameterBag()->all(), $fromXml->getParameterBag()->all());
    }
}
