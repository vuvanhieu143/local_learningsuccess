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
use local_learningsuccess\local\intervention\intervention_manager;
use local_learningsuccess\local\intervention\intervention_status;
use local_learningsuccess\local\outcome\outcome;
use local_learningsuccess\local\outcome\outcome_evaluator;

/**
 * Unit test suite verifying intervention lifecycle, state transitions, and outcome tracking.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_learningsuccess\local\intervention\intervention_manager
 */
final class intervention_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test intervention creation and snapshot serialization.
     */
    public function test_intervention_creation(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        $manager = new intervention_manager();
        $id = $manager->create(
            $student->id,
            $course->id,
            $teacher->id,
            'CONTACT',
            'Student inactive for 8 days',
            'Send check-in message',
            'Message sent via Moodle'
        );

        $this->assertGreaterThan(0, $id);

        $record = $DB->get_record('local_learningsuccess_int', ['id' => $id]);
        $this->assertNotEmpty($record);
        $this->assertEquals(intervention_manager::STATUS_OPEN, $record->status);
        $this->assertEquals(outcome::UNKNOWN, $record->outcome);
        $this->assertNotEmpty($record->before_snapshot);
        $this->assertNull($record->after_snapshot);
        $this->assertNull($record->completed_at);

        // Verify before_snapshot serialization integrity.
        $snapshot = json_decode($record->before_snapshot, true);
        $this->assertIsArray($snapshot);
        $this->assertArrayHasKey('risk_score', $snapshot);
        $this->assertArrayHasKey('status', $snapshot);
        $this->assertArrayHasKey('completion', $snapshot);
        $this->assertArrayHasKey('grade', $snapshot);
        $this->assertArrayHasKey('inactive_days', $snapshot);
        $this->assertArrayHasKey('timestamp', $snapshot);
    }

    /**
     * Test intervention state transitions: update and dismiss.
     */
    public function test_intervention_update_and_dismiss(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        $manager = new intervention_manager();
        $id = $manager->create($student->id, $course->id, $teacher->id, 'CONTACT');

        // Update.
        $manager->update($id, [
            'actual_action' => 'Called student on phone',
            'status' => intervention_manager::STATUS_IN_PROGRESS,
        ]);

        $record = $DB->get_record('local_learningsuccess_int', ['id' => $id]);
        $this->assertEquals(intervention_manager::STATUS_IN_PROGRESS, $record->status);
        $this->assertEquals('Called student on phone', $record->actual_action);

        // Dismiss.
        $manager->dismiss($id);
        $dismissed = $DB->get_record('local_learningsuccess_int', ['id' => $id]);
        $this->assertEquals(intervention_manager::STATUS_DISMISSED, $dismissed->status);
    }

    /**
     * Test completion lifecycle captures after snapshot and computes outcome.
     */
    public function test_intervention_completion_lifecycle(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        $manager = new intervention_manager();
        $id = $manager->create($student->id, $course->id, $teacher->id, 'EXTENSION');

        $manager->transition_to($id, intervention_status::CONTACTED);
        $manager->complete($id, 'Student submitted assignment');

        $record = $DB->get_record('local_learningsuccess_int', ['id' => $id]);
        $this->assertEquals(intervention_manager::STATUS_COMPLETED, $record->status);
        $this->assertNotEmpty($record->after_snapshot);
        $this->assertNotNull($record->completed_at);
        $this->assertNotNull($record->before_snapshot_id);
        $this->assertNotNull($record->after_snapshot_id);
        $this->assertEquals('Student submitted assignment', $record->actual_action);

        // Verify after_snapshot serialization structure.
        $afterdata = json_decode($record->after_snapshot, true);
        $this->assertIsArray($afterdata);
        $this->assertArrayHasKey('risk_score', $afterdata);
        $this->assertArrayHasKey('status', $afterdata);
        $this->assertArrayHasKey('completion', $afterdata);
        $this->assertArrayHasKey('grade', $afterdata);
        $this->assertArrayHasKey('inactive_days', $afterdata);
        $this->assertArrayHasKey('timestamp', $afterdata);

        $this->assertContains($record->outcome, [
            outcome::IMPROVED,
            outcome::NO_CHANGE,
            outcome::DECLINED,
            outcome::UNKNOWN,
        ]);
    }

    /**
     * Test outcome calculation logic with simulated snapshots (arrays and JSON strings).
     */
    public function test_outcome_calculation(): void {
        $evaluator = new outcome_evaluator();

        $basebefore = [
            'risk_score' => 80,
            'completion' => 30,
            'grade' => 45,
            'status' => 'critical',
            'inactive_days' => 14,
            'timestamp' => time() - 3600,
        ];

        // 1. Improvement scenario: risk decrease >= 10.
        $afterriskreduced = [
            'risk_score' => 60,
            'completion' => 30,
            'grade' => 45,
        ];
        $this->assertEquals(outcome::IMPROVED, $evaluator->evaluate($basebefore, $afterriskreduced)->get_status());

        // 2. Improvement scenario: completion increase >= 15.
        $aftercompletionimproved = [
            'risk_score' => 75,
            'completion' => 50,
            'grade' => 45,
        ];
        $this->assertEquals(outcome::IMPROVED, $evaluator->evaluate($basebefore, $aftercompletionimproved)->get_status());

        // 3. Improvement scenario: grade increase >= 10.
        $aftergradeimproved = [
            'risk_score' => 75,
            'completion' => 30,
            'grade' => 60,
        ];
        $this->assertEquals(outcome::IMPROVED, $evaluator->evaluate($basebefore, $aftergradeimproved)->get_status());

        // 4. Decline scenario: risk increased by >= 15.
        $afterriskincreased = [
            'risk_score' => 100,
            'completion' => 30,
            'grade' => 45,
        ];
        $this->assertEquals(outcome::DECLINED, $evaluator->evaluate($basebefore, $afterriskincreased)->get_status());

        // 5. Decline scenario: grade decreased by >= 15.
        $aftergradedecreased = [
            'risk_score' => 80,
            'completion' => 30,
            'grade' => 25,
        ];
        $this->assertEquals(outcome::DECLINED, $evaluator->evaluate($basebefore, $aftergradedecreased)->get_status());

        // 6. No change scenario.
        $aftersame = [
            'risk_score' => 78,
            'completion' => 32,
            'grade' => 46,
        ];
        $this->assertEquals(outcome::NO_CHANGE, $evaluator->evaluate($basebefore, $aftersame)->get_status());

        // 7. Unknown scenario: missing snapshots.
        $this->assertEquals(outcome::UNKNOWN, $evaluator->evaluate(null, $aftersame)->get_status());
        $this->assertEquals(outcome::UNKNOWN, $evaluator->evaluate($basebefore, null)->get_status());
        $this->assertEquals(outcome::UNKNOWN, $evaluator->evaluate('', '')->get_status());

        // 8. JSON string inputs deserialization.
        $jsonbefore = json_encode($basebefore);
        $jsonafter = json_encode($afterriskreduced);
        $this->assertEquals(outcome::IMPROVED, $evaluator->evaluate($jsonbefore, $jsonafter)->get_status());
    }

    /**
     * Test course and student queries for interventions.
     */
    public function test_get_for_course_and_student(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        $manager = new intervention_manager();
        $manager->create($student1->id, $course->id, $teacher->id, 'CONTACT');
        $manager->create($student2->id, $course->id, $teacher->id, 'EXTENSION');

        $courseinterventions = $manager->get_for_course($course->id);
        $this->assertCount(2, $courseinterventions);

        $student1interventions = $manager->get_for_student($student1->id, $course->id);
        $this->assertCount(1, $student1interventions);
        $this->assertEquals($student1->id, reset($student1interventions)->userid);
    }
}
