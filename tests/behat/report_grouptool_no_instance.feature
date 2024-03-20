@report @report_grouptool
Feature: Course report grouptool is not available if no grouptool course instance exists
  In order to not see a grouptool course report in course reports
  As a teacher
  I have to make shure that no grouptool instance exists in course

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

  @javascript
  Scenario: No grouptool instance exists - the course grouptool report is not shown in course reports
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Reports" in current page administration
    Then I should not see "Grouptool report"
