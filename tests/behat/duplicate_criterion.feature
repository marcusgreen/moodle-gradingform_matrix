@gradingform @gradingform_matrix
Feature: Criteria can be duplicated
  In order to build a grading form faster
  As a teacher
  I need to copy a criterion with its levels

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activity" exists:
      | activity                          | assign                      |
      | course                            | C1                          |
      | name                              | Test assignment 1 name      |
      | intro                             | Test assignment description |
      | advancedgradingmethod_submissions | matrix                      |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage

  @javascript
  Scenario: Duplicating a criterion copies its description, levels and scores
    When I go to "Test assignment 1 name" advanced grading definition page
    And I set the following fields to these values:
      | Name | Assignment 1 matrix |
    And I define the following matrix:
      | Spelling | Many errors | 0 | Few errors | 3 | No errors | 7 |
      | Pictures | None        | 0 | Some       | 5 |           |   |
    And I click on "Duplicate criterion" "button" in the "Spelling" "table_row"
    And I press "Save as draft"
    Then "(//tr[@data-criterion='Spelling'])[2]" "xpath_element" should exist
    And "(//tr[@data-criterion='Spelling'])[3]" "xpath_element" should not exist
    And "(//tr[@data-criterion='Spelling'])[2]//td[@data-score='0']" "xpath_element" should exist
    And "(//tr[@data-criterion='Spelling'])[2]//td[@data-score='3']" "xpath_element" should exist
    And "(//tr[@data-criterion='Spelling'])[2]//td[@data-score='7']" "xpath_element" should exist
    And "(//tr[@data-criterion='Spelling'])[2]//td[contains(., 'No errors')]" "xpath_element" should exist
    And "//tr[@data-criterion='Pictures']" "xpath_element" should exist

  @javascript
  Scenario: Duplicating a criterion copies exactly its levels
    When I go to "Test assignment 1 name" advanced grading definition page
    And I set the following fields to these values:
      | Name | Assignment 1 matrix |
    And I define the following matrix:
      | Spelling | Many errors | 0 | Few errors | 3 | No errors | 7 |
      | Pictures | None        | 0 | Some {a}   | 5 |           |   |
    And I click on "Duplicate criterion" "button" in the "Pictures" "table_row"
    And I press "Save as draft"
    Then "(//tr[@data-criterion='Pictures'])[2]//td[@data-score='5'][contains(., 'Some {a}')]" "xpath_element" should exist
    And "(//tr[@data-criterion='Pictures'])[2]//td[contains(@class, 'level')][3]" "xpath_element" should not exist
    And I should not see "undefined"
