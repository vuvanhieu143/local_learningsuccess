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

namespace local_learningsuccess\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_learningsuccess\local\intervention\intervention_manager;
use local_learningsuccess\local\service\student_success_service;

/**
 * External Web Service API for Learning Success dashboard and interventions.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard_exporter extends external_api {

    /**
     * Parameters for get_dashboard_data.
     */
    public static function get_dashboard_data_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID to fetch dashboard for'),
        ]);
    }

    /**
     * Fetch dashboard pulse and priority student data.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_dashboard_data(int $courseid): array {
        $params = self::validate_parameters(self::get_dashboard_data_parameters(), ['courseid' => $courseid]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/learningsuccess:viewcourse', $context);

        $service = new student_success_service();
        $summary = $service->get_course_summary($params['courseid']);
        $priorities = $service->get_priority_students($params['courseid']);

        return [
            'total_students' => $summary['total_students'],
            'healthy_count' => $summary['healthy_count'],
            'monitor_count' => $summary['monitor_count'],
            'atrisk_count' => $summary['atrisk_count'],
            'critical_count' => $summary['critical_count'],
            'active_interventions' => $summary['active_interventions'],
            'resolved_interventions' => $summary['resolved_interventions'],
            'priorities' => array_map(function ($student) {
                return [
                    'userid' => $student['userid'],
                    'fullname' => $student['fullname'],
                    'status' => $student['status'],
                    'status_label' => $student['status_label'],
                    'risk_score' => $student['risk_score'],
                    'signal_count' => $student['signal_count'],
                    'top_reason' => !empty($student['signals']) ? $student['signals'][0]['message'] : '',
                ];
            }, $priorities),
        ];
    }

    /**
     * Returns description of get_dashboard_data return value.
     */
    public static function get_dashboard_data_returns(): external_single_structure {
        return new external_single_structure([
            'total_students' => new external_value(PARAM_INT, 'Total enrolled students'),
            'healthy_count' => new external_value(PARAM_INT, 'Healthy students count'),
            'monitor_count' => new external_value(PARAM_INT, 'Students under monitoring'),
            'atrisk_count' => new external_value(PARAM_INT, 'At-risk students count'),
            'critical_count' => new external_value(PARAM_INT, 'Critical students count'),
            'active_interventions' => new external_value(PARAM_INT, 'Active interventions count'),
            'resolved_interventions' => new external_value(PARAM_INT, 'Resolved interventions count'),
            'priorities' => new external_multiple_structure(
                new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'Student User ID'),
                    'fullname' => new external_value(PARAM_TEXT, 'Full student name'),
                    'status' => new external_value(PARAM_ALPHA, 'Status key'),
                    'status_label' => new external_value(PARAM_TEXT, 'Localized status label'),
                    'risk_score' => new external_value(PARAM_INT, 'Numeric risk score (0-100)'),
                    'signal_count' => new external_value(PARAM_INT, 'Number of active signals'),
                    'top_reason' => new external_value(PARAM_TEXT, 'Primary reason text'),
                ])
            ),
        ]);
    }

    /**
     * Parameters for create_intervention.
     */
    public static function create_intervention_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'userid' => new external_value(PARAM_INT, 'Student ID'),
            'type' => new external_value(PARAM_ALPHAEXT, 'Intervention type'),
            'reason' => new external_value(PARAM_TEXT, 'Reason for intervention', VALUE_DEFAULT, ''),
            'recommended_action' => new external_value(PARAM_TEXT, 'Recommended action', VALUE_DEFAULT, ''),
            'actual_action' => new external_value(PARAM_TEXT, 'Actual action taken', VALUE_DEFAULT, ''),
            'send_message' => new external_value(PARAM_BOOL, 'Whether to dispatch via Moodle Messaging', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Create an intervention record.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $type
     * @param string $reason
     * @param string $recommendedaction
     * @param string $actualaction
     * @param bool $sendmessage
     * @return array
     */
    public static function create_intervention(
        int $courseid,
        int $userid,
        string $type,
        string $reason = '',
        string $recommendedaction = '',
        string $actualaction = '',
        bool $sendmessage = false
    ): array {
        global $USER;

        $params = self::validate_parameters(self::create_intervention_parameters(), [
            'courseid' => $courseid,
            'userid' => $userid,
            'type' => $type,
            'reason' => $reason,
            'recommended_action' => $recommendedaction,
            'actual_action' => $actualaction,
            'send_message' => $sendmessage,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/learningsuccess:createintervention', $context);

        \local_learningsuccess\local\helper\access_helper::validate_student_access(
            $params['courseid'],
            $context,
            (int) $params['userid']
        );

        $manager = new intervention_manager();
        $id = $manager->create(
            $params['userid'],
            $params['courseid'],
            $USER->id,
            $params['type'],
            $params['reason'],
            $params['recommended_action'],
            $params['actual_action'],
            $params['send_message']
        );

        return [
            'id' => $id,
            'success' => true,
            'message' => get_string('action_create_intervention', 'local_learningsuccess'),
        ];
    }

    /**
     * Return value for create_intervention.
     */
    public static function create_intervention_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Created intervention ID'),
            'success' => new external_value(PARAM_BOOL, 'Operation success flag'),
            'message' => new external_value(PARAM_TEXT, 'Result message'),
        ]);
    }

    /**
     * Parameters for complete_intervention.
     */
    public static function complete_intervention_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Intervention ID'),
            'actual_action' => new external_value(PARAM_TEXT, 'Final teacher note or resolution', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Complete an intervention.
     *
     * @param int $id
     * @param string $actualaction
     * @return array
     */
    public static function complete_intervention(int $id, string $actualaction = ''): array {
        global $DB;

        $params = self::validate_parameters(self::complete_intervention_parameters(), [
            'id' => $id,
            'actual_action' => $actualaction,
        ]);

        list($record, $course, $context) = \local_learningsuccess\local\helper\access_helper::validate_intervention_access($params['id']);

        $manager = new intervention_manager();
        $success = $manager->complete($params['id'], $params['actual_action']);

        $updated = $DB->get_record('local_ls_intervention', ['id' => $params['id']], '*', MUST_EXIST);

        return [
            'id' => $updated->id,
            'status' => $updated->status,
            'outcome' => $updated->outcome,
            'success' => $success,
        ];
    }

    /**
     * Return value for complete_intervention.
     */
    public static function complete_intervention_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Intervention ID'),
            'status' => new external_value(PARAM_ALPHA, 'Status string'),
            'outcome' => new external_value(PARAM_ALPHA, 'Outcome classification'),
            'success' => new external_value(PARAM_BOOL, 'Operation success status'),
        ]);
    }

    /**
     * Parameters for dismiss_intervention.
     */
    public static function dismiss_intervention_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Intervention ID'),
        ]);
    }

    /**
     * Dismiss an intervention record.
     *
     * @param int $id
     * @return array
     */
    public static function dismiss_intervention(int $id): array {
        global $DB;

        $params = self::validate_parameters(self::dismiss_intervention_parameters(), [
            'id' => $id,
        ]);

        list($record, $course, $context) = \local_learningsuccess\local\helper\access_helper::validate_intervention_access($params['id']);

        $manager = new intervention_manager();
        $success = $manager->dismiss($params['id']);

        $updated = $DB->get_record('local_ls_intervention', ['id' => $params['id']], '*', MUST_EXIST);

        return [
            'id' => $updated->id,
            'status' => $updated->status,
            'success' => $success,
        ];
    }

    /**
     * Return value for dismiss_intervention.
     */
    public static function dismiss_intervention_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Intervention ID'),
            'status' => new external_value(PARAM_ALPHA, 'Status string'),
            'success' => new external_value(PARAM_BOOL, 'Operation success status'),
        ]);
    }
}

