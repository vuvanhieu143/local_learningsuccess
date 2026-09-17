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

namespace local_learningsuccess\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\helper;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API provider for local_learningsuccess.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements core_userlist_provider, metadata_provider, plugin_provider {
    /**
     * Return the fields which contain personal data.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_learningsuccess_int',
            [
                'userid' => 'privacy:metadata:local_learningsuccess_int:userid',
                'courseid' => 'privacy:metadata:local_learningsuccess_int:courseid',
                'teacherid' => 'privacy:metadata:local_learningsuccess_int:teacherid',
                'type' => 'privacy:metadata:local_learningsuccess_int:type',
                'reason' => 'privacy:metadata:local_learningsuccess_int:reason',
                'recommended_action' => 'privacy:metadata:local_learningsuccess_int:recommended_action',
                'actual_action' => 'privacy:metadata:local_learningsuccess_int:actual_action',
                'status' => 'privacy:metadata:local_learningsuccess_int:status',
                'outcome' => 'privacy:metadata:local_learningsuccess_int:outcome',
                'before_snapshot' => 'privacy:metadata:local_learningsuccess_int:before_snapshot',
                'after_snapshot' => 'privacy:metadata:local_learningsuccess_int:after_snapshot',
                'timecreated' => 'privacy:metadata:local_learningsuccess_int:timecreated',
                'timemodified' => 'privacy:metadata:local_learningsuccess_int:timemodified',
                'completed_at' => 'privacy:metadata:local_learningsuccess_int:completed_at',
            ],
            'privacy:metadata:local_learningsuccess_int'
        );

        $collection->add_database_table(
            'local_learningsuccess_sign',
            [
                'userid' => 'privacy:metadata:local_learningsuccess_sign:userid',
                'courseid' => 'privacy:metadata:local_learningsuccess_sign:courseid',
                'signal_type' => 'privacy:metadata:local_learningsuccess_sign:signal_type',
                'severity' => 'privacy:metadata:local_learningsuccess_sign:severity',
                'value' => 'privacy:metadata:local_learningsuccess_sign:value',
                'metadata' => 'privacy:metadata:local_learningsuccess_sign:metadata',
                'timecreated' => 'privacy:metadata:local_learningsuccess_sign:timecreated',
            ],
            'privacy:metadata:local_learningsuccess_sign'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Check interventions where user is student or teacher.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course} cr ON c.instanceid = cr.id AND c.contextlevel = :contextlevel
                  JOIN {local_learningsuccess_int} i ON i.courseid = cr.id
                 WHERE i.userid = :userid1
                       OR i.teacherid = :teacherid";

        $params = [
            'contextlevel' => CONTEXT_COURSE,
            'userid1' => $userid,
            'teacherid' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);

        // Check signals.
        $sql2 = "SELECT c.id
                   FROM {context} c
                   JOIN {course} cr ON c.instanceid = cr.id AND c.contextlevel = :contextlevel
                   JOIN {local_learningsuccess_sign} s ON s.courseid = cr.id
                  WHERE s.userid = :userid";
        $contextlist->add_from_sql($sql2, ['contextlevel' => CONTEXT_COURSE, 'userid' => $userid]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist to add the users to.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $params = ['courseid' => $context->instanceid];

        $sql = "SELECT userid
                  FROM {local_learningsuccess_int}
                 WHERE courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sqlteacher = "SELECT teacherid AS userid
                         FROM {local_learningsuccess_int}
                        WHERE courseid = :courseid";
        $userlist->add_from_sql('userid', $sqlteacher, $params);

        $sqlsignal = "SELECT userid
                        FROM {local_learningsuccess_sign}
                       WHERE courseid = :courseid";
        $userlist->add_from_sql('userid', $sqlsignal, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }

            $courseid = $context->instanceid;

            $interventions = $DB->get_records_select(
                'local_learningsuccess_int',
                'courseid = :courseid AND (userid = :userid OR teacherid = :teacherid)',
                ['courseid' => $courseid, 'userid' => $userid, 'teacherid' => $userid]
            );

            if (!empty($interventions)) {
                $data = array_map(function ($record) {
                    return [
                        'type' => $record->type,
                        'reason' => $record->reason,
                        'recommended_action' => $record->recommended_action,
                        'actual_action' => $record->actual_action,
                        'status' => $record->status,
                        'outcome' => $record->outcome,
                        'timecreated' => transform::datetime($record->timecreated),
                        'timemodified' => transform::datetime($record->timemodified),
                    ];
                }, $interventions);

                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_learningsuccess'), get_string('interventions', 'local_learningsuccess')],
                    (object)['interventions' => array_values($data)]
                );
            }

            $signals = $DB->get_records('local_learningsuccess_sign', ['courseid' => $courseid, 'userid' => $userid]);
            if (!empty($signals)) {
                $signaldata = array_map(function ($record) {
                    return [
                        'signal_type' => $record->signal_type,
                        'severity' => $record->severity,
                        'value' => $record->value,
                        'metadata' => $record->metadata,
                        'timecreated' => transform::datetime($record->timecreated),
                    ];
                }, $signals);

                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_learningsuccess'), get_string('signals', 'local_learningsuccess')],
                    (object)['signals' => array_values($signaldata)]
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $courseid = $context->instanceid;
        $DB->delete_records('local_learningsuccess_int', ['courseid' => $courseid]);
        $DB->delete_records('local_learningsuccess_sign', ['courseid' => $courseid]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }
            $courseid = $context->instanceid;
            $DB->delete_records('local_learningsuccess_int', ['courseid' => $courseid, 'userid' => $userid]);
            $DB->delete_records('local_learningsuccess_sign', ['courseid' => $courseid, 'userid' => $userid]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $courseid = $context->instanceid;
        $userids = $userlist->get_userids();

        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = array_merge(['courseid' => $courseid], $inparams);

        $DB->delete_records_select('local_learningsuccess_int', "courseid = :courseid AND userid $insql", $params);
        $DB->delete_records_select('local_learningsuccess_sign', "courseid = :courseid AND userid $insql", $params);
    }
}
