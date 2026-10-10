<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Step\Then;
use Behat\Step\When;
use DateTimeImmutable;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use IntlDateFormatter;
use Sylius\Behat\Service\Checker\EmailCheckerInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Page\Admin\WithdrawalRequest\IndexPageInterface;
use Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Page\Admin\WithdrawalRequest\ShowPageInterface;
use Webmozart\Assert\Assert;

final class ManagingWithdrawalRequestsContext implements Context
{
    public function __construct(
        private readonly IndexPageInterface $indexPage,
        private readonly ShowPageInterface $showPage,
        private readonly RepositoryInterface $withdrawalRequestRepository,
        private readonly EmailCheckerInterface $emailChecker,
    ) {
    }

    #[When('I browse withdrawal requests')]
    public function iBrowseWithdrawalRequests(): void
    {
        $this->indexPage->open();
    }

    #[When('I filter them by the :state state')]
    public function iFilterThemByTheState(string $state): void
    {
        $this->indexPage->filterByState($this->normalizeState($state));
    }

    #[When('I view the withdrawal request for order :orderNumber')]
    public function iViewTheWithdrawalRequestForOrder(string $orderNumber): void
    {
        $this->showPage->open(['id' => $this->getRequestByOrderNumber($orderNumber)->getId()]);
    }

    #[When('I take the withdrawal request for order :orderNumber in charge')]
    public function iTakeTheWithdrawalRequestForOrderInCharge(string $orderNumber): void
    {
        $this->iViewTheWithdrawalRequestForOrder($orderNumber);
        $this->showPage->takeInCharge();
    }

    #[When('I confirm the withdrawal request for order :orderNumber')]
    public function iConfirmTheWithdrawalRequestForOrder(string $orderNumber): void
    {
        $this->iViewTheWithdrawalRequestForOrder($orderNumber);
        $this->showPage->confirm();
    }

    #[When('I reject the withdrawal request for order :orderNumber')]
    public function iRejectTheWithdrawalRequestForOrder(string $orderNumber): void
    {
        $this->iViewTheWithdrawalRequestForOrder($orderNumber);
        $this->showPage->reject();
    }

    #[Then('I should see :count withdrawal requests in the list')]
    public function iShouldSeeWithdrawalRequestsInTheList(int $count): void
    {
        Assert::same($this->indexPage->countItems(), $count);
    }

    #[Then('I should see a single withdrawal request in the list')]
    public function iShouldSeeASingleWithdrawalRequestInTheList(): void
    {
        Assert::same($this->indexPage->countItems(), 1);
    }

    #[Then('I should see a withdrawal request for order :orderNumber')]
    public function iShouldSeeAWithdrawalRequestForOrder(string $orderNumber): void
    {
        Assert::true(
            $this->indexPage->hasRequestForOrder($orderNumber),
            sprintf('No withdrawal request for order "%s" is visible in the list.', $orderNumber),
        );
    }

    #[Then('I should see the customer name :name')]
    public function iShouldSeeTheCustomerName(string $name): void
    {
        Assert::true(
            $this->showPage->hasContent($name),
            sprintf('The customer name "%s" is not shown on the page.', $name),
        );
    }

    #[Then('I should see the date and time it was submitted')]
    public function iShouldSeeTheDateAndTimeItWasSubmitted(): void
    {
        // Checks the date only, not the time: the show page renders submittedAt via Twig's
        // locale-aware format_datetime, whose medium time format includes seconds — matching
        // those exactly would race against the seconds ticking over between fixture setup and
        // this assertion. The test app's default locale is "en_US" (see services.yaml).
        $formatter = new IntlDateFormatter('en_US', IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);

        Assert::true(
            $this->showPage->hasContent($formatter->format(new DateTimeImmutable())),
            'The submission date and time is not shown on the page.',
        );
    }

    #[Then('the withdrawal request for order :orderNumber should be in state :state')]
    public function theWithdrawalRequestForOrderShouldBeInState(string $orderNumber, string $state): void
    {
        Assert::same(
            strtolower($this->showPage->getStateLabel()),
            strtolower($state),
        );
    }

    /**
     * EmailChecker matches a substring of the rendered body, so the needle has to belong to the
     * confirmation and to nothing else this plugin sends. "withdrawal" does not — the
     * acknowledgement ("We acknowledge receipt of your withdrawal request…") contains it — and
     * neither does "has been processed", which sits one word away from the acknowledgement's
     * "once our Customer Service has processed it". This phrase opens the confirmation body and
     * appears in no other template. Today the background persists the request directly and
     * sends no acknowledgement, so the distinction is dormant; it stops this step greening on
     * the wrong email if that background ever goes through the public form.
     */
    #[Then('a withdrawal confirmation email should be sent to :email')]
    public function aWithdrawalConfirmationEmailShouldBeSentTo(string $email): void
    {
        Assert::true(
            $this->emailChecker->hasMessageTo('Your withdrawal for order', $email),
            sprintf('No withdrawal confirmation email was sent to "%s".', $email),
        );
    }

    private function getRequestByOrderNumber(string $orderNumber): WithdrawalRequest
    {
        $request = $this->withdrawalRequestRepository->findOneBy(['orderNumber' => $orderNumber]);

        Assert::isInstanceOf($request, WithdrawalRequest::class, sprintf('No withdrawal request recorded for order "%s".', $orderNumber));

        return $request;
    }

    private function normalizeState(string $state): string
    {
        return str_replace(' ', '_', $state);
    }
}
