<?php

namespace Tests\Feature;

use App\Models\IepIntake;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IepIntakeUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that an authenticated user can update raw_notes on a draft intake.
     *
     * @return void
     */
    public function test_authenticated_user_can_update_raw_notes_on_draft_intake()
    {
        $user = User::factory()->create();

        $intake = IepIntake::create([
            'district_id' => '1',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-001',
            'raw_notes' => ['initial' => 'note'],
            'status' => IepIntake::STATUS_DRAFT,
        ]);

        $newNotes = [
            'observation' => 'Student shows progress in reading',
            'timestamp' => '2025-12-24 10:00:00',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/intakes/{$intake->id}", [
                'raw_notes' => $newNotes,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $intake->id,
                'status' => IepIntake::STATUS_DRAFT,
            ]);

        $this->assertDatabaseHas('iep_intakes', [
            'id' => $intake->id,
            'raw_notes' => json_encode($newNotes),
        ]);
    }

    /**
     * Test that an unauthenticated user cannot update an intake.
     *
     * @return void
     */
    public function test_unauthenticated_user_cannot_update_intake()
    {
        $user = User::factory()->create();

        $intake = IepIntake::create([
            'district_id' => '1',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-002',
            'raw_notes' => ['initial' => 'note'],
            'status' => IepIntake::STATUS_DRAFT,
        ]);

        $response = $this->patchJson("/api/intakes/{$intake->id}", [
            'raw_notes' => ['new' => 'note'],
        ]);

        $response->assertStatus(401);

        // Verify data was not changed
        $this->assertDatabaseHas('iep_intakes', [
            'id' => $intake->id,
            'raw_notes' => json_encode(['initial' => 'note']),
        ]);
    }

    /**
     * Test that a submitted intake cannot be modified.
     *
     * @return void
     */
    public function test_submitted_intake_cannot_be_modified()
    {
        $user = User::factory()->create();

        // Create an already-submitted intake
        $intake = IepIntake::create([
            'district_id' => '1',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-003',
            'raw_notes' => ['original' => 'note'],
            'status' => IepIntake::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'submitted_by_user_id' => $user->id,
            'locked_at' => now(),
            'lock_reason' => 'Test submission',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/intakes/{$intake->id}", [
                'raw_notes' => ['attempted' => 'change'],
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Only draft intakes may be updated',
            ]);

        // Verify data was not changed
        $intake->refresh();
        $this->assertEquals(['original' => 'note'], $intake->raw_notes);
        $this->assertEquals(IepIntake::STATUS_SUBMITTED, $intake->status);
    }

    /**
     * Test that a locked intake cannot be modified.
     *
     * @return void
     */
    public function test_locked_intake_cannot_be_modified()
    {
        $user = User::factory()->create();

        $intake = IepIntake::create([
            'district_id' => '1',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-004',
            'raw_notes' => ['original' => 'note'],
            'status' => IepIntake::STATUS_DRAFT,
            'locked_at' => now(),
            'lock_reason' => 'Administrative lock',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/intakes/{$intake->id}", [
                'raw_notes' => ['attempted' => 'change'],
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Only draft intakes may be updated',
            ]);

        // Verify data was not changed
        $intake->refresh();
        $this->assertEquals(['original' => 'note'], $intake->raw_notes);
    }

    /**
     * Test that only raw_notes can be updated via the autosave endpoint.
     *
     * @return void
     */
    public function test_only_raw_notes_can_be_updated()
    {
        $user = User::factory()->create();

        $originalStudentId = 'STUDENT-005';
        $originalRefined = ['refined' => 'content'];

        $intake = IepIntake::create([
            'district_id' => '1',
            'created_by_user_id' => $user->id,
            'student_identifier' => $originalStudentId,
            'raw_notes' => ['initial' => 'note'],
            'refined_notes' => $originalRefined,
            'status' => IepIntake::STATUS_DRAFT,
        ]);

        $newNotes = ['updated' => 'autosave data'];

        // Update only raw_notes
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/intakes/{$intake->id}", [
                'raw_notes' => $newNotes,
            ]);

        $response->assertStatus(200);

        $intake->refresh();

        // Verify only raw_notes was updated
        $this->assertEquals($newNotes, $intake->raw_notes);

        // Verify other fields remain unchanged
        $this->assertEquals($originalRefined, $intake->refined_notes);
        $this->assertEquals(IepIntake::STATUS_DRAFT, $intake->status);
        $this->assertEquals($originalStudentId, $intake->student_identifier);
        $this->assertEquals($user->id, $intake->created_by_user_id);
        $this->assertEquals('1', $intake->district_id);
    }

    /**
     * Test that raw_notes can be set to null.
     *
     * @return void
     */
    public function test_raw_notes_can_be_set_to_null()
    {
        $user = User::factory()->create();

        $intake = IepIntake::create([
            'district_id' => '1',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-006',
            'raw_notes' => ['existing' => 'data'],
            'status' => IepIntake::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/intakes/{$intake->id}", [
                'raw_notes' => null,
            ]);

        $response->assertStatus(200);

        $intake->refresh();
        $this->assertNull($intake->raw_notes);
    }

    /**
     * Test that raw_notes can be updated with an empty array.
     *
     * @return void
     */
    public function test_raw_notes_can_be_updated_with_empty_array()
    {
        $user = User::factory()->create();

        $intake = IepIntake::create([
            'district_id' => '1',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-007',
            'raw_notes' => ['existing' => 'data'],
            'status' => IepIntake::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/intakes/{$intake->id}", [
                'raw_notes' => [],
            ]);

        $response->assertStatus(200);

        $intake->refresh();
        $this->assertEquals([], $intake->raw_notes);
    }

    /**
     * Test that the endpoint returns 404 for non-existent intake.
     *
     * @return void
     */
    public function test_returns_404_for_non_existent_intake()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/intakes/99999', [
                'raw_notes' => ['test' => 'data'],
            ]);

        $response->assertStatus(404);
    }

    /**
     * Test that raw_notes validation rejects invalid data types.
     *
     * @return void
     */
    public function test_raw_notes_validation_rejects_invalid_data_types()
    {
        $user = User::factory()->create();

        $intake = IepIntake::create([
            'district_id' => '1',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-008',
            'raw_notes' => ['initial' => 'note'],
            'status' => IepIntake::STATUS_DRAFT,
        ]);

        // Test with string instead of array
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/intakes/{$intake->id}", [
                'raw_notes' => 'this is a string not an array',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['raw_notes']);

        // Verify data was not changed
        $intake->refresh();
        $this->assertEquals(['initial' => 'note'], $intake->raw_notes);
    }
}
