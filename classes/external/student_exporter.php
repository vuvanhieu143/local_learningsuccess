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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_learningsuccess\local\helper\access_helper;
use local_learningsuccess\local\service\student_success_service;

/**
 * External Web Service exporter providing student detail breakdown and explanation summary.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_exporter extends external_api {
    /**
     * Parameters for get_student_detail.
     */
    public static function get_student_detail_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'userid' => new external_value(PARAM_INT, 'Target Student User ID'),
        ]);
    }

    /**
     * Retrieve complete student explanation and intervention summary.
     *
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    public static function get_student_detail(int $courseid, int $userid): array {
        $params = self::validate_parameters(self::get_student_detail_parameters(), [
            'courseid' => $courseid,
            'userid' => $userid,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/learningsuccess:viewstudent', $context);

        // Security check: validate enrollment and SEPARATEGROUPS isolation.
        access_helper::validate_student_access($params['courseid'], $context, $params['userid']);

        $service = new student_success_service();
        $summary = $service->get_student_summary($params['userid'], $params['courseid']);

        return [
            'userid' => $summary['userid'],
            'courseid' => $summary['courseid'],
            'fullname' => $summary['user']['fullname'] ?? '',
            'email' => $summary['user']['email'] ?? '',
            'status' => $summary['status'],
            'status_label' => $summary['status_label'],
            'risk_score' => $summary['risk_score'],
            'signals' => array_map(function ($s) {
                return [
                    'type' => $s['type'] ?? '',
                    'severity' => $s['severity'] ?? 'info',
                    'title' => $s['title'] ?? '',
                    'description' => $s['description'] ?? ($s['message'] ?? ''),
                ];
            }, $summary['signals'] ?? []),
            'recommendations' => array_map(function ($r) {
                return [
                    'type' => $r['type'] ?? '',
                    'title' => $r['title'] ?? '',
                    'action' => $r['action'] ?? '',
                    'reason' => $r['reason'] ?? '',
                    'urgency' => $r['urgency'] ?? 'medium',
                ];
            }, $summary['recommendations'] ?? []),
        ];
    }

    /**
     * Return structure description for get_student_detail.
     */
    public static function get_student_detail_returns(): external_single_structure {
        return new external_single_structure([
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'fullname' => new external_value(PARAM_TEXT, 'Full student name'),
            'email' => new external_value(PARAM_RAW_TRIMMED, 'Student email (omitted for data minimisation)', VALUE_DEFAULT, ''),
            'status' => new external_value(PARAM_ALPHA, 'Status key'),
            'status_label' => new external_value(PARAM_TEXT, 'Localized status label'),
            'risk_score' => new external_value(PARAM_INT, 'Calculated risk score'),
            'signals' => new external_multiple_structure(
                new external_single_structure([
                    'type' => new external_value(PARAM_ALPHAEXT, 'Signal type'),
                    'severity' => new external_value(PARAM_ALPHA, 'Severity level'),
                    'title' => new external_value(PARAM_TEXT, 'Signal title'),
                    'description' => new external_value(PARAM_TEXT, 'Signal description text'),
                ])
            ),
            'recommendations' => new external_multiple_structure(
                new external_single_structure([
                    'type' => new external_value(PARAM_ALPHAEXT, 'Recommendation type'),
                    'title' => new external_value(PARAM_TEXT, 'Recommendation title'),
                    'action' => new external_value(PARAM_TEXT, 'Recommended action description'),
                    'reason' => new external_value(PARAM_TEXT, 'Recommendation reason'),
                    'urgency' => new external_value(PARAM_ALPHA, 'Urgency level'),
                ])
            ),
        ]);
    }
}
