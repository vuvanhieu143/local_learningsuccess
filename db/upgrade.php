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
 * Upgrade steps for local_learningsuccess.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute upgrade steps from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_learningsuccess_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026091100) {
        $table = new xmldb_table('local_ls_signal');

        $fields = [
            new xmldb_field('firstseen', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'metadata'),
            new xmldb_field('lastseen', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'firstseen'),
            new xmldb_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'lastseen'),
            new xmldb_field('dismissed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'active'),
            new xmldb_field('dismissedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'dismissed'),
            new xmldb_field('dismissedat', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'dismissedby'),
            new xmldb_field('dismissreason', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'dismissedat'),
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $indexes = [
            new xmldb_index('user_course_type_idx', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid', 'signal_type']),
            new xmldb_index('active_dismissed_idx', XMLDB_INDEX_NOTUNIQUE, ['active', 'dismissed']),
        ];

        foreach ($indexes as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        upgrade_plugin_savepoint(true, 2026091100, 'local', 'learningsuccess');
    }

    if ($oldversion < 2026091101) {
        $table = new xmldb_table('local_ls_intervention');

        $fields = [
            new xmldb_field('before_snapshot_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'outcome'),
            new xmldb_field('after_snapshot_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'before_snapshot_id'),
            new xmldb_field('system_outcome', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'after_snapshot_id'),
            new xmldb_field('teacher_outcome', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'system_outcome'),
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $indexes = [
            new xmldb_index('before_snapshot_idx', XMLDB_INDEX_NOTUNIQUE, ['before_snapshot_id']),
            new xmldb_index('after_snapshot_idx', XMLDB_INDEX_NOTUNIQUE, ['after_snapshot_id']),
        ];

        foreach ($indexes as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        upgrade_plugin_savepoint(true, 2026091101, 'local', 'learningsuccess');
    }

    return true;
}
