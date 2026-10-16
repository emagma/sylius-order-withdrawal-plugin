<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Emagma\SyliusOrderWithdrawalPlugin\Api\Metadata\WithdrawalRequestOperationsResourceMetadataCollectionFactory;
use Emagma\SyliusOrderWithdrawalPlugin\Api\Serializer\AvailableTransitionsNormalizer;
use Emagma\SyliusOrderWithdrawalPlugin\Api\State\WithdrawalRequestCreateProcessor;
use Emagma\SyliusOrderWithdrawalPlugin\Api\State\WithdrawalRequestTransitionProcessor;
use Emagma\SyliusOrderWithdrawalPlugin\Confirmation\PendingConfirmationQueueInterface;
use Emagma\SyliusOrderWithdrawalPlugin\StateMachine\WithdrawalRequestTransitionApplierInterface;
use Emagma\SyliusOrderWithdrawalPlugin\Submission\WithdrawalRequestSubmissionHandlerInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->private()
    ;

    $services->set(WithdrawalRequestOperationsResourceMetadataCollectionFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_collection_factory', null, 750)
        ->args([
            service('.inner'),
            param('emagma_sylius_order_withdrawal.model.withdrawal_request.class'),
            param('emagma_sylius_order_withdrawal.api.admin'),
            param('emagma_sylius_order_withdrawal.api.shop'),
        ])
    ;

    $services->set(AvailableTransitionsNormalizer::class)
        ->args([service(WithdrawalRequestTransitionApplierInterface::class)])
        ->tag('serializer.normalizer', ['priority' => 64])
    ;

    $services->set(WithdrawalRequestCreateProcessor::class)
        ->args([
            service('api_platform.doctrine.orm.state.persist_processor'),
            service(WithdrawalRequestSubmissionHandlerInterface::class),
        ])
        ->tag('api_platform.state_processor')
    ;

    $services->set(WithdrawalRequestTransitionProcessor::class)
        ->args([
            service('doctrine'),
            service(WithdrawalRequestTransitionApplierInterface::class),
            service(PendingConfirmationQueueInterface::class),
        ])
        ->tag('api_platform.state_processor')
    ;

    $services->set('emagma_sylius_order_withdrawal.api.search_filter.admin.withdrawal_request')
        ->parent('api_platform.doctrine.orm.search_filter')
        ->args([[
            'state' => 'exact',
            'scope' => 'exact',
            'email' => 'ipartial',
            'orderNumber' => 'ipartial',
            'lastName' => 'ipartial',
        ]])
        ->tag('api_platform.filter')
    ;

    $services->set('emagma_sylius_order_withdrawal.api.date_filter.admin.withdrawal_request')
        ->parent('api_platform.doctrine.orm.date_filter')
        ->args([['submittedAt' => null]])
        ->tag('api_platform.filter')
    ;

    $services->set('emagma_sylius_order_withdrawal.api.order_filter.admin.withdrawal_request')
        ->parent('api_platform.doctrine.orm.order_filter')
        ->args([['submittedAt' => null, 'id' => null]])
        ->tag('api_platform.filter')
    ;
};
