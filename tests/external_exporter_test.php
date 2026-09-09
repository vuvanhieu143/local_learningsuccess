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
use required_capability_exception;
use local_learningsuccess\external\dashboard_exporter;
use local_learningsuccess\output\dashboard;
use local_learningsuccess\output\student_detail;

/**
 * Unit tests for external Web Services and Output renderables.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external_exporter_test extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test get_dashboard_data external function with capabilities and structure.
     */
    public function test_get_dashboard_data(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $this->setUser($teacher);

        $data = dashboard_exporter::get_dashboard_data($course->id);

        $this->assertArrayHasKey('total_students', $data);
        $this->assertArrayHasKey('healthy_count', $data);
        $this->assertArrayHasKey('monitor_count', $data);
        $this->assertArrayHasKey('atrisk_count', $data);
        $this->assertArrayHasKey('critical_count', $data);
        $this->assertArrayHasKey('active_interventions', $data);
        $this->assertArrayHasKey('resolved_interventions', $data);
        $this->assertArrayHasKey('priorities', $data);
        $this->assertGreaterThanOrEqual(1, $data['total_students']);
    }

    /**
     * Test get_dashboard_data throws exception when user lacks viewcourse capability.
     */
    public function test_get_dashboard_data_capability_denied(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(required_capability_exception::class);
        dashboard_exporter::get_dashboard_data($course->id);
    }

    /**
     * Test create_intervention, complete_intervention, and dismiss_intervention via external API.
     */
    public function test_intervention_external_lifecycle(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $this->setUser($teacher);

        // 1. Create intervention.
        $createResult = dashboard_exporter::create_intervention(
            $course->id,
            $student->id,
            'CONTACT',
            'Inactivity detected',
            'Send check-in message',
            'Message dispatched'
        );

        $this->assertTrue($createResult['success']);
        $this->assertGreaterThan(0, $createResult['id']);
        $interventionId = $createResult['id'];

        // 2. Complete intervention.
        $completeResult = dashboard_exporter::complete_intervention($interventionId, 'Resolved with student.');
        $this->assertTrue($completeResult['success']);
        $this->assertEquals('COMPLETED', $completeResult['status']);

        // 3. Create second intervention and dismiss.
        $createResult2 = dashboard_exporter::create_intervention(
            $course->id,
            $student->id,
            'EXTENSION',
            'Missed quiz',
            'Grant 2-day extension'
        );
        $dismissResult = dashboard_exporter::dismiss_intervention($createResult2['id']);
        $this->assertTrue($dismissResult['success']);
        $this->assertEquals('DISMISSED', $dismissResult['status']);
    }

    /**
     * Test create_intervention throws exception when user lacks createintervention capability.
     */
    public function test_create_intervention_capability_denied(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(required_capability_exception::class);
        dashboard_exporter::create_intervention($course->id, $student->id, 'CONTACT');
    }

    /**
     * Test dashboard renderable export_for_template.
     */
    public function test_dashboard_renderable(): void {
        global $PAGE;

        $renderer = $PAGE->get_renderer('core');
        $summary = [
            'total_students' => 20,
            'healthy_count' => 15,
            'monitor_count' => 3,
            'atrisk_count' => 1,
            'critical_count' => 1,
            'active_interventions' => 2,
            'resolved_interventions' => 4,
        ];
        $priorities = [
            [
                'userid' => 10,
                'fullname' => 'Test Student',
                'status' => 'critical',
                'status_label' => 'Critical',
                'risk_score' => 90,
                'signal_count' => 2,
                'signals' => [
                    ['message' => 'Inactive 14 days', 'severity' => 'critical'],
                ],
            ],
        ];

        $dashboard = new dashboard(1, $summary, $priorities);
        $exported = $dashboard->export_for_template($renderer);

        $this->assertEquals(1, $exported['courseid']);
        $this->assertTrue($exported['has_priorities']);
        $this->assertCount(1, $exported['priorities']);
        $this->assertTrue($exported['priorities'][0]['status_is_critical']);
        $this->assertFalse($exported['priorities'][0]['status_is_healthy']);
        $this->assertEquals(1, $exported['priorities'][0]['courseid']);
    }

    /**
     * Test student_detail renderable export_for_template.
     */
    public function test_student_detail_renderable(): void {
        global $PAGE;

        $renderer = $PAGE->get_renderer('core');
        $studentdata = [
            'user' => [
                'id' => 10,
                'fullname' => 'Alice Walker',
                'email' => 'alice@example.com',
            ],
            'courseid' => 2,
            'status' => 'atrisk',
            'status_label' => 'At Risk',
            'risk_score' => 75,
            'signals' => [
                ['message' => 'Completion rate is low', 'severity' => 'high'],
            ],
            'recommendations' => [
                ['title' => 'Direct Message', 'action' => 'CONTACT', 'description' => 'Send message', 'priority' => 'high'],
            ],
            'interventions' => [
                [
                    'id' => 5,
                    'type' => 'CONTACT',
                    'reason' => 'Behind schedule',
                    'actual_action' => 'Sent email',
                    'status' => 'OPEN',
                    'outcome' => 'UNKNOWN',
                ],
            ],
        ];

        $detail = new student_detail($studentdata);
        $exported = $detail->export_for_template($renderer);

        $this->assertEquals(2, $exported['courseid']);
        $this->assertTrue($exported['status_is_atrisk']);
        $this->assertFalse($exported['status_is_healthy']);
        $this->assertTrue($exported['has_signals']);
        $this->assertTrue($exported['has_recommendations']);
        $this->assertTrue($exported['has_interventions']);
        $this->assertTrue($exported['interventions'][0]['can_complete']);
        $this->assertTrue($exported['interventions'][0]['can_dismiss']);
    }

    /**
     * Test create_intervention throws exception when target student is not enrolled in course.
     */
    public function test_create_intervention_not_enrolled_exception(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $stranger = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\moodle_exception::class);
        dashboard_exporter::create_intervention($course->id, $stranger->id, 'CONTACT', 'Checking in');
    }
}
