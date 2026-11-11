@mod @mod_customcert @customcertelement @customcertelement_conditionaltext
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

  @javascript
  Scenario Outline: Each condition type shows its own fields
    When I add the element "Conditional text" to page "1" of the "Custom certificate 1" certificate template
    And I set the field "ruletype[0]" to "<type>"
    Then "<field>" "<fieldtype>" should be visible
    And "<hidden>" "<hiddentype>" should not be visible

    Examples:
      | type                        | field                 | fieldtype | hidden              | hiddentype |
      | Grade                       | rulegradevalue[0]     | field     | rulegroupid[0]      | select     |
      | Course progress (%)         | ruleprogressvalue[0]  | field     | rulegradeitem[0]    | select     |
      | Activity completion         | rulecmid[0]           | select    | rulegradeitem[0]    | select     |
      | User profile field          | ruleprofilefield[0]   | select    | rulegradeitem[0]    | select     |
      | Group                       | rulegroupid[0]        | select    | rulecohortid[0]     | select     |
      | Cohort                      | rulecohortid[0]       | select    | rulegroupid[0]      | select     |
      | Role in the course          | ruleroleid[0]         | select    | rulegroupid[0]      | select     |
      | Badge earned                | rulebadgeid[0]        | select    | ruleroleid[0]       | select     |
      | Competency achieved         | rulecompetencyid[0]   | select    | rulebadgeid[0]      | select     |
      | Enrolment method            | ruleenrol[0]          | select    | rulecmid[0]         | select     |
      | Date                        | ruledatesource[0]     | select    | rulegradeitem[0]    | select     |
      | Time spent in the course    | rulehoursvalue[0]     | field     | ruledaysvalue[0]    | field      |
      | Days to complete the course | ruledaysvalue[0]      | field     | rulehoursvalue[0]   | field      |
      | Always                      | ruletext[0]           | field     | rulenegate[0]       | checkbox   |

  @javascript
  Scenario: Invert and combine with the next rule
    When I add the element "Conditional text" to page "1" of the "Custom certificate 1" certificate template
    And I set the following fields to these values:
      | Element name      | Combined |
      | ruletype[0]       | Grade    |
      | rulegradevalue[0] | 90       |
      | rulecombine[0]    | Yes: the next rule must ALSO be met (AND) |
    Then "ruletext[0]" "field" should not be visible
    And I press "Add another rule"
    And I set the following fields to these values:
      | ruletype[1]    | Group            |
      | rulegroupid[1] | Advanced         |
      | rulenegate[1]  | 1                |
      | ruletext[1]    | Top, not advanced |
    And I press "Save changes"
    And I click on ".edit-icon" "css_element" in the "Combined" "table_row"
    And the following fields match these values:
      | rulecombine[0] | Yes: the next rule must ALSO be met (AND) |
      | rulenegate[0]  | 0                 |
      | rulenegate[1]  | 1                 |
      | rulegroupid[1] | Advanced          |
      | ruletext[1]    | Top, not advanced |
    And "ruletext[0]" "field" should not be visible

  Scenario: A dangling AND on the last rule is rejected
    When I add the element "Conditional text" to page "1" of the "Custom certificate 1" certificate template
    And I set the following fields to these values:
      | ruletype[0]    | Always                                    |
      | rulecombine[0] | Yes: the next rule must ALSO be met (AND) |
    And I press "Save changes"
    Then I should see "The last rule cannot be combined with a next rule."

  @javascript
  Scenario: The group example preselects the course group
    When I add the element "Conditional text" to page "1" of the "Custom certificate 1" certificate template
    And I click on "Load an example" "button"
    And I click on "By group" "link"
    Then the following fields match these values:
      | ruletype[0]    | Group          |
      | rulegroupid[0] | Advanced       |
      | ruletext[0]    | Advanced level |
      | defaulttext    | Standard level |

  @javascript
  Scenario: The condition help explains every type
    When I add the element "Conditional text" to page "1" of the "Custom certificate 1" certificate template
    And I click on "Help with Condition" "icon"
    Then I should see "Always" in the ".popover-body" "css_element"
    And I should see "matches every learner" in the ".popover-body" "css_element"
