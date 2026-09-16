@local @local_learningsuccess
Feature: Complex multi-cohort data, multi-group, and multi-intervention tracking
  In order to effectively manage larger classes and complex cohort needs
  As an educator
  I need comprehensive analytics, multi-student tracking, and multi-record intervention histories to function seamlessly

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
      | student3 | Alice     | Wonder   | student3@example.com |
      | student4 | Charlie   | Brown    | student4@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           | timestart  |
      | teacher1 | BIO101 | editingteacher | 0          |
      | teacher2 | BIO101 | editingteacher | 0          |
      | student1 | BIO101 | student        | 1000000000 |
      | student2 | BIO101 | student        | 1000000000 |
      | student3 | BIO101 | student        | 1000000000 |
      | student4 | BIO101 | student        | 1000000000 |
    And the following "group members" exist:
      | user     | group |
      | teacher1 | GA    |
      | student1 | GA    |
      | student2 | GA    |
      | teacher2 | GB    |
      | student3 | GB    |
      | student4 | GB    |

  @javascript
  Scenario: Class Pulse widget aggregates metrics across multiple students and active interventions
    Given the following "local_learningsuccess > interventions" exist:
      | user     | course | teacher  | type              | status    | outcome  | reason           | actual_action                         |
      | student1 | BIO101 | teacher1 | CONTACT           | OPEN      | UNKNOWN  | Low attendance   | Scheduled meeting                     |
      | student2 | BIO101 | teacher1 | LEARNING_RESOURCE | COMPLETED | IMPROVED | Inactive 10 days | Sent revision guide                   |
      | student3 | BIO101 | teacher2 | EXTENSION         | FOLLOW_UP | UNKNOWN  | Missed deadline  | Extension granted                     |
    And I log in as "teacher1"
    And I am on "Biology 1" course homepage
    When I navigate to "Learning Success & Intervention" in current page administration
    Then I should see "Enrolled Students"
    And I should see "Active Interventions"
    And I should see "Recent Improvements"
    And I should see "Bob Builder"
    And I should see "Improved"

  @javascript
  Scenario: Student details displays multiple historical interventions with diverse statuses and outcomes
    Given the following "local_learningsuccess > interventions" exist:
      | user     | course | teacher  | type              | status    | outcome   | reason                  | actual_action                         |
      | student1 | BIO101 | teacher1 | CONTACT           | COMPLETED | IMPROVED  | Inactive for 14 days    | Direct chat discussion                |
      | student1 | BIO101 | teacher1 | LEARNING_RESOURCE | COMPLETED | DECLINED  | Failed formative test   | Recommended supplementary modules     |
      | student1 | BIO101 | teacher1 | EXTENSION         | OPEN      | UNKNOWN   | Assignment 1 unfinished | Granted 5-day extension               |
    And I log in as "teacher1"
    And I am on "Biology 1" course homepage
    And I navigate to "Learning Success & Intervention" in current page administration
    When I click on "View Student Insights" "link" in the "Sam Student" "list_item"
    Then I should see "Intervention History & Outcome Tracking"
    And I should see "Direct Message"
    And I should see "Recommend Learning Resource"
    And I should see "Grant Extension"
    And I should see "Improved"
    And I should see "Declined"
    And I should see "Mark as Completed"

  @javascript
  Scenario: Multiple teachers collaborate on cross-cohort student interventions
    Given the following "local_learningsuccess > interventions" exist:
      | user     | course | teacher  | type    | status | reason          | actual_action            |
      | student1 | BIO101 | teacher1 | CONTACT | OPEN   | Disengaged week | Initial check-in message |
    And I log in as "teacher2"
    And I am on "Biology 1" course homepage
    And I navigate to "Learning Success & Intervention" in current page administration
    And I click on "View Student Insights" "link" in the "Sam Student" "list_item"
    When I click on "Mark as Completed" "button"
    And I click on "Mark as Completed" "button" in the "Complete Intervention" "dialogue"
    Then I should not see "Mark as Completed"

  @javascript
  Scenario: Teacher records a secondary intervention for a student with existing history
    Given the following "local_learningsuccess > interventions" exist:
      | user     | course | teacher  | type    | status    | outcome  | reason         | actual_action       |
      | student1 | BIO101 | teacher1 | CONTACT | COMPLETED | IMPROVED | First outreach | Resolved attendance |
    And I log in as "teacher1"
    And I am on "Biology 1" course homepage
    And I navigate to "Learning Success & Intervention" in current page administration
    And I click on "View Student Insights" "link" in the "Sam Student" "list_item"
    And I click on "Record Intervention" "button"
    When I set the field "Identified Need / Reason" to "Second struggle detected on assignment"
    And I set the field "Action Taken / Notes" to "Arranged 1-on-1 tutoring support"
    And I press "Save changes"
    Then I should see "Intervention History & Outcome Tracking"
    And I should see "First outreach"

  @javascript
  Scenario: Dashboard highlights multiple improved students in the recent improvements section
    Given the following "local_learningsuccess > interventions" exist:
      | user     | course | teacher  | type    | status    | outcome  | reason           | actual_action      |
      | student1 | BIO101 | teacher1 | CONTACT | COMPLETED | IMPROVED | Low engagement   | Follow-up coaching |
      | student2 | BIO101 | teacher1 | CONTACT | COMPLETED | IMPROVED | Overdue lab work | Lab hours makeup   |
    And I log in as "teacher1"
    And I am on "Biology 1" course homepage
    When I navigate to "Learning Success & Intervention" in current page administration
    Then I should see "Recent Improvements"
    And I should see "Sam Student"
    And I should see "Bob Builder"
