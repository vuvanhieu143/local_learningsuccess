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
use local_learningsuccess\local\signal\inactivity_signal;
use local_learningsuccess\local\signal\completion_signal;
use local_learningsuccess\local\signal\grade_decline_signal;
use local_learningsuccess\local\signal\overdue_signal;
use local_learningsuccess\local\signal\signal_collector;
use local_learningsuccess\local\explanation\explanation;
use local_learningsuccess\local\explanation\explanation_engine;
use local_learningsuccess\local\recommendation\recommendation_engine;

/**
 * Unit test suite verifying modular signals, signal collector, and explanation engine.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class explanation_test extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test inactivity signal returns critical when user has never accessed.
     */
    public function test_inactivity_signal_never_accessed(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $signal = new inactivity_signal();
        $explanation = $signal->evaluate($user->id, $course->id);

        $this->assertNotNull($explanation);
        $this->assertEquals('inactivity', $explanation->get_type());
        $this->assertEquals(explanation::SEVERITY_CRITICAL, $explanation->get_severity());
    }

    /**
     * Test inactivity signal with active access and warning access states.
     */
    public function test_inactivity_signal_thresholds(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $now = time();

        // 1. Active yesterday (no signal).
        $DB->insert_record('user_lastaccess', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'timeaccess' => $now - DAYSECS,
        ]);

        $signal = new inactivity_signal();
        $this->assertNull($signal->evaluate($user->id, $course->id));

        // 2. Inactive for 8 days (warning severity).
        $DB->set_field('user_lastaccess', 'timeaccess', $now - (8 * DAYSECS), [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);
        $signalWarning = $signal->evaluate($user->id, $course->id);
        $this->assertNotNull($signalWarning);
        $this->assertEquals(explanation::SEVERITY_WARNING, $signalWarning->get_severity());
        $this->assertEquals(8, $signalWarning->get_value());

        // 3. Inactive for 15 days (critical severity).
        $DB->set_field('user_lastaccess', 'timeaccess', $now - (15 * DAYSECS), [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);
        $signalCritical = $signal->evaluate($user->id, $course->id);
        $this->assertNotNull($signalCritical);
        $this->assertEquals(explanation::SEVERITY_CRITICAL, $signalCritical->get_severity());
        $this->assertEquals(15, $signalCritical->get_value());
    }

    /**
     * Test completion signal returns null when course has no trackable activities.
     */
    public function test_completion_signal_no_activities(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $signal = new completion_signal();
        $res = $signal->evaluate($user->id, $course->id);

        $this->assertNull($res);
    }

    /**
     * Test completion signal with deterministic mocked completion states.
     */
    public function test_completion_signal_mocked_states(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        // Create 4 trackable course modules.
        $cmids = [];
        for ($i = 1; $i <= 4; $i++) {
            $cmids[] = $DB->insert_record('course_modules', (object) [
                'course' => $course->id,
                'module' => 1,
                'instance' => $i,
                'completion' => 1,
                'deletioninprogress' => 0,
                'added' => time(),
            ]);
        }

        $signal = new completion_signal();

        // Case A: 0 completed out of 4 (0% -> SEVERITY_CRITICAL, < 25%).
        $res = $signal->evaluate($user->id, $course->id);
        $this->assertNotNull($res);
        $this->assertEquals('completion', $res->get_type());
        $this->assertEquals(explanation::SEVERITY_CRITICAL, $res->get_severity());
        $this->assertEquals(0, $res->get_value());

        // Case B: 1 completed out of 4 (25% -> SEVERITY_WARNING, < 50%).
        $DB->insert_record('course_modules_completion', (object) [
            'coursemoduleid' => $cmids[0],
            'userid' => $user->id,
            'completionstate' => 1,
            'timemodified' => time(),
        ]);
        $res = $signal->evaluate($user->id, $course->id);
        $this->assertNotNull($res);
        $this->assertEquals(explanation::SEVERITY_WARNING, $res->get_severity());
        $this->assertEquals(25, $res->get_value());

        // Case C: 2 completed out of 4 (50% -> null, since threshold is < 50%).
        $DB->insert_record('course_modules_completion', (object) [
            'coursemoduleid' => $cmids[1],
            'userid' => $user->id,
            'completionstate' => 2,
            'timemodified' => time(),
        ]);
        $res = $signal->evaluate($user->id, $course->id);
        $this->assertNull($res);
    }

    /**
     * Test grade decline signal with deterministic mocked gradebook items.
     */
    public function test_grade_decline_signal_mocked_grades(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $now = time();

        $signal = new grade_decline_signal();

        // 1. Single grade item at 80% -> healthy, returns null.
        $gi1 = $DB->insert_record('grade_items', (object) [
            'courseid' => $course->id,
            'itemtype' => 'course',
            'itemname' => 'Course Grade',
            'grademax' => 100.0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $gg1 = $DB->insert_record('grade_grades', (object) [
            'itemid' => $gi1,
            'userid' => $user->id,
            'finalgrade' => 80.0,
            'timemodified' => $now,
        ]);

        $this->assertNull($signal->evaluate($user->id, $course->id));

        // 2. Grade drops to 50% (< 60% threshold -> SEVERITY_WARNING).
        $DB->set_field('grade_grades', 'finalgrade', 50.0, ['id' => $gg1]);
        $resWarning = $signal->evaluate($user->id, $course->id);
        $this->assertNotNull($resWarning);
        $this->assertEquals('grade_decline', $resWarning->get_type());
        $this->assertEquals(explanation::SEVERITY_WARNING, $resWarning->get_severity());
        $this->assertEquals(50.0, $resWarning->get_value());

        // 3. Grade drops to 30% (< 40% threshold -> SEVERITY_CRITICAL).
        $DB->set_field('grade_grades', 'finalgrade', 30.0, ['id' => $gg1]);
        $resCritical = $signal->evaluate($user->id, $course->id);
        $this->assertNotNull($resCritical);
        $this->assertEquals(explanation::SEVERITY_CRITICAL, $resCritical->get_severity());
        $this->assertEquals(30.0, $resCritical->get_value());
    }

    /**
     * Test overdue signal with mocked overdue assignments.
     */
    public function test_overdue_signal_mocked(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $now = time();

        $signal = new overdue_signal();

        // 1. One overdue assignment without submission -> SEVERITY_WARNING.
        $assign1 = $DB->insert_record('assign', (object) [
            'course' => $course->id,
            'name' => 'Assignment 1',
            'intro' => '',
            'introformat' => 0,
            'duedate' => $now - 3600,
        ]);

        $res1 = $signal->evaluate($user->id, $course->id);
        $this->assertNotNull($res1);
        $this->assertEquals('overdue', $res1->get_type());
        $this->assertEquals(explanation::SEVERITY_WARNING, $res1->get_severity());
        $this->assertEquals(1, $res1->get_value());

        // 2. Second overdue assignment -> SEVERITY_CRITICAL.
        $assign2 = $DB->insert_record('assign', (object) [
            'course' => $course->id,
            'name' => 'Assignment 2',
            'intro' => '',
            'introformat' => 0,
            'duedate' => $now - 7200,
        ]);

        $res2 = $signal->evaluate($user->id, $course->id);
        $this->assertNotNull($res2);
        $this->assertEquals(explanation::SEVERITY_CRITICAL, $res2->get_severity());
        $this->assertEquals(2, $res2->get_value());

        // 3. User submits both assignments -> null.
        $DB->insert_record('assign_submission', (object) [
            'assignment' => $assign1,
            'userid' => $user->id,
            'status' => 'submitted',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('assign_submission', (object) [
            'assignment' => $assign2,
            'userid' => $user->id,
            'status' => 'submitted',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $this->assertNull($signal->evaluate($user->id, $course->id));
    }

    /**
     * Test signal collector aggregates and optionally persists signals.
     */
    public function test_signal_collector_persists(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $collector = new signal_collector();
        $signals = $collector->collect($user->id, $course->id, true);

        $this->assertIsArray($signals);
        $this->assertNotEmpty($signals);

        // Verify records in local_learningsuccess_sign.
        $persisted = $DB->get_records('local_learningsuccess_sign', ['userid' => $user->id, 'courseid' => $course->id]);
        $this->assertNotEmpty($persisted);
    }

    /**
     * Test explanation engine synthesizes status and signals.
     */
    public function test_explanation_engine_synthesis(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $engine = new explanation_engine();
        $result = $engine->explain_student($user->id, $course->id);

        $this->assertEquals($user->id, $result['userid']);
        $this->assertEquals($course->id, $result['courseid']);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('risk_score', $result);
        $this->assertArrayHasKey('signals', $result);
    }

    /**
     * Test recommendation engine deterministic mapping.
     */
    public function test_recommendation_engine_mapping(): void {
        $signals = [
            [
                'type' => 'inactivity',
                'severity' => explanation::SEVERITY_CRITICAL,
                'value' => 14,
                'message' => 'No activity for 14 days',
                'metadata' => [],
            ],
            [
                'type' => 'overdue',
                'severity' => explanation::SEVERITY_WARNING,
                'value' => 2,
                'message' => '2 required activities overdue',
                'metadata' => [],
            ],
            [
                'type' => 'completion',
                'severity' => explanation::SEVERITY_WARNING,
                'value' => 20,
                'message' => 'Course completion low',
                'metadata' => [],
            ],
        ];

        $engine = new recommendation_engine();
        $recommendations = $engine->recommend($signals);

        $this->assertNotEmpty($recommendations);

        $actiontypes = array_column($recommendations, 'type');
        $this->assertContains(recommendation_engine::ACTION_CONTACT, $actiontypes);
        $this->assertContains(recommendation_engine::ACTION_MISSED_ACTIVITY, $actiontypes);
        $this->assertContains(recommendation_engine::ACTION_EXTENSION, $actiontypes);
        $this->assertContains(recommendation_engine::ACTION_RESOURCE, $actiontypes);
    }
}
