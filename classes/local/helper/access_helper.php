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

namespace local_learningsuccess\local\helper;

use context_course;
use moodle_exception;
use stdClass;

/**
 * Centralized access and group validation helper.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access_helper {
    /**
     * Validate that target student is enrolled and accessible to current user under SEPARATEGROUPS.
     *
     * @param stdClass|int $course Course object or course ID.
     * @param context_course $context
     * @param int $targetuserid
     * @return stdClass The resolved course record.
     * @throws moodle_exception
     */
    public static function validate_student_access(stdClass|int $course, context_course $context, int $targetuserid): stdClass {
        global $DB, $USER;

        if (is_numeric($course)) {
            $course = $DB->get_record('course', ['id' => (int) $course], '*', MUST_EXIST);
        }

        if (!is_enrolled($context, $targetuserid, '', true)) {
            throw new moodle_exception('usernotenrolled', 'local_learningsuccess');
        }

        if ($course->groupmode == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context)) {
            $teachergroupids = array_keys(groups_get_all_groups($course->id, $USER->id));
            $studentgroupids = array_keys(groups_get_all_groups($course->id, $targetuserid));
            if (empty(array_intersect($teachergroupids, $studentgroupids))) {
                throw new moodle_exception('nopermissiontoviewstudent', 'local_learningsuccess');
            }
        }

        return $course;
    }

    /**
     * Retrieve groups allowed for current user in course.
     *
     * @param stdClass $course
     * @param context_course $context
     * @return array
     */
    public static function get_allowed_groups(stdClass $course, context_course $context): array {
        global $USER;

        if ($course->groupmode == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context)) {
            return groups_get_all_groups($course->id, $USER->id);
        }

        return groups_get_all_groups($course->id);
    }

    /**
     * Validate requested group ID and resolve default if needed.
     *
     * @param stdClass $course
     * @param context_course $context
     * @param int $requestedgroupid
     * @return array [$resolvedgroupid, $allowedgroups]
     * @throws moodle_exception
     */
    public static function resolve_group_scope(stdClass $course, context_course $context, int $requestedgroupid): array {
        $allowedgroups = self::get_allowed_groups($course, $context);
        $allowedgroupids = array_keys($allowedgroups);

        if ($course->groupmode == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context)) {
            if ($requestedgroupid > 0 && !in_array($requestedgroupid, $allowedgroupids)) {
                throw new moodle_exception('nopermissiontoviewgroup', 'local_learningsuccess');
            }
            if ($requestedgroupid == 0 && !empty($allowedgroupids)) {
                $requestedgroupid = reset($allowedgroupids);
            }
        }

        return [$requestedgroupid, $allowedgroups];
    }

    /**
     * Validate access to an intervention record.
     *
     * @param int $interventionid
     * @param string $requiredcapability
     * @return array [$interventionrecord, $course, $context]
     * @throws moodle_exception
     */
    public static function validate_intervention_access(
        int $interventionid,
        string $requiredcapability = 'local/learningsuccess:manageintervention'
    ): array {
        global $DB;

        $record = $DB->get_record('local_learningsuccess_int', ['id' => $interventionid], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $record->courseid], '*', MUST_EXIST);
        $context = context_course::instance($course->id);

        require_capability($requiredcapability, $context);
        self::validate_student_access($course, $context, (int) $record->userid);

        return [$record, $course, $context];
    }
}
