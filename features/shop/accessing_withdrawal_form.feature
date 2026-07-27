@managing_order_withdrawal_requests @shop
Feature: Finding the online withdrawal form
    In order to exercise my right of withdrawal throughout the 14-day window
    As a customer
    I want the withdrawal form to be clearly identifiable and easily accessible

    Background:
        Given the store operates on a single channel in "United States"

    @ui
    Scenario: Reaching the withdrawal form from the shop footer
        Given I am browsing the shop
        When I follow the withdrawal request link in the footer
        Then I should be on the withdrawal request form

    @ui @todo
    Scenario: Reaching the withdrawal form from my account orders
        Given I am a logged in customer
        When I browse my orders
        Then I should be able to reach the withdrawal request form from my orders page