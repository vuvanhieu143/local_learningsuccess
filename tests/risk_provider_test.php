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
use local_learningsuccess\local\risk\risk_result;
use local_learningsuccess\local\risk\fallback_provider;
use local_learningsuccess\local\risk\moodle_analytics_provider;
use local_learningsuccess\local\signal\signal_collector;
use local_learningsuccess\local\signal\inactivity_signal;
use local_learningsuccess\local\explanation\explanation;
use local_learningsuccess\local\explanation\explanation_engine;

/**
 * Unit test suite for Phase 1 Risk Abstraction and Signal/Explanation Engine.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class risk_provider_test extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test risk_result value object.
     */
    public function test_risk_result_value_object(): void {
        $result = new risk_result(
            score: 75.5,
            level: risk_result::LEVEL_CRITICAL,
            source: 'test_source',
            model: 'students_at_risk'
        );

        $this->assertEquals(75.5, $result->get_score());
        $this->assertEquals(risk_result::LEVEL_CRITICAL, $result->get_level());
        $this->assertEquals('test_source', $result->get_source());
        $this->assertEquals('students_at_risk', $result->get_model());
        $this->assertTrue($result->requires_attention());

        $arr = $result->to_array();
        $this->assertIsArray($arr);
        $this->assertEquals(75.5, $arr['score']);
        $this->assertTrue($arr['requires_attention']);
    }

    /**
     * Test fallback_provider calculating deterministic heuristic scores.
     */
    public function test_fallback_provider_healthy_and_critical(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $now = time();

        $provider = new fallback_provider();

        // 1. Initial status: student has never logged in (default 14 days -> critical/at-risk).
        $risk = $provider->get_risk($user->id, $course->id);
        $this->assertGreaterThanOrEqual(45.0, $risk->get_score());
        $this->assertEquals(fallback_provider::SOURCE_NAME, $risk->get_source());
        $this->assertNull($risk->get_model());

        // 2. Active learner with high grade and completion.
        $DB->insert_record('user_lastaccess', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'timeaccess' => $now - DAYSECS,
        ]);

        $gi = $DB->insert_record('grade_items', (object) [
            'courseid' => $course->id,
            'itemtype' => 'course',
            'grademax' => 100.0,
        ]);
        $DB->insert_record('grade_grades', (object) [
            'itemid' => $gi,
            'userid' => $user->id,
            'finalgrade' => 90.0,
        ]);

        $riskhealthy = $provider->get_risk($user->id, $course->id);
        $this->assertEquals(0.0, $riskhealthy->get_score());
        $this->assertEquals(risk_result::LEVEL_HEALTHY, $riskhealthy->get_level());
        $this->assertFalse($riskhealthy->requires_attention());
    }

    /**
     * Test moodle_analytics_provider falling back gracefully.
     */
    public function test_moodle_analytics_provider_fallback(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $analytics = new moodle_analytics_provider();
        $risk = $analytics->get_risk($user->id, $course->id);

        $this->assertInstanceOf(risk_result::class, $risk);
        $this->assertEquals(fallback_provider::SOURCE_NAME, $risk->get_source());
    }

    /**
     * Test signal_collector and inactivity_signal.
     */
    public function test_inactivity_signal_evaluation(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $now = time();

        $DB->insert_record('user_lastaccess', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'timeaccess' => $now - (10 * DAYSECS),
        ]);

        $collector = new signal_collector([new inactivity_signal()]);
        $signals = $collector->collect($user->id, $course->id);

        $this->assertCount(1, $signals);
        $this->assertInstanceOf(explanation::class, $signals[0]);
        $this->assertEquals(inactivity_signal::TYPE, $signals[0]->get_type());
        $this->assertEquals(explanation::SEVERITY_WARNING, $signals[0]->get_severity());
        $this->assertEquals(10, $signals[0]->get_value());
    }

    /**
     * Test explanation_engine build method with deduplication and sorting.
     */
    public function test_explanation_engine_build_and_explain(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $risk = new risk_result(60.0, risk_result::LEVEL_ATRISK, 'test_source');

        $signals = [
            new explanation('inactivity', explanation::SEVERITY_WARNING, 'Inactive', 'Inactive 8 days', 8),
            new explanation('overdue', explanation::SEVERITY_CRITICAL, 'Overdue', '3 overdue tasks', 3),
            new explanation('inactivity', explanation::SEVERITY_INFO, 'Duplicate', 'Duplicate', 8),
        ];

        $engine = new explanation_engine();
        $built = $engine->build($risk, $signals);

        // Deduplication should leave 2 signals.
        $this->assertCount(2, $built);
        // Critical 'overdue' should be first.
        $this->assertEquals('overdue', $built[0]['type']);
        $this->assertEquals(explanation::SEVERITY_CRITICAL, $built[0]['severity']);
        // Warning 'inactivity' should be second.
        $this->assertEquals('inactivity', $built[1]['type']);

        // Test explain_student output structure.
        $explanationdata = $engine->explain_student($user->id, $course->id);
        $this->assertArrayHasKey('status', $explanationdata);
        $this->assertArrayHasKey('risk_score', $explanationdata);
        $this->assertArrayHasKey('signals', $explanationdata);
    }

    /**
     * Test single get_risk and bulk get_risks produce identical results.
     */
    public function test_bulk_and_single_risk_consistency(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();
        $now = time();

        // Student 1: Inactive 10 days, grade 50%.
        $DB->insert_record('user_lastaccess', [
            'userid' => $student1->id,
            'courseid' => $course->id,
            'timeaccess' => $now - (10 * DAYSECS),
        ]);
        $gi = $DB->insert_record('grade_items', (object) [
            'courseid' => $course->id,
            'itemtype' => 'course',
            'grademax' => 100.0,
        ]);
        $DB->insert_record('grade_grades', (object) [
            'itemid' => $gi,
            'userid' => $student1->id,
            'finalgrade' => 50.0,
        ]);

        // Student 2: Active yesterday, grade 95%.
        $DB->insert_record('user_lastaccess', [
            'userid' => $student2->id,
            'courseid' => $course->id,
            'timeaccess' => $now - DAYSECS,
        ]);
        $DB->insert_record('grade_grades', (object) [
            'itemid' => $gi,
            'userid' => $student2->id,
            'finalgrade' => 95.0,
        ]);

        $providers = [
            new fallback_provider(),
            new moodle_analytics_provider(),
        ];

        foreach ($providers as $provider) {
            $single1 = $provider->get_risk($student1->id, $course->id);
            $single2 = $provider->get_risk($student2->id, $course->id);

            $bulk = $provider->get_risks([$student1->id, $student2->id], $course->id);

            $this->assertArrayHasKey($student1->id, $bulk);
            $this->assertArrayHasKey($student2->id, $bulk);

            // Single and bulk results must be strictly identical.
            $this->assertEquals($single1->get_score(), $bulk[$student1->id]->get_score());
            $this->assertEquals($single1->get_level(), $bulk[$student1->id]->get_level());
            $this->assertEquals($single1->get_source(), $bulk[$student1->id]->get_source());

            $this->assertEquals($single2->get_score(), $bulk[$student2->id]->get_score());
            $this->assertEquals($single2->get_level(), $bulk[$student2->id]->get_level());
            $this->assertEquals($single2->get_source(), $bulk[$student2->id]->get_source());

            // Empty userids returns empty array.
            $this->assertEmpty($provider->get_risks([], $course->id));
        }
    }
}
