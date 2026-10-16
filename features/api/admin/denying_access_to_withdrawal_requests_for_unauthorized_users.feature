@managing_order_withdrawal_requests @api
Feature: Denying access to withdrawal requests for unauthorized users
    In order to protect the customers' withdrawal requests
    As a Visitor
    I do not want to reach the admin API without being logged in

    Background:
        Given the store operates on a single channel in "United States"

    Scenario: The admin API refuses anonymous callers
        When I try to list withdrawal requests through the API without being logged in
        Then the API should answer that I am not authenticated
