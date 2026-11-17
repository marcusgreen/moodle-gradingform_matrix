@gradingform @gradingform_matrix @javascript
Feature: Matrix text in several languages
  In order to grade in a course taught in several languages
  As a teacher
  I need criteria and levels written for the multi-language filter to show in the reader's language

  Background:
    Given the "multilang" filter is "on"
    And the "multilang" filter applies to "content and headings"
    And the following "users" exist:
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

  Scenario: Teacher and student see only their own language
    Given I log in as "teacher1"
    And I change window size to "large"
    And I am on "Course 1" course homepage with editing mode on
    And I go to "Test assignment 1" advanced grading definition page
    And I set the following fields to these values:
      | Name | Assignment 1 matrix |
    And I define the following matrix:
      | <span lang="en" class="multilang">Spelling</span><span lang="de" class="multilang">Rechtschreibung</span> | <span lang="en" class="multilang">Weak</span><span lang="de" class="multilang">Schwach</span> | 0 | <span lang="en" class="multilang">Strong</span><span lang="de" class="multilang">Stark</span> | 1 |
    And I press "Save grading form and make it ready"
    When I go to "Student 1" "Test assignment 1" activity advanced grading page
    Then I should see "Spelling"
    And I should see "Strong"
    And I should not see "Rechtschreibung"
    And I should not see "Stark"
    And I grade by filling the matrix with:
      | Spelling | 1 | Well done |
    And I save the advanced grading form
    And I log out
    And I am on the "Test assignment 1" "assign activity" page logged in as student1
    And I should see "Spelling" in the ".feedback" "css_element"
    And I should see "Strong" in the ".feedback" "css_element"
    And I should see "Well done" in the ".feedback" "css_element"
    And I should not see "Rechtschreibung"
    And I should not see "Stark"
