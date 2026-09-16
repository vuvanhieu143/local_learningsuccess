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
use local_learningsuccess\local\recommendation\recommendation;
use local_learningsuccess\local\recommendation\recommendation_engine;
use local_learningsuccess\local\intervention\intervention;
use local_learningsuccess\local\intervention\intervention_status;
use local_learningsuccess\local\intervention\intervention_manager;

/**
 * Unit test suite for Phase 2 Recommendations, Interventions Domain, and Teacher Notes.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recommendation_test extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test recommendation value object and serialization.
     */
    public function test_recommendation_value_object(): void {
        $rec = new recommendation(
            type: 'checkin',
            title: 'Contact Student',
            action: 'Send check-in message',
            reason: 'Inactive for 8 days',
            urgency: recommendation::URGENCY_HIGH,
            suggestedmessage: 'Hi student'
        );

        $this->assertEquals('checkin', $rec->get_type());
        $this->assertEquals('Contact Student', $rec->get_title());
        $this->assertEquals('Send check-in message', $rec->get_action());
        $this->assertEquals('Inactive for 8 days', $rec->get_reason());
        $this->assertEquals(recommendation::URGENCY_HIGH, $rec->get_urgency());
        $this->assertEquals('Hi student', $rec->get_suggested_message());

        $arr = $rec->to_array();
        $this->assertEquals('checkin', $arr['type']);
        $this->assertEquals('high', $arr['urgency']);
    }

    /**
     * Test recommendation_engine matching signals to actionable recommendations.
     */
    public function test_recommendation_engine_matching(): void {
        $signals = [
            ['type' => 'inactivity', 'value' => 10, 'severity' => 'warning'],
            ['type' => 'overdue', 'value' => 3, 'severity' => 'critical'],
        ];

        $engine = new recommendation_engine();
        $recommendations = $engine->build($signals);

        $this->assertNotEmpty($recommendations);
        // Overdue has critical severity -> higher urgency -> should be first or high priority.
        $types = array_column($recommendations, 'type');
        $this->assertContains('checkin', $types);
        $this->assertContains('assignment_support', $types);
    }

    /**
     * Test intervention_status validation.
     */
    public function test_intervention_status_lifecycle(): void {
        $this->assertTrue(intervention_status::is_active(intervention_status::OPEN));
        $this->assertTrue(intervention_status::is_active(intervention_status::WAITING));
        $this->assertTrue(intervention_status::is_closed(intervention_status::RESOLVED));
        $this->assertTrue(intervention_status::is_closed(intervention_status::DISMISSED));

        $this->assertTrue(intervention_status::is_valid_transition(intervention_status::CONTACTED, intervention_status::RESOLVED));
        $this->assertFalse(intervention_status::is_valid_transition(intervention_status::RESOLVED, intervention_status::OPEN));
    }

    /**
     * Test intervention entity and follow-up checking.
     */
    public function test_intervention_entity(): void {
        $now = time();
        $record = (object) [
            'id' => 1,
            'courseid' => 10,
            'userid' => 20,
            'teacherid' => 30,
            'type' => 'checkin',
            'status' => intervention_status::WAITING,
            'reason' => 'Inactive',
            'actual_action' => 'Sent message',
            'followupat' => $now - 100,
            'resolvedat' => null,
            'outcome' => 'UNKNOWN',
            'timecreated' => $now - 200,
            'timemodified' => $now - 100,
        ];

        $entity = intervention::from_record($record);
        $this->assertEquals(1, $entity->id);
        $this->assertEquals(intervention_status::WAITING, $entity->status);
        $this->assertTrue($entity->is_followup_due($now));
    }

    /**
     * Test intervention_manager creating intervention with follow-up and notes.
     */
    public function test_intervention_manager_notes_and_followup(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();

        $manager = new intervention_manager();
        $futurefollowup = time() + (3 * DAYSECS);

        $id = $manager->create(
            userid: $student->id,
            courseid: $course->id,
            teacherid: $teacher->id,
            type: 'checkin',
            reason: 'Inactive',
            recommended: 'Send message',
            actual: 'Teacher sent checkin note',
            sendmessage: false,
            followupat: $futurefollowup
        );

        $this->assertGreaterThan(0, $id);

        // Add note.
        $noteid = $manager->add_note($id, $teacher->id, 'Student replied on chat asking for assignment extension.');
        $this->assertGreaterThan(0, $noteid);

        $notes = $manager->get_notes($id);
        $this->assertCount(1, $notes);
        $note = reset($notes);
        $this->assertEquals('Student replied on chat asking for assignment extension.', $note->note);
    }
}
