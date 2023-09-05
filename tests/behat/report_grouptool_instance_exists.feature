@report @report_grouptool
Feature: Course report grouptool is only available if at least one grouptool course instance exist
  In order to be able to view a grouptool course report
  As a teacher
  I need to have at least one grouptool instance in my course

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |

  Scenario: One grouptool instance exists - the course grouptool report is shown
    Given the following "activity" exists:
      | activity    | grouptool          |
      | course      | C1                 |
      | idnumber    | grouptoolid        |
      | name        | Grouptool name     |
      | intro       | Grouptool Desc     |
      | section     | 1                  |
    When I am on the "Course 1" course page logged in as teacher1
    And I follow "Reports"
    Then I should see "Grouptool"

