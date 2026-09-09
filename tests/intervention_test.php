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
use local_learningsuccess\local\intervention\intervention_manager;
use local_learningsuccess\local\intervention\outcome_tracker;

/**
 * Unit test suite verifying intervention lifecycle, state transitions, and outcome tracking.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class intervention_test extends advanced_testcase {

    protected function setUp(): void {
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

        $record = $DB->get_record('local_ls_intervention', ['id' => $id]);
        $this->assertNotEmpty($record);
        $this->assertEquals(intervention_manager::STATUS_OPEN, $record->status);
        $this->assertEquals(outcome_tracker::OUTCOME_UNKNOWN, $record->outcome);
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

        $record = $DB->get_record('local_ls_intervention', ['id' => $id]);
        $this->assertEquals(intervention_manager::STATUS_IN_PROGRESS, $record->status);
        $this->assertEquals('Called student on phone', $record->actual_action);

        // Dismiss.
        $manager->dismiss($id);
        $dismissed = $DB->get_record('local_ls_intervention', ['id' => $id]);
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

        $manager->complete($id, 'Student submitted assignment');

        $record = $DB->get_record('local_ls_intervention', ['id' => $id]);
        $this->assertEquals(intervention_manager::STATUS_COMPLETED, $record->status);
        $this->assertNotEmpty($record->after_snapshot);
        $this->assertNotNull($record->completed_at);
        $this->assertEquals('Student submitted assignment', $record->actual_action);

        // Verify after_snapshot serialization structure.
        $afterData = json_decode($record->after_snapshot, true);
        $this->assertIsArray($afterData);
        $this->assertArrayHasKey('risk_score', $afterData);
        $this->assertArrayHasKey('status', $afterData);
        $this->assertArrayHasKey('completion', $afterData);
        $this->assertArrayHasKey('grade', $afterData);
        $this->assertArrayHasKey('inactive_days', $afterData);
        $this->assertArrayHasKey('timestamp', $afterData);

        $this->assertContains($record->outcome, [
            outcome_tracker::OUTCOME_IMPROVED,
            outcome_tracker::OUTCOME_NO_CHANGE,
            outcome_tracker::OUTCOME_DECLINED,
            outcome_tracker::OUTCOME_UNKNOWN,
        ]);
    }

    /**
     * Test outcome tracker calculation logic with simulated snapshots (arrays and JSON strings).
     */
    public function test_outcome_calculation(): void {
        $tracker = new outcome_tracker();

        $baseBefore = [
            'risk_score' => 80,
            'completion' => 30,
            'grade' => 45,
            'status' => 'critical',
            'inactive_days' => 14,
            'timestamp' => time() - 3600,
        ];

        // 1. Improvement scenario: risk decrease >= 10.
        $afterRiskReduced = [
            'risk_score' => 60,
            'completion' => 30,
            'grade' => 45,
        ];
        $this->assertEquals(outcome_tracker::OUTCOME_IMPROVED, $tracker->calculate_outcome($baseBefore, $afterRiskReduced));

        // 2. Improvement scenario: completion increase >= 15.
        $afterCompletionImproved = [
            'risk_score' => 75,
            'completion' => 50,
            'grade' => 45,
        ];
        $this->assertEquals(outcome_tracker::OUTCOME_IMPROVED, $tracker->calculate_outcome($baseBefore, $afterCompletionImproved));

        // 3. Improvement scenario: grade increase >= 10.
        $afterGradeImproved = [
            'risk_score' => 75,
            'completion' => 30,
            'grade' => 60,
        ];
        $this->assertEquals(outcome_tracker::OUTCOME_IMPROVED, $tracker->calculate_outcome($baseBefore, $afterGradeImproved));

        // 4. Decline scenario: risk increased by >= 10.
        $afterRiskIncreased = [
            'risk_score' => 95,
            'completion' => 30,
            'grade' => 45,
        ];
        $this->assertEquals(outcome_tracker::OUTCOME_DECLINED, $tracker->calculate_outcome($baseBefore, $afterRiskIncreased));

        // 5. Decline scenario: grade decreased by >= 10.
        $afterGradeDecreased = [
            'risk_score' => 85,
            'completion' => 30,
            'grade' => 30,
        ];
        $this->assertEquals(outcome_tracker::OUTCOME_DECLINED, $tracker->calculate_outcome($baseBefore, $afterGradeDecreased));

        // 6. No change scenario.
        $afterSame = [
            'risk_score' => 78,
            'completion' => 32,
            'grade' => 46,
        ];
        $this->assertEquals(outcome_tracker::OUTCOME_NO_CHANGE, $tracker->calculate_outcome($baseBefore, $afterSame));

        // 7. Unknown scenario: missing snapshots.
        $this->assertEquals(outcome_tracker::OUTCOME_UNKNOWN, $tracker->calculate_outcome(null, $afterSame));
        $this->assertEquals(outcome_tracker::OUTCOME_UNKNOWN, $tracker->calculate_outcome($baseBefore, null));
        $this->assertEquals(outcome_tracker::OUTCOME_UNKNOWN, $tracker->calculate_outcome('', ''));

        // 8. JSON string inputs deserialization.
        $jsonBefore = json_encode($baseBefore);
        $jsonAfter = json_encode($afterRiskReduced);
        $this->assertEquals(outcome_tracker::OUTCOME_IMPROVED, $tracker->calculate_outcome($jsonBefore, $jsonAfter));
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

        $courseInterventions = $manager->get_for_course($course->id);
        $this->assertCount(2, $courseInterventions);

        $student1Interventions = $manager->get_for_student($student1->id, $course->id);
        $this->assertCount(1, $student1Interventions);
        $this->assertEquals($student1->id, reset($student1Interventions)->userid);
    }
}
