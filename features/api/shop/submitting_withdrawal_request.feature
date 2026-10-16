@managing_order_withdrawal_requests @api
Feature: Submitting a withdrawal request through the shop API
    In order to withdraw from an order from a headless storefront
    As a Visitor
    I want to submit a withdrawal request through the shop API

    Background:
        Given the store operates on a single channel in "United States"

    @email
    Scenario: Submitting a withdrawal request
        When I submit through the shop API a withdrawal request for order "000000123" with email "jean@example.com"
        Then the shop API should accept the submission without echoing anything back
        And a withdrawal request for order "000000123" should be recorded on the current channel in state "new"
        And an acknowledgement email should have been sent to "jean@example.com"

    Scenario: The state cannot be chosen from the shop API
        When I submit through the shop API a withdrawal request for order "000000124" claiming the state "confirmed"
        Then a withdrawal request for order "000000124" should be recorded on the current channel in state "new"

    Scenario: Submitting a partial withdrawal without detail
        When I submit through the shop API a partial withdrawal request for order "000000123" without detail
        Then the shop API should reject it with a violation on "message"

    Scenario: A body containing an id cannot target an existing request
        Given there is a withdrawal request for order "000000456" from "Anne Martin" with email "anne@example.com" in state "new"
        When I submit through the shop API a withdrawal request for order "000000125" with the id of this withdrawal request
        Then the shop API should accept the submission without echoing anything back
        And a withdrawal request for order "000000125" should be recorded with a different id from this withdrawal request
        And this withdrawal request should still be for order "000000456" with email "anne@example.com"

    Scenario: The shop API throttles repeated submissions
        When I submit through the shop API 4 withdrawal requests in a row
        Then the first 3 shop API submissions should have been accepted
        And the last shop API submission should have been throttled

    Scenario: The shop API cannot list withdrawal requests
        When I try to list withdrawal requests through the shop API
        Then the shop API should answer that the resource does not exist
