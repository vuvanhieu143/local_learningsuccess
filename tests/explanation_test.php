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
use local_learningsuccess\local\explanation\rules\inactivity;
use local_learningsuccess\local\explanation\rules\completion;
use local_learningsuccess\local\explanation\rules\grade_decline;
use local_learningsuccess\local\explanation\rules\missed_activity;
use local_learningsuccess\local\explanation\rule_interface;
use local_learningsuccess\local\explanation\signal_collector;
use local_learningsuccess\local\explanation\explanation_engine;
use local_learningsuccess\local\recommendation\recommendation_engine;

/**
 * Unit test suite verifying deterministic explanation rules, signal collector, and recommendation engine.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class explanation_test extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test inactivity rule returns critical when user has never accessed.
     */
    public function test_inactivity_rule_never_accessed(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $rule = new inactivity();
        $signal = $rule->evaluate($user->id, $course->id);

        $this->assertNotNull($signal);
        $this->assertEquals('inactivity', $signal['type']);
        $this->assertEquals(rule_interface::SEVERITY_CRITICAL, $signal['severity']);
        $this->assertArrayHasKey('message', $signal);
    }

    /**
     * Test inactivity rule with active access and warning access states.
     */
    public function test_inactivity_rule_thresholds(): void {
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

        $rule = new inactivity();
        $this->assertNull($rule->evaluate($user->id, $course->id));

        // 2. Inactive for 8 days (high severity warning).
        $DB->set_field('user_lastaccess', 'timeaccess', $now - (8 * DAYSECS), [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);
        $signalWarning = $rule->evaluate($user->id, $course->id);
        $this->assertNotNull($signalWarning);
        $this->assertEquals(rule_interface::SEVERITY_HIGH, $signalWarning['severity']);
        $this->assertEquals(8, $signalWarning['value']);

        // 3. Inactive for 15 days (critical severity).
        $DB->set_field('user_lastaccess', 'timeaccess', $now - (15 * DAYSECS), [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);
        $signalCritical = $rule->evaluate($user->id, $course->id);
        $this->assertNotNull($signalCritical);
        $this->assertEquals(rule_interface::SEVERITY_CRITICAL, $signalCritical['severity']);
        $this->assertEquals(15, $signalCritical['value']);
    }

    /**
     * Test completion rule returns null when course has no trackable activities.
     */
    public function test_completion_rule_no_activities(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $rule = new completion();
        $signal = $rule->evaluate($user->id, $course->id);

        $this->assertNull($signal);
    }

    /**
     * Test completion rule with deterministic mocked completion states.
     */
    public function test_completion_rule_mocked_states(): void {
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

        $rule = new completion();

        // Case A: 0 completed out of 4 (0% -> SEVERITY_HIGH).
        $signal = $rule->evaluate($user->id, $course->id);
        $this->assertNotNull($signal);
        $this->assertEquals('completion', $signal['type']);
        $this->assertEquals(rule_interface::SEVERITY_HIGH, $signal['severity']);
        $this->assertEquals(0, $signal['value']);

        // Case B: 1 completed out of 4 (25% -> SEVERITY_HIGH, < 30%).
        $DB->insert_record('course_modules_completion', (object) [
            'coursemoduleid' => $cmids[0],
            'userid' => $user->id,
            'completionstate' => 1,
            'timemodified' => time(),
        ]);
        $signal = $rule->evaluate($user->id, $course->id);
        $this->assertNotNull($signal);
        $this->assertEquals(rule_interface::SEVERITY_HIGH, $signal['severity']);
        $this->assertEquals(25, $signal['value']);

        // Case C: 2 completed out of 4 (50% -> null, since threshold is < 50%).
        $DB->insert_record('course_modules_completion', (object) [
            'coursemoduleid' => $cmids[1],
            'userid' => $user->id,
            'completionstate' => 2,
            'timemodified' => time(),
        ]);
        $signal = $rule->evaluate($user->id, $course->id);
        $this->assertNull($signal);
    }

    /**
     * Test grade decline rule with deterministic mocked gradebook items.
     */
    public function test_grade_decline_rule_mocked_grades(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $now = time();

        $rule = new grade_decline();

        // 1. Single grade item -> not enough variance data, returns null.
        $gi1 = $DB->insert_record('grade_items', (object) [
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemname' => 'Quiz 1',
            'grademax' => 100.0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('grade_grades', (object) [
            'itemid' => $gi1,
            'userid' => $user->id,
            'finalgrade' => 90.0,
            'timemodified' => $now - 200,
        ]);

        $this->assertNull($rule->evaluate($user->id, $course->id));

        // 2. Second grade item with slight drop (< 20 threshold, e.g. 90 -> 80 = 10 drop) -> null.
        $gi2 = $DB->insert_record('grade_items', (object) [
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemname' => 'Quiz 2',
            'grademax' => 100.0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $gg2 = $DB->insert_record('grade_grades', (object) [
            'itemid' => $gi2,
            'userid' => $user->id,
            'finalgrade' => 80.0,
            'timemodified' => $now - 100,
        ]);

        $this->assertNull($rule->evaluate($user->id, $course->id));

        // 3. Drop from 90 to 68 = 22 drop (>= 20 threshold -> SEVERITY_HIGH).
        $DB->set_field('grade_grades', 'finalgrade', 68.0, ['id' => $gg2]);
        $signalHigh = $rule->evaluate($user->id, $course->id);
        $this->assertNotNull($signalHigh);
        $this->assertEquals('grade_decline', $signalHigh['type']);
        $this->assertEquals(rule_interface::SEVERITY_HIGH, $signalHigh['severity']);
        $this->assertEquals(22, $signalHigh['value']);

        // 4. Drop from 90 to 55 = 35 drop (>= 30 (1.5 * threshold) -> SEVERITY_CRITICAL).
        $DB->set_field('grade_grades', 'finalgrade', 55.0, ['id' => $gg2]);
        $signalCritical = $rule->evaluate($user->id, $course->id);
        $this->assertNotNull($signalCritical);
        $this->assertEquals(rule_interface::SEVERITY_CRITICAL, $signalCritical['severity']);
        $this->assertEquals(35, $signalCritical['value']);
    }

    /**
     * Test missed activity rule with mocked overdue assignments.
     */
    public function test_missed_activity_rule_mocked(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $now = time();

        $rule = new missed_activity();

        // Initially no assignments -> null.
        $this->assertNull($rule->evaluate($user->id, $course->id));

        // 1. One overdue assignment without submission -> SEVERITY_HIGH.
        $assign1 = $DB->insert_record('assign', (object) [
            'course' => $course->id,
            'name' => 'Assignment 1',
            'duedate' => $now - 3600,
        ]);

        $signal1 = $rule->evaluate($user->id, $course->id);
        $this->assertNotNull($signal1);
        $this->assertEquals('missed_activity', $signal1['type']);
        $this->assertEquals(rule_interface::SEVERITY_HIGH, $signal1['severity']);
        $this->assertEquals(1, $signal1['value']);

        // 2. Second overdue assignment -> SEVERITY_CRITICAL.
        $assign2 = $DB->insert_record('assign', (object) [
            'course' => $course->id,
            'name' => 'Assignment 2',
            'duedate' => $now - 7200,
        ]);

        $signal2 = $rule->evaluate($user->id, $course->id);
        $this->assertNotNull($signal2);
        $this->assertEquals(rule_interface::SEVERITY_CRITICAL, $signal2['severity']);
        $this->assertEquals(2, $signal2['value']);

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

        $this->assertNull($rule->evaluate($user->id, $course->id));
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

        // Verify records in local_ls_signal.
        $persisted = $DB->get_records('local_ls_signal', ['userid' => $user->id, 'courseid' => $course->id]);
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
                'severity' => rule_interface::SEVERITY_CRITICAL,
                'value' => 14,
                'message' => 'No activity for 14 days',
                'metadata' => [],
            ],
            [
                'type' => 'missed_activity',
                'severity' => rule_interface::SEVERITY_HIGH,
                'value' => 2,
                'message' => '2 required activities overdue',
                'metadata' => [],
            ],
            [
                'type' => 'completion',
                'severity' => rule_interface::SEVERITY_HIGH,
                'value' => 20,
                'message' => 'Course completion low',
                'metadata' => [],
            ],
        ];

        $engine = new recommendation_engine();
        $recommendations = $engine->recommend($signals);

        $this->assertNotEmpty($recommendations);

        $actiontypes = array_column($recommendations, 'action');
        $this->assertContains(recommendation_engine::ACTION_CONTACT, $actiontypes);
        $this->assertContains(recommendation_engine::ACTION_MISSED_ACTIVITY, $actiontypes);
        $this->assertContains(recommendation_engine::ACTION_EXTENSION, $actiontypes);
        $this->assertContains(recommendation_engine::ACTION_RESOURCE, $actiontypes);
    }
}
