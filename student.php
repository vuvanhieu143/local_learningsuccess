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
 * Student Success Details and Intervention History.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_learningsuccess\local\service\student_success_service;

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$targetuser = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($courseid);
require_capability('local/learningsuccess:viewstudent', $context);

\local_learningsuccess\local\helper\access_helper::validate_student_access($course, $context, $userid);

$url = new moodle_url('/local/learningsuccess/student.php', ['courseid' => $courseid, 'userid' => $userid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(fullname($targetuser) . ' - ' . get_string('studentdetails', 'local_learningsuccess'));
$PAGE->set_heading(format_string($course->fullname));

$dashboardurl = new moodle_url('/local/learningsuccess/dashboard.php', ['courseid' => $courseid]);
$PAGE->navbar->add(get_string('pluginname', 'local_learningsuccess'), $dashboardurl);
$PAGE->navbar->add(fullname($targetuser));

$PAGE->requires->js_call_amd('local_learningsuccess/intervention', 'init', [$courseid, $userid]);

$service = new student_success_service();
$studentdata = $service->get_student_summary($userid, $courseid);

$output = $PAGE->get_renderer('local_learningsuccess');
$detailrenderable = new \local_learningsuccess\output\student_detail($studentdata);

echo $output->header();
echo $output->render($detailrenderable);
echo $output->footer();
