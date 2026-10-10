<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DateTimeImmutable;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use IntlDateFormatter;
use Sylius\Behat\Service\Checker\EmailCheckerInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Page\Shop\RequestWithdrawalPageInterface;
use Webmozart\Assert\Assert;

final readonly class RequestingWithdrawalContext implements Context
{
    public function __construct(
        private RequestWithdrawalPageInterface $requestWithdrawalPage,
        private RepositoryInterface $withdrawalRequestRepository,
        private EmailCheckerInterface $emailChecker,
        private string $notificationEmail,
    ) {
    }

    #[Given('the store sends withdrawal-request notifications to :email')]
    public function theStoreSendsNotificationsTo(string $email): void
    {
        Assert::same($this->notificationEmail, $email);
    }

    #[When('I want to request a withdrawal')]
    public function iWantToRequestAWithdrawal(): void
    {
        $this->requestWithdrawalPage->open(['_locale' => 'en_US']);
    }

    #[When('I specify the customer name as :name')]
    public function iSpecifyTheCustomerNameAs(string $name): void
    {
        [$firstName, $lastName] = array_pad(explode(' ', $name, 2), 2, '');
        $this->requestWithdrawalPage->specifyFirstName($firstName);
        $this->requestWithdrawalPage->specifyLastName($lastName);
    }

    #[When('I specify the customer first name as a :length characters long value')]
    public function iSpecifyTheCustomerFirstNameAsALongValue(int $length): void
    {
        $this->requestWithdrawalPage->specifyFirstName(str_repeat('a', $length));
    }

    #[When('I specify the last name as :lastName')]
    public function iSpecifyTheLastName(string $lastName): void
    {
        $this->requestWithdrawalPage->specifyLastName($lastName);
    }

    #[When('I specify the order number as :orderNumber')]
    public function iSpecifyTheOrderNumberAs(string $orderNumber): void
    {
        $this->requestWithdrawalPage->specifyOrderNumber($orderNumber);
    }

    #[When('I specify the order date as :date')]
    public function iSpecifyTheOrderDateAs(string $date): void
    {
        $this->requestWithdrawalPage->specifyOrderDate($date);
    }

    #[When('I specify the order reception date as :date')]
    public function iSpecifyTheOrderReceptionDateAs(string $date): void
    {
        $this->requestWithdrawalPage->specifyReceptionDate($date);
    }

    #[When('I specify my email as :email')]
    public function iSpecifyMyEmailAs(string $email): void
    {
        $this->requestWithdrawalPage->specifyEmail($email);
    }

    #[When('I withdraw from the totality of my order')]
    public function iWithdrawFromTheTotalityOfMyOrder(): void
    {
        $this->requestWithdrawalPage->chooseScope('full');
    }

    #[When('I withdraw from a part of my order with the detail :detail')]
    public function iWithdrawFromAPartOfMyOrderWithTheDetail(string $detail): void
    {
        $this->requestWithdrawalPage->chooseScope('partial');
        $this->requestWithdrawalPage->provideDetail($detail);
    }

    #[When('I withdraw from a part of my order without any detail')]
    public function iWithdrawFromAPartOfMyOrderWithoutAnyDetail(): void
    {
        $this->requestWithdrawalPage->chooseScope('partial');
    }

    #[When('I provide a valid withdrawal request without the :field')]
    public function iProvideAValidWithdrawalRequestWithoutThe(string $field): void
    {
        $fields = [
            'first name' => fn () => $this->requestWithdrawalPage->specifyFirstName('Jean'),
            'last name' => fn () => $this->requestWithdrawalPage->specifyLastName('Dupont'),
            'order number' => fn () => $this->requestWithdrawalPage->specifyOrderNumber('000000123'),
            'order date' => fn () => $this->requestWithdrawalPage->specifyOrderDate('2026-06-01'),
            'email' => fn () => $this->requestWithdrawalPage->specifyEmail('jean@example.com'),
        ];

        Assert::keyExists($fields, $field, sprintf('Unknown withdrawal field "%s".', $field));

        foreach ($fields as $name => $fill) {
            if ($name !== $field) {
                $fill();
            }
        }

        $this->requestWithdrawalPage->chooseScope('full');
        $this->requestWithdrawalPage->acceptLegalNotice();
    }

    #[When('I declare that I have not received my order yet')]
    public function iDeclareThatIHaveNotReceivedMyOrderYet(): void
    {
        $this->requestWithdrawalPage->declareOrderNotReceivedYet();
    }

    #[When('I accept the legal withdrawal notice')]
    public function iAcceptTheLegalWithdrawalNotice(): void
    {
        $this->requestWithdrawalPage->acceptLegalNotice();
    }

    #[When('I do not accept the legal withdrawal notice')]
    public function iDoNotAcceptTheLegalWithdrawalNotice(): void
    {
        // Intentionally leaves the legal-notice checkbox unticked.
    }

    #[When('I confirm my withdrawal request')]
    #[When('I try to confirm my withdrawal request')]
    public function iConfirmMyWithdrawalRequest(): void
    {
        $this->requestWithdrawalPage->confirm();
    }

    #[When('I request a withdrawal for order :orderNumber with email :email')]
    public function iRequestAWithdrawalForOrderWithEmail(string $orderNumber, string $email): void
    {
        $this->iWantToRequestAWithdrawal();
        $this->iSpecifyTheCustomerNameAs('Jean Dupont');
        $this->iSpecifyTheOrderNumberAs($orderNumber);
        $this->iSpecifyTheOrderDateAs('2026-06-01');
        $this->iSpecifyMyEmailAs($email);
        $this->iWithdrawFromTheTotalityOfMyOrder();
        $this->iAcceptTheLegalWithdrawalNotice();
        $this->iConfirmMyWithdrawalRequest();
    }

    #[Then('I should be notified that my withdrawal request has been registered')]
    public function iShouldBeNotifiedThatMyRequestHasBeenRegistered(): void
    {
        Assert::true($this->requestWithdrawalPage->hasContent('Your withdrawal request has been sent.'));
    }

    #[Then('a withdrawal request for order :orderNumber should be recorded in state :state')]
    public function aWithdrawalRequestForOrderShouldBeRecordedInState(string $orderNumber, string $state): void
    {
        Assert::same($this->getRequestByOrderNumber($orderNumber)->getState(), $state);
    }

    #[Then('the recorded withdrawal request for order :orderNumber should concern part of the order')]
    public function theRecordedWithdrawalRequestShouldConcernPartOfTheOrder(string $orderNumber): void
    {
        Assert::same($this->getRequestByOrderNumber($orderNumber)->getScope()->value, 'partial');
    }

    #[Then('an acknowledgement email recapping my request should be sent to :email')]
    public function anAcknowledgementEmailRecappingMyRequestShouldBeSentTo(string $email): void
    {
        Assert::true(
            $this->emailChecker->hasMessageTo('000000123', $email),
            sprintf('No acknowledgement email recapping the order was sent to "%s".', $email),
        );
    }

    #[Then('this acknowledgement should state the date and time of my request')]
    public function thisAcknowledgementShouldStateTheDateAndTimeOfMyRequest(): void
    {
        // Checks the date only, not the time: the email renders submittedAt via Twig's
        // locale-aware format_datetime, whose medium time format includes seconds — matching
        // those exactly would race against the seconds ticking over before this assertion runs.
        // The request is submitted through the shop's "en_US" locale (see iWantToRequestAWithdrawal()).
        $formatter = new IntlDateFormatter('en_US', IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);

        Assert::true(
            $this->emailChecker->hasMessageTo($formatter->format(new DateTimeImmutable()), 'jean@example.com'),
            'The acknowledgement email does not state the date of the request.',
        );
    }

    #[Then('a notification email about order :orderNumber should be sent to :email')]
    public function aNotificationEmailAboutOrderShouldBeSentTo(string $orderNumber, string $email): void
    {
        Assert::true(
            $this->emailChecker->hasMessageTo($orderNumber, $email),
            sprintf('No notification email about order "%s" was sent to "%s".', $orderNumber, $email),
        );
    }

    #[Then('I should be told that I must accept the withdrawal notice')]
    public function iShouldBeToldThatIMustAcceptTheWithdrawalNotice(): void
    {
        Assert::true($this->requestWithdrawalPage->hasContent('You must accept the withdrawal notice to continue.'));
    }

    #[Then('I should be told that the order date cannot be in the future')]
    public function iShouldBeToldThatTheOrderDateCannotBeInTheFuture(): void
    {
        Assert::true($this->requestWithdrawalPage->hasContent('The order date cannot be in the future.'));
    }

    #[Then('I should be told that the order reception date cannot be in the future')]
    public function iShouldBeToldThatTheOrderReceptionDateCannotBeInTheFuture(): void
    {
        Assert::true($this->requestWithdrawalPage->hasContent('The reception date cannot be in the future.'));
    }

    #[Then('I should be told that this value should not be blank')]
    public function iShouldBeToldThatThisValueShouldNotBeBlank(): void
    {
        Assert::true($this->requestWithdrawalPage->hasContent('This value should not be blank.'));
    }

    #[Then('I should be told that the email address is invalid')]
    public function iShouldBeToldThatTheEmailAddressIsInvalid(): void
    {
        Assert::true($this->requestWithdrawalPage->hasContent('This value is not a valid email address.'));
    }

    #[Then('I should be told that I must detail my partial withdrawal')]
    public function iShouldBeToldThatIMustDetailMyPartialWithdrawal(): void
    {
        Assert::true($this->requestWithdrawalPage->hasContent('Please specify which items your partial withdrawal concerns.'));
    }

    #[Then('I should be told that this value is too long')]
    public function iShouldBeToldThatThisValueIsTooLong(): void
    {
        Assert::true($this->requestWithdrawalPage->hasContent('This value is too long.'));
    }

    #[Then('I should be told that the reception date cannot precede the order date')]
    public function iShouldBeToldTheReceptionDateCannotPrecedeTheOrderDate(): void
    {
        Assert::true($this->requestWithdrawalPage->hasContent(
            'The reception date cannot be earlier than the order date.',
        ));
    }

    #[Then('I should be told that a reception date cannot be given for an order declared as not received')]
    public function iShouldBeToldAReceptionDateCannotBeGivenForANotReceivedOrder(): void
    {
        Assert::true($this->requestWithdrawalPage->hasContent(
            'Leave the reception date empty when the order has not been received yet.',
        ));
    }

    #[Then('no withdrawal request should be recorded')]
    public function noWithdrawalRequestShouldBeRecorded(): void
    {
        Assert::isEmpty($this->withdrawalRequestRepository->findAll());
    }

    private function getRequestByOrderNumber(string $orderNumber): WithdrawalRequest
    {
        $request = $this->withdrawalRequestRepository->findOneBy(['orderNumber' => $orderNumber]);

        Assert::isInstanceOf($request, WithdrawalRequest::class, sprintf('No withdrawal request recorded for order "%s".', $orderNumber));

        return $request;
    }
}
