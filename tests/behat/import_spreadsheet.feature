@gradingform @gradingform_matrix @javascript @_file_upload
Feature: Import grading form criteria from a spreadsheet
  In order to build a grading form quickly
  As a teacher
  I need to import its criteria and levels from a CSV file or a spreadsheet

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

  Scenario Outline: Import a <type> file into a new grading form
    Given I log in as "teacher1"
    And I change window size to "large"
    And I am on "Course 1" course homepage with editing mode on
    And I go to "Test assignment 1" advanced grading definition page
    And I click on "Import" "link"
    When I upload "grade/grading/form/matrix/tests/fixtures/spreadsheet_import.<type>" file to "File to import" filemanager
    And I press "Import"
    Then I should see "Imported 2 criteria"
    And the field "Name" matches value "spreadsheet_import"
    And I should see "Clear position"
    And I should see "Use of evidence"
    And I should see "Explicit"
    And I should see "Limited"

    Examples:
      | type |
      | csv  |
      | xlsx |
