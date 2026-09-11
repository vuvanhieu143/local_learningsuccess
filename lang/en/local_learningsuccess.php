<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * English strings for local_learningsuccess.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Learning Success & Intervention';
$string['learningsuccess'] = 'Learning Success';
$string['dashboard'] = 'Success Dashboard';
$string['classpulse'] = 'Class Pulse';
$string['todayspriorities'] = "Today's Priorities";
$string['studentsrequiringattention'] = 'Students Requiring Attention';
$string['nostudentsatrisk'] = 'No students currently require urgent intervention. Great work!';
$string['studentdetails'] = 'Student Success Details';
$string['refresh'] = 'Refresh';
$string['refresh_help'] = 'Refresh priorities list';
$string['ontrack'] = 'On track';
$string['whystudentatrisk'] = 'Why is this student at risk?';
$string['nononegativesignals'] = 'No negative signals recorded.';
$string['recommendedactions'] = 'Recommended Actions';
$string['applythisaction'] = 'Apply This Action';
$string['nopendingrecommendations'] = 'No pending recommendations.';
$string['interventionhistory'] = 'Intervention History & Outcome Tracking';
$string['nointerventionsfound'] = 'No intervention records found for this student.';
$string['actions'] = 'Actions';
$string['confirm_complete_title'] = 'Complete Intervention';
$string['confirm_complete_body'] = 'Are you sure you want to mark this intervention as completed?';
$string['confirm_dismiss_title'] = 'Dismiss Intervention';
$string['confirm_dismiss_body'] = 'Are you sure you want to dismiss this intervention?';
$string['intervention_created'] = 'Intervention recorded successfully.';
$string['intervention_completed'] = 'Intervention completed.';
$string['intervention_dismissed'] = 'Intervention dismissed.';
$string['placeholder_reason'] = 'e.g. Student inactive for 8 days';
$string['placeholder_recommended'] = 'e.g. Send check-in message';
$string['placeholder_actual'] = 'Notes on message sent or meeting scheduled';

// Status labels.
$string['status_healthy'] = 'Healthy';
$string['status_monitor'] = 'Monitor';
$string['status_atrisk'] = 'At Risk';
$string['status_critical'] = 'Critical';

// Actionability & Priority Work-Queue.
$string['priority_urgent'] = 'Urgent Action';
$string['priority_follow_up'] = 'Follow-up Due';
$string['priority_recommend'] = 'Recommended';
$string['priority_monitor'] = 'Monitor';
$string['priority_no_action'] = 'No Action Needed';
$string['action_review_followup'] = 'Review Follow-up';
$string['action_checkin'] = 'Check in';
$string['why_now'] = 'Why now?';
$string['primary_recommendation'] = 'Primary Recommendation';
$string['alternative_options'] = 'Other Options';


// Intervention types.
$string['type_contact'] = 'Direct Message';
$string['type_learning_resource'] = 'Recommend Learning Resource';
$string['type_missed_activity'] = 'Review Missed Activity';
$string['type_extension'] = 'Grant Extension';
$string['type_advisor_referral'] = 'Advisor Referral';
$string['type_other'] = 'Other Action';

// Intervention statuses.
$string['status_open'] = 'Open';
$string['status_in_progress'] = 'In Progress';
$string['status_completed'] = 'Completed';
$string['status_dismissed'] = 'Dismissed';

// Outcomes.
$string['outcome_improved'] = 'Improved';
$string['outcome_no_change'] = 'No Change';
$string['outcome_declined'] = 'Declined';
$string['outcome_unknown'] = 'Evaluating / Unknown';

// Signal messages.
$string['signal_inactivity_title'] = 'Course Inactivity';
$string['signal_inactivity_critical_desc'] = 'No course activity recorded for {$a} consecutive days.';
$string['signal_inactivity_warning_desc'] = 'Student has been inactive for {$a} days.';
$string['signal_inactivity_critical'] = 'No course activity for {$a} days';
$string['signal_inactivity_warning'] = 'Inactive for {$a} days';

$string['signal_overdue_title'] = 'Overdue Activities';
$string['signal_overdue_desc'] = 'Student has {$a} overdue assignment activities.';

$string['signal_grade_title'] = 'Course Assessment Performance';
$string['signal_grade_critical_desc'] = 'Current course grade is critically low at {$a}%.';
$string['signal_grade_warning_desc'] = 'Current course grade is below expectations at {$a}%.';
$string['signal_grade_decline'] = 'Assessment performance dropped by {$a}%';

$string['signal_completion_title'] = 'Module Completion Progress';
$string['signal_completion_critical_desc'] = 'Course completion rate is critically stalled at {$a}%.';
$string['signal_completion_warning_desc'] = 'Course completion progress is pacing slow at {$a}%.';
$string['signal_completion_low'] = 'Course completion rate is low ({$a}%)';

$string['signal_analytics_title'] = 'Moodle Analytics Risk Flag';
$string['signal_analytics_desc'] = 'Moodle Learning Analytics model ({$a}) flagged this student as potentially at risk.';

$string['signal_missed_activities'] = '{$a} required activities overdue or incomplete';
$string['signal_quiz_low'] = 'Recent quiz performance below passing threshold ({$a}%)';
$string['signal_quiz_retries'] = 'Repeated quiz attempts ({$a} attempts recorded)';

// Struggle Archetypes / Tags.
$string['struggle_disengaged'] = '👻 Disengaged';
$string['struggle_repeated_attempts'] = '🔄 Quiz Retries ({$a})';
$string['struggle_overdue'] = '⏳ Overdue Work ({$a})';
$string['struggle_pacing'] = '📉 Pacing Behind';
$string['struggle_on_track'] = '✅ On Track';

// Recommendation descriptions.
$string['recommend_contact'] = 'Send a personalized check-in message via Moodle messaging.';
$string['recommend_resource'] = 'Recommend remedial content or prerequisite modules.';
$string['recommend_extension'] = 'Offer an assignment deadline extension or makeup submission.';
$string['recommend_review_missing'] = 'Prompt the student to complete overdue assignments.';
$string['recommend_advisor'] = 'Refer student to academic advising or student support services.';

// Actions & Buttons.
$string['action_intervene'] = 'Take Action';
$string['action_create_intervention'] = 'Record Intervention';
$string['action_quick_contact'] = 'Quick Message';
$string['action_update_intervention'] = 'Update Intervention';
$string['action_complete'] = 'Mark as Completed';
$string['action_dismiss'] = 'Dismiss';
$string['action_view_student'] = 'View Student Insights';
$string['action_contact_student'] = 'Message Student';

// Group & Filter strings.
$string['filter_by_group'] = 'Filter by Group';
$string['all_groups'] = 'All Groups / Cohorts';

// Direct Messaging & Check-in strings.
$string['field_send_message'] = 'Send as direct message via Moodle Messaging';
$string['message_subject'] = 'Check-in from your teacher regarding course progress';
$string['default_checkin_message'] = 'Hi, I noticed you have not been active in our course recently. Please let me know if you need any help!';
$string['auto_evaluated_note'] = 'Auto-evaluated after {$a} days of intervention.';

// Success Stories & Celebrations.
$string['recent_success_stories'] = 'Recent Success Stories & Comebacks';
$string['no_success_stories_yet'] = 'No resolved interventions yet. Start reaching out to students to see their progress celebrated here!';
$string['success_story_desc'] = 'Improved after receiving support from teacher.';

// Empathetic Message Presets.
$string['click_preset_to_fill'] = 'Click a preset to quickly fill a warm message:';
$string['message_presets'] = 'Empathetic Message Presets';
$string['preset_empathy_title'] = '🌟 Encouraging Check-in';
$string['preset_empathy_text'] = 'Hi! I noticed you have been a bit quiet in our course recently. Is there anything difficult with the lessons this week? Feel free to reach out and let me know how I can help!';
$string['preset_resource_title'] = '💡 Practical Guidance';
$string['preset_resource_text'] = 'Hello! This week\'s module and assignment are very important and due soon. I have prepared some concise summary materials to help you review and submit on time!';
$string['preset_extension_title'] = '⏱ Gentle Extension Offer';
$string['preset_extension_text'] = 'Hello! I noticed you have not been able to submit the recent assignment yet. If you are experiencing unexpected personal or health issues, please reply and I can grant you a 2-day extension.';

// Signal-driven Message Templates (Teacher editable drafts).
$string['template_inactivity_title'] = 'Course Inactivity Check-in';
$string['template_inactivity_body'] = "Hi {firstname},\n\nI noticed you haven't been active in {coursename} recently.\nIs everything going okay? Let me know if you need any help getting back on track.";
$string['template_overdue_title'] = 'Overdue Activity Support';
$string['template_overdue_body'] = "Hi {firstname},\n\nI noticed you have some overdue activities in {coursename}.\nIf you're having trouble completing them, I can help you work out what to tackle first.";
$string['template_grade_decline_title'] = 'Assessment Support';
$string['template_grade_decline_body'] = "Hi {firstname},\n\nI noticed your recent assessment results have dropped in {coursename}.\nWould you like to discuss any areas where you're finding the course difficult?";
$string['template_general_title'] = 'General Progress Check-in';
$string['template_general_body'] = "Hi {firstname},\n\nI wanted to check in with you regarding your progress in {coursename}.\nPlease feel free to reply if there is anything I can do to support your learning.";

// Signal Dismissal & Teacher Overrides.
$string['dismiss_signal'] = 'Dismiss Signal';
$string['dismiss_reason_approved_leave'] = 'Student on approved leave';
$string['dismiss_reason_working_offline'] = 'Student working offline';
$string['dismiss_reason_false_positive'] = 'False positive indicator';
$string['dismiss_reason_not_relevant'] = 'Not currently relevant';
$string['dismiss_reason_other'] = 'Other reason';
$string['signal_dismissed_notice'] = 'Signal dismissed by teacher. It will remain suppressed unless conditions worsen.';


// Dashboard Metrics.
$string['metric_total_students'] = 'Enrolled Students';
$string['metric_avg_completion'] = 'Avg Completion';
$string['metric_active_interventions'] = 'Active Interventions';
$string['metric_resolved_interventions'] = 'Resolved (Improved)';

// Modal and Form fields.
$string['field_student'] = 'Student';
$string['field_type'] = 'Intervention Type';
$string['field_reason'] = 'Identified Need / Reason';
$string['field_recommended_action'] = 'Recommended Action';
$string['field_actual_action'] = 'Action Taken / Notes';
$string['field_status'] = 'Status';
$string['field_outcome'] = 'Outcome';

// Capabilities.
$string['learningsuccess:viewcourse'] = 'View Learning Success course dashboard';
$string['learningsuccess:viewstudent'] = 'View student success details and signals';
$string['learningsuccess:createintervention'] = 'Create student intervention records';
$string['learningsuccess:manageintervention'] = 'Manage and resolve intervention records';
$string['learningsuccess:viewreports'] = 'View learning success analytical reports';
$string['learningsuccess:manageconfig'] = 'Configure learning success plugin settings';

// Admin Settings.
$string['setting_enabled'] = 'Enable Learning Success';
$string['setting_enabled_desc'] = 'Enable or disable the Learning Success plugin globally.';
$string['setting_enable_classpulse'] = 'Enable Class Pulse';
$string['setting_enable_classpulse_desc'] = 'Show course-level pulse and aggregate health metrics.';
$string['setting_enable_interventions'] = 'Enable Intervention Tracking';
$string['setting_enable_interventions_desc'] = 'Allow teachers to record and track student intervention lifecycles.';
$string['setting_inactivity_threshold'] = 'Inactivity Threshold (Days)';
$string['setting_inactivity_threshold_desc'] = 'Number of days without course access before a student is flagged as inactive.';
$string['setting_grade_decline_threshold'] = 'Grade Decline Threshold (%)';
$string['setting_grade_decline_threshold_desc'] = 'Percentage drop between assessments to trigger a grade decline signal.';

// Tasks.
$string['task_refresh_student_data'] = 'Refresh student learning success cache and signals';
$string['task_process_followups'] = 'Process due intervention follow-ups and notify teachers';
$string['followup_notification_subject'] = 'Follow-up Due: Student {$a}';
$string['followup_notification_body'] = 'A scheduled follow-up for student {$a->student} in course {$a->course} is due today. Please review the student progress and record the outcome.';

// Privacy metadata strings.
$string['privacy:metadata:local_ls_intervention'] = 'Stores records of teacher interventions and before/after outcome snapshots.';
$string['privacy:metadata:local_ls_intervention:userid'] = 'The ID of the student receiving the intervention.';
$string['privacy:metadata:local_ls_intervention:courseid'] = 'The ID of the course in which the intervention took place.';
$string['privacy:metadata:local_ls_intervention:teacherid'] = 'The ID of the teacher recording the intervention.';
$string['privacy:metadata:local_ls_intervention:type'] = 'The categorization of the intervention.';
$string['privacy:metadata:local_ls_intervention:reason'] = 'The reason or explanation triggering the intervention.';
$string['privacy:metadata:local_ls_intervention:recommended_action'] = 'The action recommended by the system.';
$string['privacy:metadata:local_ls_intervention:actual_action'] = 'The notes or action recorded by the teacher.';
$string['privacy:metadata:local_ls_intervention:status'] = 'The current progress status of the intervention.';
$string['privacy:metadata:local_ls_intervention:outcome'] = 'The measured outcome of the intervention.';
$string['privacy:metadata:local_ls_intervention:before_snapshot'] = 'Snapshot of student metrics before the intervention.';
$string['privacy:metadata:local_ls_intervention:after_snapshot'] = 'Snapshot of student metrics after the intervention.';
$string['privacy:metadata:local_ls_intervention:timecreated'] = 'Timestamp when the record was created.';
$string['privacy:metadata:local_ls_intervention:timemodified'] = 'Timestamp when the record was last modified.';
$string['privacy:metadata:local_ls_intervention:completed_at'] = 'Timestamp when the intervention was completed.';
$string['privacy:metadata:local_ls_signal'] = 'Stores derived learning risk signals.';
$string['privacy:metadata:local_ls_signal:userid'] = 'The ID of the student.';
$string['privacy:metadata:local_ls_signal:courseid'] = 'The ID of the course.';
$string['privacy:metadata:local_ls_signal:signal_type'] = 'The type of signal (e.g. inactivity, completion).';
$string['privacy:metadata:local_ls_signal:severity'] = 'The severity level of the signal.';
$string['privacy:metadata:local_ls_signal:value'] = 'The metric value associated with the signal.';
$string['privacy:metadata:local_ls_signal:metadata'] = 'Additional contextual JSON metadata.';
$string['privacy:metadata:local_ls_signal:timecreated'] = 'Timestamp when the signal was recorded.';
$string['interventions'] = 'Interventions';
$string['signals'] = 'Signals';

// Security and access control strings.
$string['usernotenrolled'] = 'The requested user is not enrolled in this course.';
$string['nopermissiontoviewstudent'] = 'You do not have permission to view students outside your assigned groups.';
$string['nopermissiontoviewgroup'] = 'You do not have permission to view this group.';

