@managing_order_withdrawal_requests @shop
Feature: Requesting the withdrawal of an order online
    In order to exercise my legal right of withdrawal (ordonnance n°2026-2)
    As a customer
    I want to notify the shop online that I withdraw from my purchase

    Background:
        Given the store operates on a single channel in "United States"
        And the store sends withdrawal-request notifications to "sav@example.com"

    @ui
    Scenario: Withdrawing from a whole order as a visitor
        When I want to request a withdrawal
        And I specify the customer name as "Jean Dupont"
        And I specify the order number as "000000123"
        And I specify the order date as "2026-06-01"
        And I specify my email as "jean@example.com"
        And I withdraw from the totality of my order
        And I accept the legal withdrawal notice
        And I confirm my withdrawal request
        Then I should be notified that my withdrawal request has been registered
        And a withdrawal request for order "000000123" should be recorded in state "new"

    @ui
    Scenario: Withdrawing from part of an order
        When I want to request a withdrawal
        And I specify the customer name as "Jean Dupont"
        And I specify the order number as "000000123"
        And I specify the order date as "2026-06-01"
        And I specify my email as "jean@example.com"
        And I withdraw from a part of my order with the detail "Article B uniquement"
        And I accept the legal withdrawal notice
        And I confirm my withdrawal request
        Then I should be notified that my withdrawal request has been registered
        And the recorded withdrawal request for order "000000123" should concern part of the order

    @ui
    Scenario: Requesting a withdrawal before the order is received
        When I want to request a withdrawal
        And I specify the customer name as "Jean Dupont"
        And I specify the order number as "000000123"
        And I specify the order date as "2026-06-01"
        And I declare that I have not received my order yet
        And I specify my email as "jean@example.com"
        And I accept the legal withdrawal notice
        And I confirm my withdrawal request
        Then I should be notified that my withdrawal request has been registered

    @ui @email
    Scenario: Receiving an acknowledgement on a durable medium
        When I request a withdrawal for order "000000123" with email "jean@example.com"
        Then an acknowledgement email recapping my request should be sent to "jean@example.com"
        And this acknowledgement should state the date and time of my request

    @ui @email
    Scenario: The after-sales service is notified of a new request
        When I request a withdrawal for order "000000123" with email "jean@example.com"
        Then a notification email about order "000000123" should be sent to "sav@example.com"

    @ui
    Scenario: The legal withdrawal notice must be accepted
        When I want to request a withdrawal
        And I specify the customer name as "Jean Dupont"
        And I specify the order number as "000000123"
        And I specify my email as "jean@example.com"
        But I do not accept the legal withdrawal notice
        And I try to confirm my withdrawal request
        Then I should be told that I must accept the withdrawal notice

    @ui
    Scenario: The order date cannot be in the future
        When I want to request a withdrawal
        And I specify the customer name as "Jean Dupont"
        And I specify the order number as "000000123"
        And I specify the order date as "2099-01-01"
        And I specify my email as "jean@example.com"
        And I withdraw from the totality of my order
        And I accept the legal withdrawal notice
        And I try to confirm my withdrawal request
        Then I should be told that the order date cannot be in the future
        And no withdrawal request should be recorded

    @ui
    Scenario: The order reception date cannot be in the future
        When I want to request a withdrawal
        And I specify the customer name as "Jean Dupont"
        And I specify the order number as "000000123"
        And I specify the order date as "2026-06-01"
        And I specify the order reception date as "2099-01-01"
        And I specify my email as "jean@example.com"
        And I withdraw from the totality of my order
        And I accept the legal withdrawal notice
        And I try to confirm my withdrawal request
        Then I should be told that the order reception date cannot be in the future
        And no withdrawal request should be recorded

    @ui
    Scenario Outline: The mandatory withdrawal fields are required
        When I want to request a withdrawal
        And I provide a valid withdrawal request without the "<field>"
        And I try to confirm my withdrawal request
        Then I should be told that this value should not be blank
        And no withdrawal request should be recorded

        Examples:
            | field        |
            | first name   |
            | last name    |
            | order number |
            | order date   |
            | email        |

    @ui
    Scenario: The email address must be a valid one
        When I want to request a withdrawal
        And I specify the customer name as "Jean Dupont"
        And I specify the order number as "000000123"
        And I specify the order date as "2026-06-01"
        And I specify my email as "not-an-email"
        And I withdraw from the totality of my order
        And I accept the legal withdrawal notice
        And I try to confirm my withdrawal request
        Then I should be told that the email address is invalid
        And no withdrawal request should be recorded

    @ui
    Scenario: A partial withdrawal must detail the items concerned
        When I want to request a withdrawal
        And I specify the customer name as "Jean Dupont"
        And I specify the order number as "000000123"
        And I specify the order date as "2026-06-01"
        And I specify my email as "jean@example.com"
        And I withdraw from a part of my order without any detail
        And I accept the legal withdrawal notice
        And I try to confirm my withdrawal request
        Then I should be told that I must detail my partial withdrawal
        And no withdrawal request should be recorded