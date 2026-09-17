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
use moodle_exception;

/**
 * Unit tests for intervention lifecycle edge cases, terminal state immutability, and multi-note tracking.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_learningsuccess\local\intervention\intervention_manager
 */
final class intervention_edge_cases_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test terminal states (COMPLETED, DISMISSED, NOT_APPLICABLE) reject further transitions.
     */
    public function test_terminal_state_immutability(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        $manager = new intervention_manager();

        // 1. Create and dismiss.
        $id1 = $manager->create($student->id, $course->id, $teacher->id, 'resource');
        $manager->transition_to($id1, intervention_status::DISMISSED);

        $this->assertFalse(intervention_status::can_transition(intervention_status::DISMISSED, intervention_status::OPEN));
        $this->assertFalse(intervention_status::can_transition(intervention_status::DISMISSED, intervention_status::CONTACTED));
        $this->assertFalse(intervention_status::can_transition(intervention_status::DISMISSED, intervention_status::COMPLETED));

        $this->expectException(moodle_exception::class);
        $manager->transition_to($id1, intervention_status::OPEN);
    }

    /**
     * Test completed intervention cannot be transitioned to any other state.
     */
    public function test_completed_intervention_cannot_transition(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        $manager = new intervention_manager();
        $id = $manager->create($student->id, $course->id, $teacher->id, 'meeting');

        $manager->transition_to($id, intervention_status::CONTACTED);
        $manager->complete($id, 'Meeting held');

        $this->assertFalse(intervention_status::can_transition(intervention_status::COMPLETED, intervention_status::DISMISSED));

        $this->expectException(moodle_exception::class);
        $manager->transition_to($id, intervention_status::DISMISSED);
    }

    /**
     * Test UNABLE_TO_CONTACT workflow branch and subsequent resolution.
     */
    public function test_unable_to_contact_lifecycle(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        $manager = new intervention_manager();
        $id = $manager->create($student->id, $course->id, $teacher->id, 'checkin');

        // OPEN -> CONTACTED.
        $manager->transition_to($id, intervention_status::CONTACTED, 'Sent email');

        // CONTACTED -> UNABLE_TO_CONTACT.
        $this->assertTrue(intervention_status::can_transition(
            intervention_status::CONTACTED,
            intervention_status::UNABLE_TO_CONTACT
        ));
        $manager->transition_to($id, intervention_status::UNABLE_TO_CONTACT, 'Phone disconnected');
        $record = $DB->get_record('local_learningsuccess_int', ['id' => $id]);
        $this->assertEquals(intervention_status::UNABLE_TO_CONTACT, $record->status);

        // UNABLE_TO_CONTACT -> DISMISSED.
        $this->assertTrue(intervention_status::can_transition(
            intervention_status::UNABLE_TO_CONTACT,
            intervention_status::DISMISSED
        ));
        $manager->transition_to($id, intervention_status::DISMISSED, 'Student uncontactable');
        $record = $DB->get_record('local_learningsuccess_int', ['id' => $id]);
        $this->assertEquals(intervention_status::DISMISSED, $record->status);
    }

    /**
     * Test FOLLOW_UP to NOT_APPLICABLE workflow transition.
     */
    public function test_follow_up_to_not_applicable(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        $manager = new intervention_manager();
        $id = $manager->create($student->id, $course->id, $teacher->id, 'extension');

        $manager->transition_to($id, intervention_status::CONTACTED);
        $manager->transition_to($id, intervention_status::FOLLOW_UP);

        $this->assertTrue(intervention_status::can_transition(intervention_status::FOLLOW_UP, intervention_status::NOT_APPLICABLE));
        $manager->transition_to($id, intervention_status::NOT_APPLICABLE, 'Student withdrew from module');

        $record = $DB->get_record('local_learningsuccess_int', ['id' => $id]);
        $this->assertEquals(intervention_status::NOT_APPLICABLE, $record->status);
    }

    /**
     * Test multiple teacher notes are recorded chronologically.
     */
    public function test_multiple_teacher_notes(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher1 = $this->getDataGenerator()->create_user();
        $teacher2 = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        $manager = new intervention_manager();
        $id = $manager->create($student->id, $course->id, $teacher1->id, 'meeting');

        $note1id = $manager->add_note($id, $teacher1->id, 'First attempt to call');
        $note2id = $manager->add_note($id, $teacher2->id, 'Second attempt via advisor');

        $notes = array_values($manager->get_notes($id));
        $this->assertCount(2, $notes);
        $this->assertEquals('First attempt to call', $notes[0]->note);
        $this->assertEquals($teacher1->id, $notes[0]->authorid);
        $this->assertEquals('Second attempt via advisor', $notes[1]->note);
        $this->assertEquals($teacher2->id, $notes[1]->authorid);
    }

    /**
     * Test outcome evaluator handles malformed or empty snapshots without throwing errors.
     */
    public function test_outcome_evaluator_malformed_snapshots(): void {
        $evaluator = new outcome_evaluator();

        // Empty before snapshot.
        $outcomeobj = $evaluator->evaluate('', ['riskscore' => 50, 'gradepct' => 70]);
        $this->assertEquals(outcome::UNKNOWN, $outcomeobj->get_status());

        // Empty after snapshot.
        $outcomeobj2 = $evaluator->evaluate(json_encode(['riskscore' => 80]), []);
        $this->assertEquals(outcome::UNKNOWN, $outcomeobj2->get_status());
    }
}
