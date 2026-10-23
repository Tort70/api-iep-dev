<?php

namespace Tests\Feature;

use App\Models\IepIntake;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteExpiredIepIntakesCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test command deletes expired intakes.
     */
    public function test_command_deletes_expired_intakes()
    {
        $user = User::factory()->create();

        // Create an expired draft intake
        $expiredDraft = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-001',
            'status' => IepIntake::STATUS_DRAFT,
            'auto_delete_at' => now()->subDays(1),
        ]);

        // Create an expired submitted intake
        $expiredSubmitted = IepIntake::create([
            'district_id' => 'DIST-002',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-002',
            'status' => IepIntake::STATUS_SUBMITTED,
            'submitted_at' => now()->subDays(200),
            'submitted_by_user_id' => $user->id,
            'locked_at' => now()->subDays(200),
            'lock_reason' => 'Submitted',
            'auto_delete_at' => now()->subDays(1),
        ]);

        // Create a non-expired intake
        $validIntake = IepIntake::create([
            'district_id' => 'DIST-003',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-003',
            'status' => IepIntake::STATUS_DRAFT,
            'auto_delete_at' => now()->addDays(30),
        ]);

        $this->artisan('iep:delete-expired')
            ->expectsOutput('Found 2 expired IEP intake(s):')
            ->expectsOutput('Successfully deleted 2 expired IEP intake(s)')
            ->assertExitCode(0);

        // Verify expired intakes were deleted
        $this->assertDatabaseMissing('iep_intakes', ['id' => $expiredDraft->id]);
        $this->assertDatabaseMissing('iep_intakes', ['id' => $expiredSubmitted->id]);

        // Verify non-expired intake still exists
        $this->assertDatabaseHas('iep_intakes', ['id' => $validIntake->id]);
    }

    /**
     * Test command doesn't delete non-expired intakes.
     */
    public function test_command_does_not_delete_non_expired_intakes()
    {
        $user = User::factory()->create();

        $intake1 = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-001',
            'status' => IepIntake::STATUS_DRAFT,
            'auto_delete_at' => now()->addDays(30),
        ]);

        $intake2 = IepIntake::create([
            'district_id' => 'DIST-002',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-002',
            'status' => IepIntake::STATUS_DRAFT,
            'auto_delete_at' => now()->addDays(90),
        ]);

        $this->artisan('iep:delete-expired')
            ->expectsOutput('No expired IEP intakes found.')
            ->assertExitCode(0);

        // Verify both intakes still exist
        $this->assertDatabaseHas('iep_intakes', ['id' => $intake1->id]);
        $this->assertDatabaseHas('iep_intakes', ['id' => $intake2->id]);
    }

    /**
     * Test dry-run mode doesn't delete anything.
     */
    public function test_dry_run_does_not_delete_intakes()
    {
        $user = User::factory()->create();

        $expiredIntake = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-001',
            'status' => IepIntake::STATUS_DRAFT,
            'auto_delete_at' => now()->subDays(1),
        ]);

        $this->artisan('iep:delete-expired --dry-run')
            ->expectsOutput('Running in DRY RUN mode - no records will be deleted')
            ->expectsOutput('Found 1 expired IEP intake(s):')
            ->expectsOutput('DRY RUN: No records were deleted')
            ->assertExitCode(0);

        // Verify intake still exists after dry-run
        $this->assertDatabaseHas('iep_intakes', ['id' => $expiredIntake->id]);
    }

    /**
     * Test command handles no expired intakes gracefully.
     */
    public function test_command_handles_no_expired_intakes_gracefully()
    {
        $this->artisan('iep:delete-expired')
            ->expectsOutput('No expired IEP intakes found.')
            ->assertExitCode(0);
    }

    /**
     * Test command doesn't delete intakes with null auto_delete_at.
     */
    public function test_command_does_not_delete_intakes_with_null_auto_delete_at()
    {
        $user = User::factory()->create();

        // Create an intake without auto_delete_at (legacy data)
        $intakeWithoutAutoDelete = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-001',
            'status' => IepIntake::STATUS_DRAFT,
            'auto_delete_at' => null,
        ]);

        $this->artisan('iep:delete-expired')
            ->expectsOutput('No expired IEP intakes found.')
            ->assertExitCode(0);

        // Verify intake still exists
        $this->assertDatabaseHas('iep_intakes', ['id' => $intakeWithoutAutoDelete->id]);
    }

    /**
     * Test command deletes both draft and submitted expired intakes.
     */
    public function test_command_deletes_both_draft_and_submitted_intakes()
    {
        $user = User::factory()->create();

        // Create expired draft
        $expiredDraft = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-001',
            'status' => IepIntake::STATUS_DRAFT,
            'auto_delete_at' => now()->subDays(1),
        ]);

        // Create expired submitted intake
        $expiredSubmitted = IepIntake::create([
            'district_id' => 'DIST-002',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-002',
            'status' => IepIntake::STATUS_SUBMITTED,
            'submitted_at' => now()->subDays(200),
            'submitted_by_user_id' => $user->id,
            'locked_at' => now()->subDays(200),
            'lock_reason' => 'Submitted',
            'auto_delete_at' => now()->subDays(1),
        ]);

        $this->artisan('iep:delete-expired')
            ->assertExitCode(0);

        // Verify both were deleted
        $this->assertDatabaseMissing('iep_intakes', ['id' => $expiredDraft->id]);
        $this->assertDatabaseMissing('iep_intakes', ['id' => $expiredSubmitted->id]);
    }
}
