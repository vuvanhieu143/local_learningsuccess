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

use advanced_testcase;
use local_learningsuccess\local\actionability\actionability_engine;
use local_learningsuccess\local\actionability\actionability_result;
use local_learningsuccess\local\explanation\explanation;
use local_learningsuccess\local\recommendation\recommendation_engine;
use local_learningsuccess\local\recommendation\rules\completion_rule;
use local_learningsuccess\local\recommendation\rules\inactivity_rule;
use local_learningsuccess\local\risk\risk_result;
use local_learningsuccess\local\signal\completion_signal;
use local_learningsuccess\local\signal\grade_decline_signal;
use local_learningsuccess\local\signal\inactivity_signal;
use local_learningsuccess\local\signal\overdue_signal;

/**
 * Unit tests covering signal evaluators, rules, and recommendation edge cases.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_learningsuccess\local\signal\signal_collector
 */
final class rules_and_signals_edge_cases_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test completion signal returns null when course has 0 trackable modules.
     */
    public function test_completion_signal_zero_modules(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $signal = new completion_signal();
        $result = $signal->evaluate($student->id, $course->id);

        $this->assertNull($result);
    }

    /**
     * Test grade decline signal returns null when student has no grades.
     */
    public function test_grade_decline_signal_no_grades(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $signal = new grade_decline_signal();
        $result = $signal->evaluate($student->id, $course->id);

        $this->assertNull($result);
    }

    /**
     * Test overdue signal returns null when no assignments exist in course.
     */
    public function test_overdue_signal_no_assignments(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $signal = new overdue_signal();
        $result = $signal->evaluate($student->id, $course->id);

        $this->assertNull($result);
    }

    /**
     * Test inactivity signal evaluates cleanly when student has never accessed course.
     */
    public function test_inactivity_signal_never_accessed(): void {
        $course = $this->getDataGenerator()->create_course(['startdate' => time() - (20 * 86400)]);
        $student = $this->getDataGenerator()->create_user(['firstaccess' => 0, 'lastaccess' => 0]);
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $signal = new inactivity_signal();
        $result = $signal->evaluate($student->id, $course->id);

        $this->assertInstanceOf(explanation::class, $result);
        $this->assertEquals(inactivity_signal::TYPE, $result->get_type());
        $this->assertEquals(explanation::SEVERITY_CRITICAL, $result->get_severity());
        $this->assertEquals(get_string('signal_no_activity_title', 'local_learningsuccess'), $result->get_title());
        $this->assertEquals(get_string('signal_no_activity_desc', 'local_learningsuccess'), $result->get_description());
    }

    /**
     * Test recommendation engine generates prioritized, sorted recommendations when multiple signals match.
     */
    public function test_recommendation_engine_multiple_signals(): void {
        $signals = [
            new explanation(
                type: 'inactivity',
                severity: explanation::SEVERITY_CRITICAL,
                title: 'Inactivity',
                description: 'Inactive for 15 days',
                value: 15
            ),
            new explanation(
                type: 'completion',
                severity: explanation::SEVERITY_WARNING,
                title: 'Low completion',
                description: 'Only completed 10%',
                value: 10
            ),
        ];

        $engine = new recommendation_engine();
        $recommendations = $engine->build($signals);

        $this->assertNotEmpty($recommendations);
        $this->assertGreaterThanOrEqual(2, count($recommendations));

        // High/Critical priority recommendation must appear first.
        $this->assertEquals('high', $recommendations[0]['urgency']);
    }

    /**
     * Test actionability engine calculates urgent level for critical risk and signal count.
     */
    public function test_actionability_engine_urgent_prioritization(): void {
        $risk = new risk_result(
            score: 95.0,
            level: 'critical',
            source: 'course_activity_signals'
        );

        $signals = [
            ['rule' => 'inactivity', 'severity' => 'critical', 'message' => 'Inactive 15 days', 'value' => 15],
            ['rule' => 'overdue', 'severity' => 'critical', 'message' => 'Missed 3 assignments', 'value' => 3],
        ];

        $engine = new actionability_engine();
        $actionability = $engine->evaluate(10, 1, $risk, $signals);

        $this->assertEquals(actionability_result::LEVEL_URGENT, $actionability->get_level());
        $this->assertGreaterThan(1000, $actionability->get_priority_score());
        $this->assertNotEmpty($actionability->get_primary_action());
    }
}
