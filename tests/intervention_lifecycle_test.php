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
use local_learningsuccess\local\intervention\intervention_status;
use moodle_exception;

/**
 * Unit test suite verifying the normalized intervention lifecycle state-machine.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class intervention_lifecycle_test extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test valid lifecycle progression: OPEN -> CONTACTED -> WAITING -> FOLLOW_UP -> COMPLETED.
     */
    public function test_valid_lifecycle_progression(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        $manager = new intervention_manager();

        // 1. Create -> OPEN.
        $id = $manager->create($student->id, $course->id, $teacher->id, 'checkin', 'Needs checkin');
        $record = $DB->get_record('local_ls_intervention', ['id' => $id]);
        $this->assertEquals(intervention_status::OPEN, $record->status);

        // 2. Transition OPEN -> CONTACTED.
        $this->assertTrue(intervention_status::can_transition(intervention_status::OPEN, intervention_status::CONTACTED));
        $manager->transition_to($id, intervention_status::CONTACTED, 'Sent message to student', $teacher->id);
        $record = $DB->get_record('local_ls_intervention', ['id' => $id]);
        $this->assertEquals(intervention_status::CONTACTED, $record->status);

        // Verify note was attached.
        $notes = $manager->get_notes($id);
        $this->assertCount(1, $notes);
        $this->assertEquals('Sent message to student', reset($notes)->note);

        // 3. Transition CONTACTED -> WAITING.
        $this->assertTrue(intervention_status::can_transition(intervention_status::CONTACTED, intervention_status::WAITING));
        $manager->transition_to($id, intervention_status::WAITING);
        $record = $DB->get_record('local_ls_intervention', ['id' => $id]);
        $this->assertEquals(intervention_status::WAITING, $record->status);

        // 4. Transition WAITING -> FOLLOW_UP.
        $this->assertTrue(intervention_status::can_transition(intervention_status::WAITING, intervention_status::FOLLOW_UP));
        $manager->transition_to($id, intervention_status::FOLLOW_UP);
        $record = $DB->get_record('local_ls_intervention', ['id' => $id]);
        $this->assertEquals(intervention_status::FOLLOW_UP, $record->status);

        // 5. Transition FOLLOW_UP -> COMPLETED.
        $this->assertTrue(intervention_status::can_transition(intervention_status::FOLLOW_UP, intervention_status::COMPLETED));
        $manager->transition_to($id, intervention_status::COMPLETED, 'Student completed catchup');
        $record = $DB->get_record('local_ls_intervention', ['id' => $id]);
        $this->assertEquals(intervention_status::COMPLETED, $record->status);
        $this->assertNotNull($record->completed_at);
        $this->assertNotEmpty($record->after_snapshot);
    }

    /**
     * Test invalid lifecycle transitions throw exception.
     */
    public function test_invalid_lifecycle_transitions(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        $manager = new intervention_manager();
        $id = $manager->create($student->id, $course->id, $teacher->id, 'meeting');

        // Transition to COMPLETED.
        $manager->transition_to($id, intervention_status::COMPLETED);

        // Attempt invalid COMPLETED -> OPEN transition.
        $this->assertFalse(intervention_status::can_transition(intervention_status::COMPLETED, intervention_status::OPEN));
        $this->expectException(moodle_exception::class);
        $manager->transition_to($id, intervention_status::OPEN);
    }

    /**
     * Test dismissal lifecycle path: OPEN -> DISMISSED.
     */
    public function test_dismissal_lifecycle(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        $manager = new intervention_manager();
        $id = $manager->create($student->id, $course->id, $teacher->id, 'resource');

        $manager->transition_to($id, intervention_status::DISMISSED);
        $record = $DB->get_record('local_ls_intervention', ['id' => $id]);
        $this->assertEquals(intervention_status::DISMISSED, $record->status);

        // Dismissed cannot transition to CONTACTED.
        $this->assertFalse(intervention_status::can_transition(intervention_status::DISMISSED, intervention_status::CONTACTED));
    }
}
