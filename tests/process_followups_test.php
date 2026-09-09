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
use local_learningsuccess\task\process_followups;
use local_learningsuccess\local\intervention\intervention_status;

/**
 * Unit test suite for Phase 3 follow-up processing scheduled task.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_followups_test extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test scheduled task transitions due interventions and alerts teacher.
     */
    public function test_process_followups_execution(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();

        $now = time();

        // 1. Create an intervention with followup due in the past.
        $id1 = $DB->insert_record('local_ls_intervention', (object) [
            'userid' => $student->id,
            'courseid' => $course->id,
            'teacherid' => $teacher->id,
            'type' => 'checkin',
            'status' => intervention_status::WAITING,
            'outcome' => 'UNKNOWN',
            'followupat' => $now - 3600, // 1 hour ago.
            'timecreated' => $now - 86400,
            'timemodified' => $now - 86400,
        ]);

        // 2. Create another intervention with followup in the future (not due).
        $id2 = $DB->insert_record('local_ls_intervention', (object) [
            'userid' => $student->id,
            'courseid' => $course->id,
            'teacherid' => $teacher->id,
            'type' => 'checkin',
            'status' => intervention_status::WAITING,
            'outcome' => 'UNKNOWN',
            'followupat' => $now + 86400, // Tomorrow.
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        // Execute task.
        $sink = $this->redirectMessages();

        $task = new process_followups();
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $messages = $sink->get_messages();
        $sink->close();

        // Verify id1 status is now follow_up.
        $updated1 = $DB->get_record('local_ls_intervention', ['id' => $id1]);
        $this->assertEquals(intervention_status::FOLLOW_UP, $updated1->status);

        // Verify id2 status remains waiting.
        $updated2 = $DB->get_record('local_ls_intervention', ['id' => $id2]);
        $this->assertEquals(intervention_status::WAITING, $updated2->status);

        // Verify notification was sent.
        $this->assertNotEmpty($messages);
        $this->assertEquals($teacher->id, $messages[0]->useridto);
    }
}
