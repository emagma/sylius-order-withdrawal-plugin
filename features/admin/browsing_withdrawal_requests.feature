@managing_order_withdrawal_requests @admin
Feature: Browsing customer withdrawal requests
    In order to process customers exercising their right of withdrawal
    As an administrator
    I want to see and filter the withdrawal requests submitted online

    Background:
        Given the store operates on a single channel in "United States"
        And there is a withdrawal request for order "000000123" from "Jean Dupont" in state "new"
        And there is a withdrawal request for order "000000456" from "Marie Martin" in state "confirmed"
        And I am logged in as an administrator

    @ui
    Scenario: Listing all withdrawal requests
        When I browse withdrawal requests
        Then I should see 2 withdrawal requests in the list
        And I should see a withdrawal request for order "000000123"

    @ui
    Scenario: Filtering withdrawal requests by their state
        When I browse withdrawal requests
        And I filter them by the "new" state
        Then I should see a single withdrawal request in the list
        And I should see a withdrawal request for order "000000123"

    @ui
    Scenario: Viewing the details of a withdrawal request
        When I browse withdrawal requests
        And I view the withdrawal request for order "000000123"
        Then I should see the customer name "Jean Dupont"
        And I should see the date and time it was submitted