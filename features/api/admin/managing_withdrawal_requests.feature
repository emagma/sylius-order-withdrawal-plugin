@managing_order_withdrawal_requests @api
Feature: Managing withdrawal requests through the admin API
    In order to process withdrawal requests from an automated agent
    As an Administrator
    I want to read, record, correct and transition withdrawal requests through the API

    Background:
        Given the store operates on a single channel in "United States"
        And there is a withdrawal request for order "000000123" from "Jean Dupont" with email "jean@example.com" in state "new"
        And there is a withdrawal request for order "000000456" from "Anne Martin" with email "anne@example.com" in state "confirmed"
        And I am logged in as an administrator

    Scenario: Listing withdrawal requests
        When I list withdrawal requests through the API
        Then I should see 2 withdrawal requests in the API response

    Scenario: Filtering withdrawal requests by state
        When I list withdrawal requests in state "new" through the API
        Then I should see 1 withdrawal requests in the API response
        And the API response should list the order "000000123"

    Scenario: Showing a withdrawal request with its available transitions
        When I show the withdrawal request for order "000000123" through the API
        Then the API withdrawal request should be in state "new"
        And its available transitions should be "start, confirm, reject"

    @email
    Scenario: Recording a withdrawal request
        When I record through the API a withdrawal request for order "000000789" from "Paul Durand" with email "paul@example.com"
        Then the API should report the withdrawal request as created in state "new"
        And an acknowledgement email should have been sent to "paul@example.com"

    Scenario: Recording an invalid withdrawal request
        When I record through the API a withdrawal request without accepting the legal notice
        Then the API should reject it with a violation on "legalNoticeAccepted"

    Scenario: Recording a withdrawal request with an invalid locale
        When I record through the API a withdrawal request with the locale "not a locale"
        Then the API should reject it with a violation on "localeCode"

    Scenario: The page size is capped
        Given there are 101 other withdrawal requests
        When I list withdrawal requests through the API with 500 items per page
        Then I should see 100 withdrawal requests in the API response

    Scenario: Correcting a withdrawal request
        When I change through the API the email of the withdrawal request for order "000000123" to "jean.dupont@example.com"
        Then the API withdrawal request should have the email "jean.dupont@example.com"

    Scenario: A closed request cannot be edited
        When I change through the API the email of the withdrawal request for order "000000456" to "other@example.com"
        Then the API should reject it with a violation on ""
        And the withdrawal request for order "000000456" should still have the email "anne@example.com"

    Scenario: Patching the state is ignored
        When I try to set through the API the state of the withdrawal request for order "000000123" to "confirmed"
        Then the API withdrawal request should be in state "new"

    Scenario: Taking a request in charge
        When I apply the "start" transition through the API to the withdrawal request for order "000000123"
        Then the API withdrawal request should be in state "in_progress"
        And the API response should identify the withdrawal request for order "000000123"

    @email
    Scenario: Confirming a request notifies the customer once
        When I apply the "confirm" transition through the API to the withdrawal request for order "000000123"
        Then the API withdrawal request should be in state "confirmed"
        And the API response should identify the withdrawal request for order "000000123"
        And exactly 1 confirmation email should have been sent to "jean@example.com"

    Scenario: Rejecting a request
        When I apply the "reject" transition through the API to the withdrawal request for order "000000123"
        Then the API withdrawal request should be in state "rejected"
        And the API response should identify the withdrawal request for order "000000123"

    @email
    Scenario: A closed request cannot be transitioned
        When I apply the "confirm" transition through the API to the withdrawal request for order "000000456"
        Then the API should refuse the transition
        And the withdrawal request for order "000000456" should still be in state "confirmed"
        And no email should have been sent to "anne@example.com"

    Scenario: Withdrawal requests cannot be deleted
        When I try to delete through the API the withdrawal request for order "000000123"
        Then the API should answer that the method is not allowed
