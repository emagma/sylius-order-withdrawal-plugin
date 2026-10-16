<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\Behat\Client\ResponseCheckerInterface;
use Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Context\Api\Admin\ManagingWithdrawalRequestsContext as ApiManagingWithdrawalRequestsContext;
use Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Context\Api\Shop\SubmittingWithdrawalRequestContext;
use Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Context\Hook\RateLimiterContext;
use Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Context\Setup\WithdrawalRequestContext;
use Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Context\Ui\Admin\ManagingWithdrawalRequestsContext;
use Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Context\Ui\Shop\AccessingWithdrawalFormContext;
use Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Context\Ui\Shop\RequestingWithdrawalContext;
use Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Page\Admin\WithdrawalRequest\IndexPage;
use Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Page\Admin\WithdrawalRequest\ShowPage;
use Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Page\Shop\RequestWithdrawalPage;
use Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Page\Shop\ShopHomePage;

return static function (ContainerConfigurator $container): void {
    // The core Sylius Behat services. Sylius 2.3 moved them to services.php and kept services.xml
    // as a shim, which Symfony 8 can no longer load; Sylius 2.0–2.2 ship only services.xml.
    $syliusBehatConfig = __DIR__ . '/../../../../vendor/sylius/sylius/src/Sylius/Behat/Resources/config';
    $container->import(
        file_exists($syliusBehatConfig . '/services.php')
            ? $syliusBehatConfig . '/services.php'
            : $syliusBehatConfig . '/services.xml',
    );

    $services = $container->services();

    // Pages
    $services->set(RequestWithdrawalPage::class, RequestWithdrawalPage::class)
        ->parent('sylius.behat.symfony_page')
        ->private()
    ;

    $services->set(ShopHomePage::class, ShopHomePage::class)
        ->parent('sylius.behat.symfony_page')
        ->private()
    ;

    $services->set(IndexPage::class, IndexPage::class)
        ->parent('sylius.behat.page.admin.crud.index')
        ->private()
        ->args(['emagma_sylius_order_withdrawal_admin_withdrawal_request_index'])
    ;

    $services->set(ShowPage::class, ShowPage::class)
        ->parent('sylius.behat.symfony_page')
        ->private()
    ;

    // Contexts
    $services->set(RateLimiterContext::class)
        ->public()
        ->args([service('test.withdrawal_rate_limiter_pool')])
    ;

    $services->set(RequestingWithdrawalContext::class)
        ->public()
        ->args([
            service(RequestWithdrawalPage::class),
            service('emagma_sylius_order_withdrawal.repository.withdrawal_request'),
            service('sylius.behat.email_checker'),
            param('emagma_sylius_order_withdrawal.notification_email'),
        ])
    ;

    $services->set(AccessingWithdrawalFormContext::class)
        ->public()
        ->args([
            service(ShopHomePage::class),
            service(RequestWithdrawalPage::class),
        ])
    ;

    $services->set(WithdrawalRequestContext::class)
        ->public()
        ->args([
            service('sylius.behat.shared_storage'),
            service('emagma_sylius_order_withdrawal.factory.withdrawal_request'),
            service('emagma_sylius_order_withdrawal.manager.withdrawal_request'),
        ])
    ;

    $services->set(ManagingWithdrawalRequestsContext::class)
        ->public()
        ->args([
            service(IndexPage::class),
            service(ShowPage::class),
            service('emagma_sylius_order_withdrawal.repository.withdrawal_request'),
            service('sylius.behat.email_checker'),
        ])
    ;

    $services->set(ApiManagingWithdrawalRequestsContext::class)
        ->public()
        ->args([
            service('sylius.behat.api_platform_client.admin'),
            service(ResponseCheckerInterface::class),
            service('emagma_sylius_order_withdrawal.repository.withdrawal_request'),
            service('emagma_sylius_order_withdrawal.manager.withdrawal_request'),
            service('sylius.behat.email_checker'),
        ])
    ;

    $services->set(SubmittingWithdrawalRequestContext::class)
        ->public()
        ->args([
            service('sylius.behat.api_platform_client.shop'),
            service(ResponseCheckerInterface::class),
            service('emagma_sylius_order_withdrawal.repository.withdrawal_request'),
            service('sylius.behat.email_checker'),
            service('sylius.behat.shared_storage'),
        ])
    ;
};
