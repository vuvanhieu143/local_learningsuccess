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
 * External service definitions for local_learningsuccess.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_learningsuccess_get_dashboard_data' => [
        'classname' => 'local_learningsuccess\external\dashboard_exporter',
        'methodname' => 'get_dashboard_data',
        'description' => 'Retrieves course pulse, health metrics, and priority students',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/learningsuccess:viewcourse',
    ],
    'local_learningsuccess_create_intervention' => [
        'classname' => 'local_learningsuccess\external\dashboard_exporter',
        'methodname' => 'create_intervention',
        'description' => 'Records a new intervention for a student',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/learningsuccess:createintervention',
    ],
    'local_learningsuccess_complete_intervention' => [
        'classname' => 'local_learningsuccess\external\dashboard_exporter',
        'methodname' => 'complete_intervention',
        'description' => 'Completes an intervention and evaluates the outcome',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/learningsuccess:manageintervention',
    ],
    'local_learningsuccess_dismiss_intervention' => [
        'classname' => 'local_learningsuccess\external\dashboard_exporter',
        'methodname' => 'dismiss_intervention',
        'description' => 'Dismisses an intervention record',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/learningsuccess:manageintervention',
    ],
    'local_learningsuccess_get_student_detail' => [
        'classname' => 'local_learningsuccess\external\student_exporter',
        'methodname' => 'get_student_detail',
        'description' => 'Retrieves detailed student explanations and recommendations',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/learningsuccess:viewstudent',
    ],
];

