@mod @mod_customcert @customcertelement_conditionaltext
Feature: Configure a conditional text element
  In order to print different text depending on the learner
  As a teacher
  I need to configure rules on a conditional text element

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "groups" exist:
      | name     | course | idnumber |
      | Advanced | C1     | G1       |
    And the following "activities" exist:
      | activity   | name                 | intro                      | course | idnumber    |
      | customcert | Custom certificate 1 | Custom certificate 1 intro | C1     | customcert1 |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Custom certificate 1"
    And I navigate to "Edit certificate" in current page administration

  @javascript
  Scenario: Add, edit and delete rules
    When I add the element "Conditional text" to page "1" of the "Custom certificate 1" certificate template
    And I set the following fields to these values:
      | Element name       | Classification   |
      | ruletype[0]        | Grade            |
      | rulegradeop[0]     | ≥                |
      | rulegradevalue[0]  | 90               |
      | ruletext[0]        | WITH DISTINCTION |
    Then "rulegradeitem[0]" "select" should be visible
    And "ruleprofilefield[0]" "select" should not be visible
    And "rulegroupid[0]" "select" should not be visible
    And "rulebadgeid[0]" "select" should not be visible
    And I press "Add another rule"
    And I set the following fields to these values:
      | ruletype[1]    | Group          |
      | rulegroupid[1] | Advanced       |
      | ruletext[1]    | Advanced level |
      | defaulttext    | Attended       |
    And "rulegroupid[1]" "select" should be visible
    And "rulenegate[1]" "checkbox" should be visible
    And "rulegradeitem[1]" "select" should not be visible
    And I press "Save changes"
    And I should see "Classification" in the "elementstable" "table"
    And I click on ".edit-icon" "css_element" in the "Classification" "table_row"
    And the following fields match these values:
      | ruletype[0]       | Grade            |
      | rulegradevalue[0] | 90               |
      | ruletext[0]       | WITH DISTINCTION |
      | ruletype[1]       | Group            |
      | rulegroupid[1]    | Advanced         |
      | ruletext[1]       | Advanced level   |
      | defaulttext       | Attended         |
    And I press "Delete rule 1"
    And I press "Save changes"
    And I click on ".edit-icon" "css_element" in the "Classification" "table_row"
    And the following fields match these values:
      | ruletype[0]    | Group          |
      | rulegroupid[0] | Advanced       |
      | ruletext[0]    | Advanced level |
    And "ruletype[1]" "select" should not exist

  Scenario: Invalid rules are rejected
    When I add the element "Conditional text" to page "1" of the "Custom certificate 1" certificate template
    And I set the following fields to these values:
      | ruletype[0]       | Grade |
      | rulegradevalue[0] | abc   |
    And I press "Save changes"
    Then I should see "Enter a number."
    And I set the following fields to these values:
      | ruletype[0] | Group |
    And I press "Save changes"
    And I should see "Choose an option."

  @javascript
  Scenario: Load a ready-made example
    When I add the element "Conditional text" to page "1" of the "Custom certificate 1" certificate template
    And I click on "Load an example" "button"
    And I click on "Distinction / Merit / Pass" "link"
    Then I should see "Example loaded"
    And the following fields match these values:
      | ruletext[0]       | WITH DISTINCTION |
      | rulegradevalue[1] | 75               |
      | ruletext[2]       | PASS             |
    And I set the field "Element name" to "Classification example"
    And I press "Save changes"
    And I should see "Classification example" in the "elementstable" "table"
