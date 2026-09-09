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
use local_learningsuccess\local\service\student_summary;
use local_learningsuccess\local\service\student_success_service;
use local_learningsuccess\local\risk\risk_result;
use local_learningsuccess\external\student_exporter;

/**
 * Unit test suite for Phase 5 student_summary application DTO and external student exporter.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_summary_test extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test student_summary DTO construction and serialization.
     */
    public function test_student_summary_dto(): void {
        $risk = new risk_result(68.0, risk_result::LEVEL_ATRISK, 'signals');
        $dto = new student_summary(
            userid: 10,
            courseid: 20,
            user: ['id' => 10, 'fullname' => 'Alice Test', 'email' => 'alice@example.com'],
            risk: $risk,
            signals: [['type' => 'inactivity', 'value' => 8]],
            explanations: [['type' => 'inactivity', 'title' => 'Inactive']],
            recommendations: [['type' => 'checkin', 'action' => 'Message']],
            interventions: [],
            pendingfollowups: [],
            notes: []
        );

        $this->assertEquals(10, $dto->userid);
        $this->assertEquals(68, $dto->get_risk_score());
        $this->assertEquals(risk_result::LEVEL_ATRISK, $dto->get_status());

        $arr = $dto->to_array();
        $this->assertIsArray($arr);
        $this->assertEquals('Alice Test', $arr['user']['fullname']);
        $this->assertEquals('atrisk', $arr['status']);
        $this->assertEquals(68, $arr['risk_score']);
    }

    /**
     * Test student_success_service get_student_summary returns enriched array.
     */
    public function test_student_success_service_integration(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $service = new student_success_service();
        $summary = $service->get_student_summary($user->id, $course->id);

        $this->assertIsArray($summary);
        $this->assertEquals($user->id, $summary['userid']);
        $this->assertEquals($course->id, $summary['courseid']);
        $this->assertArrayHasKey('status', $summary);
        $this->assertArrayHasKey('risk_score', $summary);
        $this->assertArrayHasKey('recommendations', $summary);
    }

    /**
     * Test student_exporter external web service capability validation.
     */
    public function test_student_exporter_capabilities(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id);
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        $this->setUser($teacher);

        $result = student_exporter::get_student_detail($course->id, $student->id);
        $this->assertEquals($student->id, $result['userid']);
        $this->assertEquals($course->id, $result['courseid']);
        $this->assertArrayHasKey('fullname', $result);
        $this->assertArrayHasKey('signals', $result);
        $this->assertArrayHasKey('recommendations', $result);
    }
}
