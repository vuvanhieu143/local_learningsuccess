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
 * Event observers for local_learningsuccess.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_module_completion_updated',
        'callback' => '\local_learningsuccess\event\observers::module_completion_updated',
    ],
    [
        'eventname' => '\mod_assign\event\submission_graded',
        'callback' => '\local_learningsuccess\event\observers::submission_graded',
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback' => '\local_learningsuccess\event\observers::attempt_submitted',
    ],
    [
        'eventname' => '\core\event\user_enrolment_created',
        'callback' => '\local_learningsuccess\event\observers::user_enrolment_changed',
    ],
    [
        'eventname' => '\core\event\user_enrolment_deleted',
        'callback' => '\local_learningsuccess\event\observers::user_enrolment_changed',
    ],
];

