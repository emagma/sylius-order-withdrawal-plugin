@managing_order_withdrawal_requests @admin
Feature: Processing a withdrawal request
    In order to honour a customer's right of withdrawal
    As an administrator
    I want to track and confirm the handling of a withdrawal request

    Background:
        Given the store operates on a single channel in "United States"
        And there is a withdrawal request for order "000000123" from "Jean Dupont" with email "jean@example.com" in state "new"
        And I am logged in as an administrator

    @ui @javascript
    Scenario: Taking a new request in charge
        When I browse withdrawal requests
        And I take the withdrawal request for order "000000123" in charge
        Then the withdrawal request for order "000000123" should be in state "in progress"

    @ui @javascript @email
    Scenario: Confirming a withdrawal notifies the customer
        When I browse withdrawal requests
        And I confirm the withdrawal request for order "000000123"
        Then the withdrawal request for order "000000123" should be in state "confirmed"
        And a withdrawal confirmation email should be sent to "jean@example.com"

    @ui @javascript
    Scenario: Rejecting a withdrawal request
        When I browse withdrawal requests
        And I reject the withdrawal request for order "000000123"
        Then the withdrawal request for order "000000123" should be in state "rejected"