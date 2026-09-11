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
use local_learningsuccess\local\outcome\outcome;
use local_learningsuccess\local\outcome\outcome_evaluator;
use local_learningsuccess\local\outcome\snapshot_service;
use local_learningsuccess\local\recommendation\recommendation_engine;

/**
 * Unit tests for recommendation follow-up defaults, evidence-based outcome evaluation, and snapshots.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class outcome_evaluator_test extends advanced_testcase {

    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test recommendation-specific follow-up duration defaults.
     */
    public function test_recommendation_followup_duration_defaults(): void {
        $this->assertEquals(3 * DAYSECS, recommendation_engine::get_default_followup_duration('checkin'));
        $this->assertEquals(3 * DAYSECS, recommendation_engine::get_default_followup_duration('contact'));
        $this->assertEquals(3 * DAYSECS, recommendation_engine::get_default_followup_duration('meeting'));
        $this->assertEquals(7 * DAYSECS, recommendation_engine::get_default_followup_duration('assignment_support'));
        $this->assertEquals(7 * DAYSECS, recommendation_engine::get_default_followup_duration('extension'));
        $this->assertEquals(7 * DAYSECS, recommendation_engine::get_default_followup_duration('learning_resource'));
    }

    /**
     * Test system evidence calculation with neutral wording.
     */
    public function test_system_evidence_evaluation(): void {
        $evaluator = new outcome_evaluator();

        $before = ['risk_score' => 80.0, 'completion' => 40.0, 'grade' => 45.0];
        $after = ['risk_score' => 50.0, 'completion' => 65.0, 'grade' => 60.0];

        $evidence = $evaluator->evaluate_system_evidence($before, $after);

        $this->assertEquals(outcome::IMPROVED, $evidence['status']);
        $this->assertEquals(30.0, $evidence['risk_delta']);
        $this->assertEquals(25.0, $evidence['completion_delta']);
        $this->assertEquals(15.0, $evidence['grade_delta']);
        $this->assertNotEmpty($evidence['summary']);
    }

    /**
     * Test intervention completion with teacher-confirmed outcome and canonical snapshot recording.
     */
    public function test_intervention_completion_and_canonical_snapshots(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();

        $manager = new intervention_manager();
        $snapshotservice = new snapshot_service();

        // 1. Create checkin intervention (should auto-default follow-up to 3 days).
        $beforetime = time();
        $id = $manager->create(
            userid: $user->id,
            courseid: $course->id,
            teacherid: $teacher->id,
            type: 'checkin',
            reason: 'High inactivity',
            recommended: 'Send check-in',
            actual: 'Check-in message sent'
        );

        $interv = $manager->get_for_student($user->id, $course->id)[$id];
        $this->assertGreaterThanOrEqual($beforetime + (3 * DAYSECS) - 5, (int) $interv->followupat);

        // 2. Transition to CONTACTED first, then complete with teacher-confirmed outcome UNABLE_TO_CONTACT.
        $manager->transition_to($id, intervention_status::CONTACTED);
        $success = $manager->complete(
            id: $id,
            actualaction: 'Student did not reply to multiple emails',
            teacheroutcome: outcome::UNABLE_TO_CONTACT
        );
        $this->assertTrue($success);

        $completed = $manager->get_for_student($user->id, $course->id)[$id];
        $this->assertEquals(outcome::UNABLE_TO_CONTACT, $completed->outcome);
        $this->assertEquals(outcome::UNABLE_TO_CONTACT, $completed->teacher_outcome);
        $this->assertNotNull($completed->system_outcome);
        $this->assertNotNull($completed->before_snapshot_id);
        $this->assertNotNull($completed->after_snapshot_id);

        // 3. Verify canonical snapshots exist for both 'before' and 'followup'.
        $snapshots = $snapshotservice->get_snapshots_for_intervention($id);
        $this->assertNotNull($snapshots['before']);
        $this->assertNotNull($snapshots['followup']);
        $this->assertEquals($id, $snapshots['before']->interventionid);
        $this->assertEquals($id, $snapshots['followup']->interventionid);
        $this->assertEquals($completed->before_snapshot_id, $snapshots['before']->id);
        $this->assertEquals($completed->after_snapshot_id, $snapshots['followup']->id);
    }
}
