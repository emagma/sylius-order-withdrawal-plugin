<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Emagma\SyliusOrderWithdrawalPlugin\Form\Type;

use Emagma\SyliusOrderWithdrawalPlugin\Model\WithdrawalScope;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class WithdrawalRequestType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'emagma_sylius_order_withdrawal.form.first_name',
            ])
            ->add('lastName', TextType::class, [
                'label' => 'emagma_sylius_order_withdrawal.form.last_name',
            ])
            ->add('orderNumber', TextType::class, [
                'label' => 'emagma_sylius_order_withdrawal.form.order_number',
            ])
            ->add('orderDate', DateType::class, [
                'label' => 'emagma_sylius_order_withdrawal.form.order_date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('notYetReceived', CheckboxType::class, [
                'label' => 'emagma_sylius_order_withdrawal.form.not_yet_received',
                'required' => false,
            ])
            ->add('receptionDate', DateType::class, [
                'label' => 'emagma_sylius_order_withdrawal.form.reception_date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('email', EmailType::class, [
                'label' => 'emagma_sylius_order_withdrawal.form.email',
            ])
            ->add('scope', EnumType::class, [
                'class' => WithdrawalScope::class,
                'expanded' => true,
                'label' => 'emagma_sylius_order_withdrawal.form.scope',
                'choice_label' => static fn (WithdrawalScope $scope): string => 'emagma_sylius_order_withdrawal.scope.' . $scope->value,
            ])
            ->add('message', TextareaType::class, [
                'label' => 'emagma_sylius_order_withdrawal.form.message',
                'required' => false,
            ])
            ->add('legalNoticeAccepted', CheckboxType::class, [
                'label' => 'emagma_sylius_order_withdrawal.form.legal_notice',
                'required' => false,
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'emagma_sylius_order_withdrawal_request';
    }
}
