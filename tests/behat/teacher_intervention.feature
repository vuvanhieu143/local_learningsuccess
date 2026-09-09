@local @local_learningsuccess
Feature: Teacher views dashboard, reviews student signals, and executes intervention
  In order to prevent student attrition and support struggling learners
  As a teacher
  I need to inspect the Learning Success dashboard and record measurable interventions

  Background:
    Given the following "courses" exist:
      | fullname  | shortname | category |
      | Biology 1 | BIO101    | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | BIO101 | editingteacher |
      | student1 | BIO101 | student        |

  @javascript
  Scenario: Teacher navigates to dashboard and records an intervention from priority queue
    Given I log in as "teacher1"
    And I am on "Biology 1" course homepage
    When I follow "Learning Success & Intervention"
    Then I should see "Class Pulse"
    And I should see "Today's Priorities"
    And I should see "Sam Student"
    When I click on "Record Intervention" "button" in the "Sam Student" "list_item"
    Then I should see "Record Intervention: Sam Student"
    When I set the field "Identified Need / Reason" to "Student inactive for 8 days"
    And I set the field "Action Taken / Notes" to "Sent direct check-in message via Moodle"
    And I press "Save changes"
    Then I should see "Today's Priorities"
    And I should see "Sam Student"

  @javascript
  Scenario: Teacher views student insights and applies recommended action
    Given I log in as "teacher1"
    And I am on "Biology 1" course homepage
    When I follow "Learning Success & Intervention"
    And I click on "View Student Insights" "link" in the "Sam Student" "list_item"
    Then I should see "Sam Student"
    And I should see "Why is this student at risk?"
    And I should see "Recommended Actions"
    And I should see "Intervention History & Outcome Tracking"
    When I press "Apply This Action"
    Then I should see "Record Intervention: Sam Student"
    When I set the field "Action Taken / Notes" to "Scheduled individual catch-up session"
    And I press "Save changes"
    Then I should see "Intervention History & Outcome Tracking"

  @javascript
  Scenario: Teacher completes an active intervention from student details
    Given the following "local_learningsuccess > interventions" exist:
      | user     | course | teacher  | type    | status | reason                |
      | student1 | BIO101 | teacher1 | CONTACT | OPEN   | Initial check-in sent |
    And I log in as "teacher1"
    And I am on "Biology 1" course homepage
    When I follow "Learning Success & Intervention"
    And I click on "View Student Insights" "link" in the "Sam Student" "list_item"
    Then I should see "Intervention History & Outcome Tracking"
    And I should see "Initial check-in sent"
    When I click on "Mark as Completed" "button"
    And I click on "Mark as Completed" "button" in the "Complete Intervention" "dialogue"
    Then I should not see "Mark as Completed"
