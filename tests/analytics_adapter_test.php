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

namespace local_learningsuccess;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;
use local_learningsuccess\local\analytics\analytics_adapter;

/**
 * Unit test suite verifying analytics adapter fallback behavior and heuristic risk scoring.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class analytics_adapter_test extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test fallback calculation for student who never accessed the course.
     */
    public function test_fallback_status_never_accessed(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $adapter = new analytics_adapter();
        $result = $adapter->get_student_status($user->id, $course->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('risk_score', $result);
        $this->assertArrayHasKey('source', $result);
        $this->assertEquals('heuristic_engine', $result['source']);

        // Never accessed student should have default 14 days inactivity (+45 risk).
        $this->assertGreaterThanOrEqual(45, $result['risk_score']);
        $this->assertContains($result['status'], [
            analytics_adapter::STATUS_MONITOR,
            analytics_adapter::STATUS_ATRISK,
            analytics_adapter::STATUS_CRITICAL,
        ]);
    }

    /**
     * Test status constants and boundaries.
     */
    public function test_status_constants(): void {
        $this->assertEquals('critical', analytics_adapter::STATUS_CRITICAL);
        $this->assertEquals('atrisk', analytics_adapter::STATUS_ATRISK);
        $this->assertEquals('monitor', analytics_adapter::STATUS_MONITOR);
        $this->assertEquals('healthy', analytics_adapter::STATUS_HEALTHY);
    }

    /**
     * Test heuristic calculations for healthy active learner.
     */
    public function test_heuristic_healthy_status(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $now = time();

        // Recent activity (yesterday).
        $DB->insert_record('user_lastaccess', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'timeaccess' => $now - DAYSECS,
        ]);

        // Course grade of 85% (no risk penalty).
        $gi = $DB->insert_record('grade_items', (object) [
            'courseid' => $course->id,
            'itemtype' => 'course',
            'grademax' => 100.0,
        ]);
        $DB->insert_record('grade_grades', (object) [
            'itemid' => $gi,
            'userid' => $user->id,
            'finalgrade' => 85.0,
        ]);

        // Completion at 75% (3 of 4 completed).
        for ($i = 1; $i <= 4; $i++) {
            $cmid = $DB->insert_record('course_modules', (object) [
                'course' => $course->id,
                'module' => 1,
                'instance' => $i,
                'completion' => 1,
                'deletioninprogress' => 0,
                'added' => $now,
            ]);
            if ($i <= 3) {
                $DB->insert_record('course_modules_completion', (object) [
                    'coursemoduleid' => $cmid,
                    'userid' => $user->id,
                    'completionstate' => 1,
                    'timemodified' => $now,
                ]);
            }
        }

        $adapter = new analytics_adapter();
        $result = $adapter->get_student_status($user->id, $course->id);

        $this->assertEquals(0, $result['risk_score']);
        $this->assertEquals(analytics_adapter::STATUS_HEALTHY, $result['status']);
        $this->assertEquals('heuristic_engine', $result['source']);
    }

    /**
     * Test heuristic calculations triggering critical status.
     */
    public function test_heuristic_critical_status(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $now = time();

        // Inactive for 15 days (+45).
        $DB->insert_record('user_lastaccess', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'timeaccess' => $now - (15 * DAYSECS),
        ]);

        // Course grade of 30% (< 40% threshold -> +40).
        $gi = $DB->insert_record('grade_items', (object) [
            'courseid' => $course->id,
            'itemtype' => 'course',
            'grademax' => 100.0,
        ]);
        $DB->insert_record('grade_grades', (object) [
            'itemid' => $gi,
            'userid' => $user->id,
            'finalgrade' => 30.0,
        ]);

        $adapter = new analytics_adapter();
        $result = $adapter->get_student_status($user->id, $course->id);

        // Expected score: 45 (inactivity) + 40 (grade) = 85 (clamped to <= 100).
        $this->assertEquals(85, $result['risk_score']);
        $this->assertEquals(analytics_adapter::STATUS_CRITICAL, $result['status']);
    }

    /**
     * Test heuristic calculations triggering monitor and at-risk statuses.
     */
    public function test_heuristic_monitor_and_atrisk_statuses(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $now = time();

        // 1. Inactive 8 days (+30) -> Score 30 -> MONITOR (>= 25).
        $DB->insert_record('user_lastaccess', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'timeaccess' => $now - (8 * DAYSECS),
        ]);

        $adapter = new analytics_adapter();
        $resMonitor = $adapter->get_student_status($user->id, $course->id);
        $this->assertEquals(30, $resMonitor['risk_score']);
        $this->assertEquals(analytics_adapter::STATUS_MONITOR, $resMonitor['status']);

        // 2. Add grade of 50% (< 60% threshold -> +25) -> Score 30 + 25 = 55 -> ATRISK (>= 50).
        $gi = $DB->insert_record('grade_items', (object) [
            'courseid' => $course->id,
            'itemtype' => 'course',
            'grademax' => 100.0,
        ]);
        $DB->insert_record('grade_grades', (object) [
            'itemid' => $gi,
            'userid' => $user->id,
            'finalgrade' => 50.0,
        ]);

        $resAtrisk = $adapter->get_student_status($user->id, $course->id);
        $this->assertEquals(55, $resAtrisk['risk_score']);
        $this->assertEquals(analytics_adapter::STATUS_ATRISK, $resAtrisk['status']);
    }
}
