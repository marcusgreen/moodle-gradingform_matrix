@gradingform @gradingform_matrix @javascript @accessibility
Feature: The matrix is accessible wherever it is shown
  In order to use the matrix with assistive technology
  As a teacher or a student
  I need the editor, the grading form and the graded form to meet accessibility standards

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

  Scenario: Edit, grade with and view a matrix
    Given I log in as "teacher1"
    And I change window size to "large"
    And I am on "Course 1" course homepage with editing mode on
    And I go to "Test assignment 1" advanced grading definition page
    And I set the following fields to these values:
      | Name | Assignment 1 matrix |
    And I define the following matrix:
      | Criterion 1 | Level 11 | 0 | Level 12 | 1 | Level 13 | 2 |
      | Criterion 2 | Level 21 | 0 | Level 22 | 1 | Level 23 | 2 |
    # The editor.
    And the "#matrix-matrix" "css_element" should meet accessibility standards
    And I press "Save grading form and make it ready"
    # The preview on the management page.
    And the ".gradingform_matrix" "css_element" should meet accessibility standards
    # The grading form.
    And I go to "Student 1" "Test assignment 1" activity advanced grading page
    And the ".gradingform_matrix" "css_element" should meet accessibility standards
    And I grade by filling the matrix with:
      | Criterion 1 | 2 | Well argued |
      | Criterion 2 | 1 |             |
    And I save the advanced grading form
    And I log out
    # The graded form, as the student sees it.
    When I am on the "Test assignment 1" "assign activity" page logged in as student1
    Then I should see "Well argued"
    And the ".gradingform_matrix" "css_element" should meet accessibility standards
