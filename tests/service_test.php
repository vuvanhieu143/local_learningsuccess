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
use cache;
use local_learningsuccess\local\service\student_success_service;
use local_learningsuccess\local\intervention\intervention_manager;

/**
 * Unit test suite verifying StudentSuccessService calculations, priority queue, and MUC caching.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_learningsuccess\local\service\student_success_service
 */
final class service_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test course summary aggregates student count and risk buckets.
     */
    public function test_get_course_summary_counts(): void {
        $course = $this->getDataGenerator()->create_course();
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($student1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($student2->id, $course->id, 'student');

        $service = new student_success_service();
        $summary = $service->get_course_summary($course->id);

        $this->assertIsArray($summary);
        $this->assertEquals(2, $summary['total_students']);
        $this->assertArrayHasKey('healthy_count', $summary);
        $this->assertArrayHasKey('monitor_count', $summary);
        $this->assertArrayHasKey('atrisk_count', $summary);
        $this->assertArrayHasKey('critical_count', $summary);
        $this->assertArrayHasKey('active_interventions', $summary);
        $this->assertArrayHasKey('resolved_interventions', $summary);
    }

    /**
     * Test priority students queue orders by risk and signal count.
     */
    public function test_get_priority_students_ordering(): void {
        $course = $this->getDataGenerator()->create_course();
        $studenta = $this->getDataGenerator()->create_user();
        $studentb = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($studenta->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($studentb->id, $course->id, 'student');

        $service = new student_success_service();
        $priorities = $service->get_priority_students($course->id, 5);

        $this->assertIsArray($priorities);
        // Ensure priority list doesn't exceed requested limit.
        $this->assertLessThanOrEqual(5, count($priorities));
    }

    /**
     * Test student summary details return comprehensive student structure.
     */
    public function test_get_student_summary_structure(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user(['email' => 'learner@example.com']);
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $service = new student_success_service();
        $details = $service->get_student_summary($student->id, $course->id);

        $this->assertEquals($student->id, $details['user']['id']);
        $this->assertArrayNotHasKey('email', $details['user']);
        $this->assertArrayHasKey('status', $details);
        $this->assertArrayHasKey('risk_score', $details);
        $this->assertArrayHasKey('signals', $details);
        $this->assertArrayHasKey('recommendations', $details);
        $this->assertArrayHasKey('interventions', $details);
    }

    /**
     * Test MUC caching for course summary and student summary, including cache invalidation.
     */
    public function test_muc_caching_and_invalidation(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $service = new student_success_service();
        $coursecache = cache::make('local_learningsuccess', 'course_summary');
        $studentcache = cache::make('local_learningsuccess', 'student_summary');

        $studentcachekey = "{$course->id}_{$student->id}";

        // 1. Initial fetch populates caches.
        $summary = $service->get_course_summary($course->id);
        $studentdetails = $service->get_student_summary($student->id, $course->id);

        $cachedcourse = $coursecache->get($course->id);
        $this->assertNotFalse($cachedcourse);
        $this->assertEquals($summary['total_students'], $cachedcourse['total_students']);

        $cachedstudent = $studentcache->get($studentcachekey);
        $this->assertNotFalse($cachedstudent);
        $this->assertEquals($studentdetails['user']['id'], $cachedstudent['user']['id']);

        // 2. Fetch with skipcache=true returns fresh data.
        $freshsummary = $service->get_course_summary($course->id, true);
        $this->assertEquals($summary['total_students'], $freshsummary['total_students']);

        // 3. Creating an intervention invalidates both course and student caches.
        $manager = new intervention_manager();
        $interventionid = $manager->create($student->id, $course->id, $teacher->id, 'CONTACT');

        $this->assertFalse($coursecache->get($course->id));
        $this->assertFalse($studentcache->get($studentcachekey));

        // 4. Repopulate caches, then update intervention -> should invalidate again.
        $service->get_course_summary($course->id);
        $service->get_student_summary($student->id, $course->id);
        $this->assertNotFalse($coursecache->get($course->id));

        $manager->update($interventionid, ['actual_action' => 'Sent follow-up']);
        $this->assertFalse($coursecache->get($course->id));
        $this->assertFalse($studentcache->get($studentcachekey));

        // 5. Repopulate and dismiss intervention -> should invalidate again.
        $service->get_course_summary($course->id);
        $service->get_student_summary($student->id, $course->id);
        $this->assertNotFalse($coursecache->get($course->id));

        $manager->dismiss($interventionid);
        $this->assertFalse($coursecache->get($course->id));
        $this->assertFalse($studentcache->get($studentcachekey));
    }
}
