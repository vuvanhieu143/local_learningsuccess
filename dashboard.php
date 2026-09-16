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
 * Course Learning Success Dashboard.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_learningsuccess\local\service\student_success_service;
use local_learningsuccess\output\dashboard;

$courseid = required_param('courseid', PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($courseid);
require_capability('local/learningsuccess:viewcourse', $context);

$urlparams = ['courseid' => $courseid];
if ($groupid > 0) {
    $urlparams['groupid'] = $groupid;
}
$url = new moodle_url('/local/learningsuccess/dashboard.php', $urlparams);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('learningsuccess', 'local_learningsuccess') . ': ' . format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('pluginname', 'local_learningsuccess'), $url);

$PAGE->requires->js_call_amd('local_learningsuccess/dashboard', 'init', [$courseid]);

[$groupid, $groups] = \local_learningsuccess\local\helper\access_helper::resolve_group_scope($course, $context, $groupid);

$service = new student_success_service();
$summary = $service->get_course_summary($courseid, $groupid);
$priorities = $service->get_priority_students($courseid, $groupid);
$successstories = $service->get_recent_success_stories($courseid, 4);
$recentlyhandled = $service->get_recently_handled_students($courseid, 4);

$output = $PAGE->get_renderer('local_learningsuccess');
$dashboardrenderable = new dashboard($courseid, $summary, $priorities, $groupid, $groups, $successstories, $recentlyhandled);

echo $output->header();
echo $output->render($dashboardrenderable);
echo $output->footer();

