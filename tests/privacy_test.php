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
use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_learningsuccess\privacy\provider;
use local_learningsuccess\local\intervention\intervention_manager;
use local_learningsuccess\local\signal\signal_collector;

/**
 * Unit test suite verifying GDPR Privacy API compliance: metadata, export, and deletion.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class privacy_test extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test metadata collection returns declared database tables.
     */
    public function test_get_metadata(): void {
        $collection = new collection('local_learningsuccess');
        $collection = provider::get_metadata($collection);

        $this->assertNotEmpty($collection->get_collection());

        $tablenames = [];
        foreach ($collection->get_collection() as $item) {
            $tablenames[] = $item->get_name();
        }

        $this->assertContains('local_ls_intervention', $tablenames);
        $this->assertContains('local_ls_signal', $tablenames);
    }

    /**
     * Test context retrieval for both target student and intervening teacher.
     */
    public function test_get_contexts_for_userid(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        $manager = new intervention_manager();
        $manager->create($student->id, $course->id, $teacher->id, 'CONTACT');

        $context = context_course::instance($course->id);

        $studentContexts = provider::get_contexts_for_userid($student->id);
        $this->assertContains($context->id, $studentContexts->get_contextids());

        $teacherContexts = provider::get_contexts_for_userid($teacher->id);
        $this->assertContains($context->id, $teacherContexts->get_contextids());
    }

    /**
     * Test userlist retrieval within course context.
     */
    public function test_get_users_in_context(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        $manager = new intervention_manager();
        $manager->create($student->id, $course->id, $teacher->id, 'CONTACT');

        $context = context_course::instance($course->id);
        $userlist = new userlist($context, 'local_learningsuccess');
        provider::get_users_in_context($userlist);

        $this->assertContains($student->id, $userlist->get_userids());
        $this->assertContains($teacher->id, $userlist->get_userids());
    }

    /**
     * Test data export for a user.
     */
    public function test_export_user_data(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        $manager = new intervention_manager();
        $manager->create($student->id, $course->id, $teacher->id, 'CONTACT', 'Inactivity reason', 'Check in');

        $collector = new signal_collector();
        $collector->collect($student->id, $course->id, true);

        $context = context_course::instance($course->id);
        $approvedlist = new approved_contextlist($student, 'local_learningsuccess', [$context->id]);

        provider::export_user_data($approvedlist);

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        $exportedInterventions = $writer->get_data([
            get_string('pluginname', 'local_learningsuccess'),
            get_string('interventions', 'local_learningsuccess'),
        ]);
        $this->assertNotEmpty($exportedInterventions);
        $this->assertNotEmpty($exportedInterventions->interventions);
        $this->assertEquals('CONTACT', $exportedInterventions->interventions[0]['type']);

        $exportedSignals = $writer->get_data([
            get_string('pluginname', 'local_learningsuccess'),
            get_string('signals', 'local_learningsuccess'),
        ]);
        $this->assertNotEmpty($exportedSignals);
        $this->assertNotEmpty($exportedSignals->signals);
    }

    /**
     * Test context retrieval and individual user data deletion.
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        $manager = new intervention_manager();
        $manager->create($student->id, $course->id, $teacher->id, 'CONTACT');

        $collector = new signal_collector();
        $collector->collect($student->id, $course->id, true);

        $context = context_course::instance($course->id);
        $approvedlist = new approved_contextlist($student, 'local_learningsuccess', [$context->id]);
        provider::delete_data_for_user($approvedlist);

        $this->assertEquals(0, $DB->count_records('local_ls_intervention', ['userid' => $student->id]));
        $this->assertEquals(0, $DB->count_records('local_ls_signal', ['userid' => $student->id]));
    }

    /**
     * Test purging all data for all users within a course context.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        $manager = new intervention_manager();
        $manager->create($student1->id, $course->id, $teacher->id, 'CONTACT');
        $manager->create($student2->id, $course->id, $teacher->id, 'EXTENSION');

        $context = context_course::instance($course->id);
        provider::delete_data_for_all_users_in_context($context);

        $this->assertEquals(0, $DB->count_records('local_ls_intervention', ['courseid' => $course->id]));
        $this->assertEquals(0, $DB->count_records('local_ls_signal', ['courseid' => $course->id]));
    }

    /**
     * Test batch user deletion within a context via approved_userlist.
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        $manager = new intervention_manager();
        $manager->create($student1->id, $course->id, $teacher->id, 'CONTACT');
        $manager->create($student2->id, $course->id, $teacher->id, 'EXTENSION');

        $context = context_course::instance($course->id);
        $approveduserlist = new approved_userlist($context, 'local_learningsuccess', [$student1->id]);
        provider::delete_data_for_users($approveduserlist);

        // student1 data should be deleted, student2 should remain.
        $this->assertEquals(0, $DB->count_records('local_ls_intervention', ['userid' => $student1->id]));
        $this->assertEquals(1, $DB->count_records('local_ls_intervention', ['userid' => $student2->id]));
    }
}
