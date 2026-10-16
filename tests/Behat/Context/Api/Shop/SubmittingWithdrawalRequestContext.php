<?php

/*
 * This file is part of the Emagma SyliusOrderWithdrawal Plugin.
 * (c) Emagma
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Emagma\SyliusOrderWithdrawalPlugin\Behat\Context\Api\Shop;

use Behat\Behat\Context\Context;
use Behat\Step\Then;
use Behat\Step\When;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Sylius\Behat\Client\ApiClientInterface;
use Sylius\Behat\Client\ResponseCheckerInterface;
use Sylius\Behat\Service\Checker\EmailCheckerInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final readonly class SubmittingWithdrawalRequestContext implements Context
{
    private const RESOURCE = 'withdrawal-requests';

    private const STATUS_CODES = 'emagma_withdrawal_shop_api_status_codes';

    /** @param RepositoryInterface<WithdrawalRequest> $repository */
    public function __construct(
        private ApiClientInterface $client,
        private ResponseCheckerInterface $responseChecker,
        private RepositoryInterface $repository,
        private EmailCheckerInterface $emailChecker,
        private SharedStorageInterface $sharedStorage,
    ) {
    }

    #[When('I submit through the shop API a withdrawal request for order :orderNumber with email :email')]
    public function iSubmitAWithdrawalRequest(string $orderNumber, string $email): void
    {
        $this->submit(['orderNumber' => $orderNumber, 'email' => $email]);
    }

    #[When('I submit through the shop API a withdrawal request for order :orderNumber claiming the state :state')]
    public function iSubmitAWithdrawalRequestClaimingAState(string $orderNumber, string $state): void
    {
        $this->submit(['orderNumber' => $orderNumber, 'state' => $state, 'submittedAt' => '2020-01-01T00:00:00+00:00']);
    }

    #[When('I submit through the shop API a partial withdrawal request for order :orderNumber without detail')]
    public function iSubmitAPartialWithdrawalRequestWithoutDetail(string $orderNumber): void
    {
        $this->submit(['orderNumber' => $orderNumber, 'scope' => 'partial', 'message' => null]);
    }

    #[When('I submit through the shop API a withdrawal request for order :orderNumber with the id :id')]
    public function iSubmitAWithdrawalRequestWithAnId(string $orderNumber, int $id): void
    {
        $this->submit(['orderNumber' => $orderNumber, 'id' => $id]);
    }

    #[When('I submit through the shop API :count withdrawal requests in a row')]
    public function iSubmitWithdrawalRequestsInARow(int $count): void
    {
        $statusCodes = [];
        for ($i = 1; $i <= $count; ++$i) {
            $this->submit(['orderNumber' => sprintf('0000009%02d', $i)]);
            $statusCodes[] = $this->client->getLastResponse()->getStatusCode();
        }

        $this->sharedStorage->set(self::STATUS_CODES, $statusCodes);
    }

    #[Then('the shop API should accept the submission without echoing anything back')]
    public function theShopApiShouldAcceptTheSubmission(): void
    {
        $response = $this->client->getLastResponse();
        Assert::same($response->getStatusCode(), Response::HTTP_ACCEPTED, (string) $response->getContent());
        Assert::same((string) $response->getContent(), '', 'The shop API must not echo the submission back.');
        Assert::false($response->headers->has('Location'), 'The shop API must not disclose the created resource.');
        foreach ($response->headers->all() as $name => $values) {
            foreach ($values as $value) {
                Assert::notRegex((string) $value, '#withdrawal-requests/\d#', sprintf('Header "%s" discloses an id.', $name));
            }
        }
    }

    #[Then('a withdrawal request for order :orderNumber should be recorded on the current channel in state :state')]
    public function aWithdrawalRequestShouldBeRecorded(string $orderNumber, string $state): void
    {
        $request = $this->repository->findOneBy(['orderNumber' => $orderNumber]);
        Assert::isInstanceOf($request, WithdrawalRequest::class);

        /** @var ChannelInterface $channel */
        $channel = $this->sharedStorage->get('channel');
        Assert::same($request->getChannel()?->getCode(), $channel->getCode());
        Assert::same($request->getState(), $state);
        Assert::notNull($request->getSubmittedAt());
        Assert::notSame($request->getSubmittedAt()->format('Y'), '2020');
    }

    #[Then('an acknowledgement email should have been sent to :email')]
    public function anAcknowledgementEmailShouldHaveBeenSentTo(string $email): void
    {
        Assert::true($this->emailChecker->hasRecipient($email));
    }

    #[Then('the shop API should reject it with a violation on :property')]
    public function theShopApiShouldRejectItWithAViolationOn(string $property): void
    {
        $response = $this->client->getLastResponse();
        Assert::same($response->getStatusCode(), Response::HTTP_UNPROCESSABLE_ENTITY);
        Assert::contains((string) $response->getContent(), sprintf('"propertyPath":"%s"', $property));
    }

    #[Then('no withdrawal request for order :orderNumber should be recorded')]
    public function noWithdrawalRequestShouldBeRecorded(string $orderNumber): void
    {
        Assert::null($this->repository->findOneBy(['orderNumber' => $orderNumber]));
    }

    #[Then('the shop API should refuse it as a bad request')]
    public function theShopApiShouldRefuseItAsABadRequest(): void
    {
        $response = $this->client->getLastResponse();
        Assert::same($response->getStatusCode(), Response::HTTP_BAD_REQUEST, (string) $response->getContent());
    }

    #[Then('the first :count shop API submissions should have been accepted')]
    public function theFirstSubmissionsShouldHaveBeenAccepted(int $count): void
    {
        /** @var list<int> $statusCodes */
        $statusCodes = $this->sharedStorage->get(self::STATUS_CODES);
        Assert::greaterThan(\count($statusCodes), $count);
        Assert::same(array_slice($statusCodes, 0, $count), array_fill(0, $count, Response::HTTP_ACCEPTED));
    }

    #[Then('the last shop API submission should have been throttled')]
    public function theLastSubmissionShouldHaveBeenThrottled(): void
    {
        Assert::same($this->client->getLastResponse()->getStatusCode(), Response::HTTP_TOO_MANY_REQUESTS);
    }

    #[When('I try to list withdrawal requests through the shop API')]
    public function iTryToListWithdrawalRequests(): void
    {
        $this->client->index(self::RESOURCE);
    }

    #[Then('the shop API should answer that the resource does not exist')]
    public function theShopApiShouldAnswerNotFound(): void
    {
        Assert::inArray($this->client->getLastResponse()->getStatusCode(), [Response::HTTP_NOT_FOUND, Response::HTTP_METHOD_NOT_ALLOWED]);
    }

    /** @param array<string, mixed> $overrides */
    private function submit(array $overrides): void
    {
        $this->client->buildCreateRequest(self::RESOURCE);
        $this->client->setRequestData(array_merge([
            'firstName' => 'Jean',
            'lastName' => 'Dupont',
            'email' => 'jean@example.com',
            'orderNumber' => '000000123',
            'orderDate' => '2026-06-01',
            'notYetReceived' => true,
            'scope' => 'full',
            'legalNoticeAccepted' => true,
        ], $overrides));
        $this->client->create();
    }
}
