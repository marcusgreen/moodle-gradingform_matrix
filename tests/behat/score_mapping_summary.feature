@gradingform @gradingform_matrix @javascript
Feature: Score to grade summary in the matrix editor
  In order to avoid unexpected grades
  As a teacher
  I need to see what grade the lowest and highest possible totals give while I edit a matrix

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
    And the following "activities" exist:
      | activity | name              | intro | course | idnumber | advancedgradingmethod_submissions |
      | assign   | Test assignment 1 | Test  | C1     | assign1  | matrix                            |

  Scenario: The summary follows score and option changes and is shown again after saving
    Given I log in as "teacher1"
    And I change window size to "large"
    And I am on "Course 1" course homepage with editing mode on
    And I go to "Test assignment 1" advanced grading definition page
    And I set the following fields to these values:
      | Name | Assignment 1 matrix |
    When I define the following matrix:
      | Criterion 1 | Level 11 | 1 | Level 12 | 2 | Level 13 | 5 |
      | Criterion 2 | Level 21 | 2 | Level 22 | 5 |          |   |
    Then I should see "Lowest possible total: 3 points, which gives 30% of the maximum grade. Highest possible total: 10 points, which gives the maximum grade."
    # Levels can only be found by their text once the form has been saved and reopened.
    And I press "Save as draft"
    And I go to "Test assignment 1" advanced grading definition page
    And I should see "Lowest possible total: 3 points, which gives 30% of the maximum grade."
    And I replace "5" matrix level with "15" in "Criterion 1" criterion
    And I should see "Lowest possible total: 3 points, which gives 15% of the maximum grade. Highest possible total: 20 points"
    And I set the field "Calculate grade having a minimum score of the minimum achievable grade for the grading form" to "0"
    And I should see "Lowest possible total: 3 points, which gives 0% of the maximum grade."
    And I press "Save grading form and make it ready"
    And I go to "Test assignment 1" advanced grading definition page
    And I should see "Lowest possible total: 3 points, which gives 0% of the maximum grade. Highest possible total: 20 points"
