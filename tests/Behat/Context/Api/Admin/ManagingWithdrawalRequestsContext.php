<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Context\Api\Admin;

use Behat\Behat\Context\Context;
use Behat\Step\Then;
use Behat\Step\When;
use Doctrine\Persistence\ObjectManager;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Sylius\Behat\Client\ApiClientInterface;
use Sylius\Behat\Client\ResponseCheckerInterface;
use Sylius\Behat\Service\Checker\EmailCheckerInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final readonly class ManagingWithdrawalRequestsContext implements Context
{
    private const RESOURCE = 'withdrawal-requests';

    /** @param RepositoryInterface<WithdrawalRequest> $repository */
    public function __construct(
        private ApiClientInterface $client,
        private ResponseCheckerInterface $responseChecker,
        private RepositoryInterface $repository,
        private ObjectManager $manager,
        private EmailCheckerInterface $emailChecker,
    ) {
    }

    #[When('I list withdrawal requests through the API')]
    public function iListWithdrawalRequests(): void
    {
        $this->client->index(self::RESOURCE);
    }

    #[When('I list withdrawal requests in state :state through the API')]
    public function iListWithdrawalRequestsInState(string $state): void
    {
        $this->client->index(self::RESOURCE, ['state' => $state]);
    }

    #[When('I list withdrawal requests through the API with :count items per page')]
    public function iListWithdrawalRequestsWithItemsPerPage(int $count): void
    {
        $this->client->index(self::RESOURCE, ['itemsPerPage' => (string) $count]);
    }

    #[When('I record through the API a withdrawal request with the locale :localeCode')]
    public function iRecordAWithdrawalRequestWithTheLocale(string $localeCode): void
    {
        $this->client->buildCreateRequest(self::RESOURCE);
        $this->client->setRequestData($this->validPayload(['localeCode' => $localeCode]));
        $this->client->create();
    }

    #[Then('I should see :count withdrawal requests in the API response')]
    public function iShouldSeeWithdrawalRequests(int $count): void
    {
        Assert::same($this->responseChecker->countCollectionItems($this->client->getLastResponse()), $count);
    }

    #[Then('the API response should list the order :orderNumber')]
    public function theResponseShouldListTheOrder(string $orderNumber): void
    {
        Assert::true($this->responseChecker->hasItemWithValue($this->client->getLastResponse(), 'orderNumber', $orderNumber));
    }

    #[When('I show the withdrawal request for order :orderNumber through the API')]
    public function iShowTheWithdrawalRequest(string $orderNumber): void
    {
        $this->client->show(self::RESOURCE, $this->idOf($orderNumber));
    }

    #[Then('the API withdrawal request should be in state :state')]
    public function theWithdrawalRequestShouldBeInState(string $state): void
    {
        Assert::same($this->responseChecker->getValue($this->client->getLastResponse(), 'state'), $state);
    }

    #[Then('its available transitions should be :transitions')]
    public function itsAvailableTransitionsShouldBe(string $transitions): void
    {
        Assert::same(
            $this->responseChecker->getValue($this->client->getLastResponse(), 'availableTransitions'),
            array_map(trim(...), explode(',', $transitions)),
        );
    }

    #[When('I record through the API a withdrawal request for order :orderNumber from :name with email :email')]
    public function iRecordAWithdrawalRequest(string $orderNumber, string $name, string $email): void
    {
        [$firstName, $lastName] = explode(' ', $name, 2);

        $this->client->buildCreateRequest(self::RESOURCE);
        $this->client->setRequestData($this->validPayload([
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'orderNumber' => $orderNumber,
        ]));
        $this->client->create();
    }

    #[When('I record through the API a withdrawal request without accepting the legal notice')]
    public function iRecordAWithdrawalRequestWithoutLegalNotice(): void
    {
        $this->client->buildCreateRequest(self::RESOURCE);
        $this->client->setRequestData($this->validPayload(['legalNoticeAccepted' => false]));
        $this->client->create();
    }

    #[Then('the API should report the withdrawal request as created in state :state')]
    public function theApiShouldReportItAsCreated(string $state): void
    {
        $response = $this->client->getLastResponse();
        Assert::true($this->responseChecker->isCreationSuccessful($response), $response->getContent() ?: '');
        Assert::same($this->responseChecker->getValue($response, 'state'), $state);
        Assert::notNull($this->responseChecker->getValue($response, 'submittedAt'));
    }

    #[Then('the API should reject it with a violation on :property')]
    public function theApiShouldRejectItWithAViolationOn(string $property): void
    {
        $response = $this->client->getLastResponse();
        Assert::same($response->getStatusCode(), Response::HTTP_UNPROCESSABLE_ENTITY);
        Assert::contains((string) $response->getContent(), sprintf('"propertyPath":"%s"', $property));
    }

    #[Then('an acknowledgement email should have been sent to :email')]
    public function anAcknowledgementEmailShouldHaveBeenSentTo(string $email): void
    {
        Assert::true($this->emailChecker->hasRecipient($email));
    }

    #[When('I change through the API the email of the withdrawal request for order :orderNumber to :email')]
    public function iChangeTheEmail(string $orderNumber, string $email): void
    {
        $this->client->requestPatch(sprintf('%s/%s', self::RESOURCE, $this->idOf($orderNumber)), ['email' => $email]);
    }

    #[When('I try to set through the API the state of the withdrawal request for order :orderNumber to :state')]
    public function iTryToSetTheState(string $orderNumber, string $state): void
    {
        $this->client->requestPatch(sprintf('%s/%s', self::RESOURCE, $this->idOf($orderNumber)), ['state' => $state]);
    }

    #[Then('the API withdrawal request should have the email :email')]
    public function theWithdrawalRequestShouldHaveTheEmail(string $email): void
    {
        Assert::same($this->responseChecker->getValue($this->client->getLastResponse(), 'email'), $email);
    }

    #[When('I apply the :transition transition through the API to the withdrawal request for order :orderNumber')]
    public function iApplyTheTransition(string $transition, string $orderNumber): void
    {
        $this->client->applyTransition(self::RESOURCE, $this->idOf($orderNumber), $transition);
    }

    #[Then('the API response should identify the withdrawal request for order :orderNumber')]
    public function theResponseShouldIdentifyTheWithdrawalRequest(string $orderNumber): void
    {
        $response = $this->client->getLastResponse();
        $request = $this->findByOrderNumber($orderNumber);
        $iri = sprintf('/api/v2/admin/%s/%s', self::RESOURCE, (string) $request->getId());
        $channel = $request->getChannel();
        Assert::notNull($channel);

        $body = json_encode(json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR), \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
        Assert::notRegex($body, '#/withdrawal-requests/[^/"]+/(start|confirm|reject)\b#');
        Assert::same($this->responseChecker->getValue($response, '@id'), $iri);
        Assert::same($this->responseChecker->getValue($response, 'channel'), sprintf('/api/v2/admin/channels/%s', (string) $channel->getCode()));
    }

    #[Then('exactly :count confirmation email should have been sent to :email')]
    public function exactlyConfirmationEmailsShouldHaveBeenSentTo(int $count, string $email): void
    {
        Assert::same($this->emailChecker->countMessagesTo($email), $count);
    }

    #[Then('the API should refuse the transition')]
    public function theApiShouldRefuseTheTransition(): void
    {
        Assert::same($this->client->getLastResponse()->getStatusCode(), Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Then('the withdrawal request for order :orderNumber should still be in state :state')]
    public function theWithdrawalRequestShouldStillBeInState(string $orderNumber, string $state): void
    {
        $this->manager->clear();
        Assert::same($this->findByOrderNumber($orderNumber)->getState(), $state);
    }

    #[Then('the withdrawal request for order :orderNumber should still have the email :email')]
    public function theWithdrawalRequestShouldStillHaveTheEmail(string $orderNumber, string $email): void
    {
        $this->manager->clear();
        Assert::same($this->findByOrderNumber($orderNumber)->getEmail(), $email);
    }

    #[Then('no email should have been sent to :email')]
    public function noEmailShouldHaveBeenSentTo(string $email): void
    {
        Assert::same($this->emailChecker->countMessagesTo($email), 0);
    }

    #[When('I try to delete through the API the withdrawal request for order :orderNumber')]
    public function iTryToDelete(string $orderNumber): void
    {
        $this->client->delete(self::RESOURCE, $this->idOf($orderNumber));
    }

    #[Then('the API should answer that the method is not allowed')]
    public function theApiShouldAnswerMethodNotAllowed(): void
    {
        Assert::same($this->client->getLastResponse()->getStatusCode(), Response::HTTP_METHOD_NOT_ALLOWED);
    }

    #[When('I try to list withdrawal requests through the API without being logged in')]
    public function iTryToListWithdrawalRequestsAnonymously(): void
    {
        $this->client->index(self::RESOURCE);
    }

    #[Then('the API should answer that I am not authenticated')]
    public function theApiShouldAnswerUnauthorized(): void
    {
        Assert::same($this->client->getLastResponse()->getStatusCode(), Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'firstName' => 'Paul',
            'lastName' => 'Durand',
            'email' => 'paul@example.com',
            'orderNumber' => '000000789',
            'orderDate' => '2026-06-01',
            'notYetReceived' => true,
            'scope' => 'full',
            'legalNoticeAccepted' => true,
        ], $overrides);
    }

    private function idOf(string $orderNumber): string
    {
        return (string) $this->findByOrderNumber($orderNumber)->getId();
    }

    private function findByOrderNumber(string $orderNumber): WithdrawalRequest
    {
        $request = $this->repository->findOneBy(['orderNumber' => $orderNumber]);
        Assert::isInstanceOf($request, WithdrawalRequest::class);

        return $request;
    }
}
