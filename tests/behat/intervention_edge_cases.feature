@local @local_learningsuccess
Feature: Learning success dashboard edge cases, access control, and dismissals
  In order to ensure platform reliability and security
  As an educator
  I need edge cases such as group isolation, dismissal workflows, and authorization rules to be strictly enforced

  Background:
    Given the following "courses" exist:
      | fullname  | shortname | category | groupmode | startdate  |
      | Biology 1 | BIO101    | 0        | 1         | 1000000000 |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group A | BIO101 | GA       |
      | Group B | BIO101 | GB       |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
      | teacher2 | Tina      | Teacher2 | teacher2@example.com |
      | student1 | Sam       | Student  | student1@example.com |
      | student2 | Bob       | Builder  | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           | timestart  |
      | teacher1 | BIO101 | editingteacher | 0          |
      | teacher2 | BIO101 | teacher        | 0          |
      | student1 | BIO101 | student        | 1000000000 |
      | student2 | BIO101 | student        | 1000000000 |
    And the following "group members" exist:
      | user     | group |
      | teacher1 | GA    |
      | teacher2 | GA    |
      | student1 | GA    |
      | student2 | GB    |

  @javascript
  Scenario: Teacher dismisses an active intervention from student details
    Given the following "local_learningsuccess > interventions" exist:
      | user     | course | teacher  | type    | status    | reason                |
      | student1 | BIO101 | teacher1 | CONTACT | CONTACTED | Initial check-in sent |
    And I log in as "teacher1"
    And I am on "Biology 1" course homepage
    And I navigate to "Learning Success & Intervention" in current page administration
    And I click on "View Student Insights" "link" in the "Sam Student" "list_item"
    When I click on "Dismiss" "button" in the "Initial check-in sent" "table_row"
    And I click on "Dismiss" "button" in the "Dismiss Intervention" "dialogue"
    Then I should not see "Mark as Completed"

  @javascript
  Scenario: Quick contact modal preset templates and direct message execution
    Given I log in as "teacher1"
    And I am on "Biology 1" course homepage
    And I navigate to "Learning Success & Intervention" in current page administration
    And I click on "Check in" "button" in the "Sam Student" "list_item"
    When I press "🌟 Encouraging Check-in"
    And I press "Save changes"
    Then I should see "Today's Priorities"
    And I should see "Sam Student"

  @javascript
  Scenario: Quick contact modal extension preset populates action note
    Given I log in as "teacher1"
    And I am on "Biology 1" course homepage
    And I navigate to "Learning Success & Intervention" in current page administration
    And I click on "Record Intervention" "button" in the "Sam Student" "list_item"
    When I press "⏱ Gentle Extension Offer"
    And I press "Save changes"
    Then I should see "Today's Priorities"
    And I should see "Sam Student"

  @javascript
  Scenario: Student cannot access the teacher learning success dashboard
    Given I log in as "student1"
    When I am on "Biology 1" course homepage
    Then "Learning Success & Intervention" "link" should not exist in the "page" "region"

  @javascript
  Scenario: Non-editing teacher in separate groups course only sees students from assigned group
    Given I log in as "teacher2"
    And I am on "Biology 1" course homepage
    When I navigate to "Learning Success & Intervention" in current page administration
    Then I should see "Sam Student"
    And I should not see "Bob Builder"

  @javascript
  Scenario: Teacher views group filter selector on dashboard in course with groups
    Given I log in as "teacher1"
    And I am on "Biology 1" course homepage
    When I navigate to "Learning Success & Intervention" in current page administration
    Then "Filter by Group" "select" should exist
    And I should see "All Groups / Cohorts"

  @javascript
  Scenario: Dashboard displays recent improvements for successfully resolved interventions
    Given the following "local_learningsuccess > interventions" exist:
      | user     | course | teacher  | type    | status    | outcome  | reason           | actual_action                         |
      | student1 | BIO101 | teacher1 | CONTACT | COMPLETED | IMPROVED | Inactive 14 days | Sent direct check-in message via Moodle |
    And I log in as "teacher1"
    And I am on "Biology 1" course homepage
    When I navigate to "Learning Success & Intervention" in current page administration
    Then I should see "Recent Improvements"
    And I should see "Sam Student"
    And I should see "Improved"

  @javascript
  Scenario: Priority queue displays follow-up review for pending interventions
    Given the following "local_learningsuccess > interventions" exist:
      | user     | course | teacher  | type    | status    | reason             |
      | student1 | BIO101 | teacher1 | CONTACT | FOLLOW_UP | Needs weekly check |
    And I log in as "teacher1"
    And I am on "Biology 1" course homepage
    When I navigate to "Learning Success & Intervention" in current page administration
    Then I should see "Review Follow-up"

  @javascript
  Scenario: Dashboard displays empty state when no students require urgent intervention
    Given the following "courses" exist:
      | fullname  | shortname | category |
      | Chemistry | CHEM101   | 0        |
    And the following "course enrolments" exist:
      | user     | course  | role           |
      | teacher1 | CHEM101 | editingteacher |
    And I log in as "teacher1"
    And I am on "Chemistry" course homepage
    When I navigate to "Learning Success & Intervention" in current page administration
    Then I should see "No students currently require urgent intervention. Great work!"

  @javascript
  Scenario: Student details displays empty state when no interventions exist
    Given I log in as "teacher1"
    And I am on "Biology 1" course homepage
    And I navigate to "Learning Success & Intervention" in current page administration
    When I click on "View Student Insights" "link" in the "Sam Student" "list_item"
    Then I should see "No intervention records found for this student."

  @javascript
  Scenario: Student details displays intervention history with tracked outcomes
    Given the following "local_learningsuccess > interventions" exist:
      | user     | course | teacher  | type    | status    | outcome  | reason          |
      | student1 | BIO101 | teacher1 | CONTACT | COMPLETED | DECLINED | Low quiz scores |
    And I log in as "teacher1"
    And I am on "Biology 1" course homepage
    And I navigate to "Learning Success & Intervention" in current page administration
    When I click on "View Student Insights" "link" in the "Sam Student" "list_item"
    Then I should see "Intervention History & Outcome Tracking"
    And I should see "Declined"
