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

namespace local_learningsuccess\task;

defined('MOODLE_INTERNAL') || die();

use core\task\scheduled_task;
use local_learningsuccess\local\explanation\signal_collector;
use local_learningsuccess\local\service\student_success_service;

/**
 * Scheduled background task to refresh learning success signals and warm cache.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh_student_data extends scheduled_task {

    /**
     * Return task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_refresh_student_data', 'local_learningsuccess');
    }

    /**
     * Execute task incrementally across active courses.
     */
    public function execute(): void {
        global $DB;

        if (!get_config('local_learningsuccess', 'enabled')) {
            mtrace("Learning success plugin is disabled. Skipping task.");
            return;
        }

        mtrace("Starting Learning Success data refresh task...");
        \core_php_time_limit::raise(300);

        // Fetch active courses (visible and ongoing).
        $now = time();
        $courses = $DB->get_records_select(
            'course',
            'id > 1 AND visible = 1 AND (enddate = 0 OR enddate > :now)',
            ['now' => $now],
            'id ASC',
            'id, fullname',
            0,
            50
        );

        $service = new student_success_service();
        $collector = new signal_collector();

        foreach ($courses as $course) {
            mtrace("Refreshing course: {$course->fullname} (ID: {$course->id})");

            // 1. Warm course summary cache first using high-performance bulk query.
            $service->get_course_summary($course->id, 0, true);

            // 2. Persist fresh signals and warm detailed profiles for priority at-risk learners.
            $priorities = $service->get_priority_students($course->id, 0, 20);
            foreach ($priorities as $student) {
                $uid = (int) $student['userid'];
                $collector->collect($uid, $course->id, true);
                $service->get_student_summary($uid, $course->id, true);
            }

            // Free cyclic references to keep memory footprint minimal.
            gc_collect_cycles();
        }

        // Auto-evaluate pending interventions older than 7 days.
        $manager = new \local_learningsuccess\local\intervention\intervention_manager();
        $evaluated = $manager->auto_evaluate_pending_interventions(7);
        if ($evaluated > 0) {
            mtrace("Auto-evaluated {$evaluated} pending interventions older than 7 days.");
        }

        mtrace("Learning Success data refresh task completed.");
    }
}

