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
use local_learningsuccess\local\outcome\outcome;
use local_learningsuccess\local\outcome\outcome_evaluator;
use local_learningsuccess\local\outcome\snapshot_service;

/**
 * Unit test suite for Phase 4 Snapshots and Outcome Evaluator.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class outcome_evaluator_test extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test snapshot_service capture structure.
     */
    public function test_snapshot_service_capture(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $service = new snapshot_service();
        $snapshot = $service->capture($user->id, $course->id);

        $this->assertIsArray($snapshot);
        $this->assertArrayHasKey('risk_score', $snapshot);
        $this->assertArrayHasKey('risk_level', $snapshot);
        $this->assertArrayHasKey('completion', $snapshot);
        $this->assertArrayHasKey('grade', $snapshot);
        $this->assertArrayHasKey('inactive_days', $snapshot);
    }

    /**
     * Test outcome_evaluator detecting improvement, decline, and no change.
     */
    public function test_outcome_evaluator_comparisons(): void {
        $evaluator = new outcome_evaluator();

        // 1. Improvement scenario: risk decreased from 70 to 40.
        $before = ['risk_score' => 70, 'grade' => 45, 'completion' => 30];
        $after = ['risk_score' => 40, 'grade' => 55, 'completion' => 50];
        $res = $evaluator->evaluate($before, $after);

        $this->assertEquals(outcome::IMPROVED, $res->status);
        $this->assertEquals(30.0, $res->riskdelta);
        $this->assertEquals(10.0, $res->gradedelta);
        $this->assertEquals(20.0, $res->completiondelta);

        // 2. Decline scenario: risk increased by 20.
        $afterDecline = ['risk_score' => 90, 'grade' => 25, 'completion' => 30];
        $resDecline = $evaluator->evaluate($before, $afterDecline);
        $this->assertEquals(outcome::DECLINED, $resDecline->status);

        // 3. No change scenario.
        $afterNoChange = ['risk_score' => 68, 'grade' => 46, 'completion' => 32];
        $resNoChange = $evaluator->evaluate($before, $afterNoChange);
        $this->assertEquals(outcome::NO_CHANGE, $resNoChange->status);
    }

    /**
     * Test course effectiveness statistics aggregation.
     */
    public function test_course_effectiveness_aggregation(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();

        // Insert mock interventions with varying outcomes.
        $outcomes = [outcome::IMPROVED, outcome::IMPROVED, outcome::NO_CHANGE, outcome::DECLINED, outcome::UNKNOWN];
        foreach ($outcomes as $out) {
            $DB->insert_record('local_ls_intervention', (object) [
                'courseid' => $course->id,
                'userid' => $student->id,
                'teacherid' => $teacher->id,
                'type' => 'checkin',
                'status' => 'completed',
                'outcome' => $out,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }

        $evaluator = new outcome_evaluator();
        $stats = $evaluator->get_course_effectiveness($course->id);

        $this->assertEquals(5, $stats['total_interventions']);
        $this->assertEquals(2, $stats['improved_count']);
        $this->assertEquals(1, $stats['no_change_count']);
        $this->assertEquals(1, $stats['declined_count']);
        $this->assertEquals(1, $stats['unknown_count']);
        $this->assertEquals(4, $stats['resolved_count']);
        $this->assertEquals(50, $stats['success_rate']); // 2 / 4 = 50%
    }
}
