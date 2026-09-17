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
use context_course;
use dml_missing_record_exception;
use local_learningsuccess\classes\external\dashboard_exporter;
use local_learningsuccess\local\helper\access_helper;
use moodle_exception;
use required_capability_exception;

/**
 * Unit tests covering access control boundaries, separate groups isolation, and capability enforcement.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_learningsuccess\local\helper\access_helper
 */
final class access_control_edge_cases_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test validate_student_access throws usernotenrolled when student is not enrolled.
     */
    public function test_validate_student_access_unenrolled_throws_exception(): void {
        $course = $this->getDataGenerator()->create_course();
        $stranger = $this->getDataGenerator()->create_user();
        $context = context_course::instance($course->id);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('usernotenrolled', 'local_learningsuccess'));
        access_helper::validate_student_access($course, $context, $stranger->id);
    }

    /**
     * Test validate_student_access enforces SEPARATEGROUPS isolation.
     */
    public function test_separate_groups_without_accessallgroups_denied(): void {
        $course = $this->getDataGenerator()->create_course(['groupmode' => SEPARATEGROUPS]);
        $teacher = $this->getDataGenerator()->create_user();
        $studenta = $this->getDataGenerator()->create_user();
        $studentb = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'teacher');
        $this->getDataGenerator()->enrol_user($studenta->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($studentb->id, $course->id, 'student');

        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $teacher->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $studenta->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupb->id, 'userid' => $studentb->id]);

        $this->setUser($teacher);
        $context = context_course::instance($course->id);

        // Student A in same group should succeed.
        $resolved = access_helper::validate_student_access($course, $context, $studenta->id);
        $this->assertEquals($course->id, $resolved->id);

        // Student B in disjoint group must throw nopermissiontoviewstudent.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('nopermissiontoviewstudent', 'local_learningsuccess'));
        access_helper::validate_student_access($course, $context, $studentb->id);
    }

    /**
     * Test users with accessallgroups can access students across any separate group.
     */
    public function test_separate_groups_with_accessallgroups_allowed(): void {
        $course = $this->getDataGenerator()->create_course(['groupmode' => SEPARATEGROUPS]);
        $manager = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($manager->id, $course->id, 'manager');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);

        $this->setUser($manager);
        $context = context_course::instance($course->id);

        $resolved = access_helper::validate_student_access($course, $context, $student->id);
        $this->assertEquals($course->id, $resolved->id);
    }

    /**
     * Test resolve_group_scope rejects unauthorized group ID under SEPARATEGROUPS.
     */
    public function test_resolve_group_scope_unauthorized_group(): void {
        $course = $this->getDataGenerator()->create_course(['groupmode' => SEPARATEGROUPS]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'teacher');

        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $teacher->id]);

        $this->setUser($teacher);
        $context = context_course::instance($course->id);

        // Accessing Group B which teacher does not belong to.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('nopermissiontoviewgroup', 'local_learningsuccess'));
        access_helper::resolve_group_scope($course, $context, $groupb->id);
    }

    /**
     * Test resolve_group_scope defaults to teacher's first group when groupid is 0.
     */
    public function test_resolve_group_scope_default_selection(): void {
        $course = $this->getDataGenerator()->create_course(['groupmode' => SEPARATEGROUPS]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'teacher');

        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $teacher->id]);

        $this->setUser($teacher);
        $context = context_course::instance($course->id);

        [$resolvedid, $allowed] = access_helper::resolve_group_scope($course, $context, 0);
        $this->assertEquals($groupa->id, $resolvedid);
        $this->assertArrayHasKey($groupa->id, $allowed);
    }

    /**
     * Test validate_intervention_access with invalid ID throws exception.
     */
    public function test_validate_intervention_access_nonexistent(): void {
        $this->expectException(dml_missing_record_exception::class);
        access_helper::validate_intervention_access(999999);
    }

    /**
     * Test external API complete_intervention requires manageintervention capability.
     */
    public function test_complete_intervention_capability_denied(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'teacher');

        $interventionid = $DB->insert_record('local_learningsuccess_int', (object) [
            'userid' => $student->id,
            'courseid' => $course->id,
            'teacherid' => $teacher->id,
            'type' => 'CONTACT',
            'status' => 'contacted',
            'outcome' => 'UNKNOWN',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $this->setUser($student);
        $this->expectException(required_capability_exception::class);
        \local_learningsuccess\external\dashboard_exporter::complete_intervention($interventionid, 'Done');
    }
}
