@gradingform @gradingform_matrix @javascript
Feature: Download per-criterion grading results
  In order to keep records of how each criterion was marked
  As a teacher
  I need to download the level, points and remark for each criterion

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name              | intro | course | idnumber | advancedgradingmethod_submissions |
      | assign   | Test assignment 1 | Test  | C1     | assign1  | matrix                            |

  Scenario: Open the results page from the grading form editor
    Given I log in as "teacher1"
    And I change window size to "large"
    And I am on "Course 1" course homepage with editing mode on
    And I go to "Test assignment 1" advanced grading definition page
    And I set the following fields to these values:
      | Name | Assignment 1 matrix |
    And I define the following matrix:
      | Criterion 1 | Level 11 | 0 | Level 12 | 1 |
      | Criterion 2 | Level 21 | 0 | Level 22 | 1 |
    And I press "Save grading form and make it ready"
    And I go to "Student 1" "Test assignment 1" activity advanced grading page
    And I grade by filling the matrix with:
      | Criterion 1 | 1 | Well done |
      | Criterion 2 | 0 |           |
    And I save the advanced grading form
    When I go to "Test assignment 1" advanced grading definition page
    And I click on "Download results" "link"
    Then I should see "Grading results" in the "h2" "css_element"
    And I should see "Download the current grade of everyone graded with this form"
    And "Download" "button" should exist
    And I should not see "can't be matched to users"
