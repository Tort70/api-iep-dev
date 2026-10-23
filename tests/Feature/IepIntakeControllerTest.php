<?php

namespace Tests\Feature;

use App\Models\IepIntake;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IepIntakeControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test index() returns only user's intakes.
     */
    public function test_index_returns_only_users_intakes()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // Create intakes for both users
        $userIntake1 = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-001',
            'status' => IepIntake::STATUS_DRAFT,
        ]);

        $userIntake2 = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-002',
            'status' => IepIntake::STATUS_DRAFT,
        ]);

        $otherIntake = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $otherUser->id,
            'student_identifier' => 'STUDENT-003',
            'status' => IepIntake::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/intakes');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertCount(2, $data);

        $ids = collect($data)->pluck('id')->toArray();
        $this->assertContains($userIntake1->id, $ids);
        $this->assertContains($userIntake2->id, $ids);
        $this->assertNotContains($otherIntake->id, $ids);
    }

    /**
     * Test getDraft() returns the most recent draft intake.
     */
    public function test_get_draft_returns_most_recent_draft()
    {
        $user = User::factory()->create();

        // Create multiple drafts (most recent will be returned)
        $olderDraft = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-001',
            'status' => IepIntake::STATUS_DRAFT,
            'raw_notes' => ['note' => 'older draft'],
        ]);

        sleep(1); // Ensure different timestamps

        $newerDraft = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-002',
            'status' => IepIntake::STATUS_DRAFT,
            'raw_notes' => ['note' => 'newer draft'],
        ]);

        $response = $this->withoutMiddleware()
            ->actingAs($user, 'sanctum')
            ->getJson('/api/intakes/draft');

        $response->assertStatus(200)
            ->assertJson([
                'id' => $newerDraft->id,
                'status' => IepIntake::STATUS_DRAFT,
            ]);
    }

    /**
     * Test getDraft() returns 404 when no draft exists.
     */
    public function test_get_draft_returns_404_when_no_draft_exists()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/intakes/draft');

        $response->assertStatus(404)
            ->assertJson(['message' => 'No draft found']);
    }

    /**
     * Test getDraft() does not return submitted intakes.
     */
    public function test_get_draft_does_not_return_submitted_intakes()
    {
        $user = User::factory()->create();

        // Create a submitted intake
        IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-001',
            'status' => IepIntake::STATUS_SUBMITTED,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/intakes/draft');

        $response->assertStatus(404);
    }

    /**
     * Test getDraft() does not return other users' drafts.
     */
    public function test_get_draft_does_not_return_other_users_drafts()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // Create draft for other user
        IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $otherUser->id,
            'student_identifier' => 'STUDENT-001',
            'status' => IepIntake::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/intakes/draft');

        $response->assertStatus(404);
    }

    /**
     * Test store() creates a draft intake.
     */
    public function test_store_creates_draft_intake()
    {
        $user = User::factory()->create();

        $payload = [
            'district_id' => 'DIST-001',
            'student_identifier' => 'STUDENT-001',
            'raw_notes' => ['observation' => 'Test note'],
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/intakes', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'status', 'district_id', 'student_identifier']);

        $this->assertDatabaseHas('iep_intakes', [
            'district_id' => 'DIST-001',
            'student_identifier' => 'STUDENT-001',
            'created_by_user_id' => $user->id,
            'status' => IepIntake::STATUS_DRAFT,
        ]);
    }

    /**
     * Test store() validates required fields.
     */
    public function test_store_validates_required_fields()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/intakes', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['district_id', 'student_identifier']);
    }

    /**
     * Test show() returns intake when user owns it.
     */
    public function test_show_returns_intake_when_user_owns_it()
    {
        $user = User::factory()->create();

        $intake = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-001',
            'status' => IepIntake::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/intakes/{$intake->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $intake->id,
                'student_identifier' => 'STUDENT-001',
            ]);
    }

    /**
     * Test show() returns 403 when user doesn't own intake.
     */
    public function test_show_returns_403_when_user_does_not_own_intake()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $intake = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $otherUser->id,
            'student_identifier' => 'STUDENT-001',
            'status' => IepIntake::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/intakes/{$intake->id}");

        $response->assertStatus(403)
            ->assertJson(['error' => 'Unauthorized']);
    }

    /**
     * Test submit() submits and locks draft intake.
     */
    public function test_submit_submits_and_locks_draft_intake()
    {
        $user = User::factory()->create();

        $intake = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-001',
            'raw_notes' => ['note' => 'content'],
            'status' => IepIntake::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/intakes/{$intake->id}/submit");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'IEP intake submitted',
                'id' => $intake->id,
                'status' => IepIntake::STATUS_SUBMITTED,
            ]);

        $intake->refresh();
        $this->assertEquals(IepIntake::STATUS_SUBMITTED, $intake->status);
        $this->assertNotNull($intake->locked_at);
        $this->assertNotNull($intake->submitted_at);
        $this->assertEquals($user->id, $intake->submitted_by_user_id);
    }

    /**
     * Test submit() returns 403 when user doesn't own intake.
     */
    public function test_submit_returns_403_when_user_does_not_own_intake()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $intake = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $otherUser->id,
            'student_identifier' => 'STUDENT-001',
            'status' => IepIntake::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/intakes/{$intake->id}/submit");

        $response->assertStatus(403)
            ->assertJson(['error' => 'Unauthorized']);
    }

    /**
     * Test submit() returns 403 when intake is already submitted.
     */
    public function test_submit_returns_403_when_intake_already_submitted()
    {
        $user = User::factory()->create();

        $intake = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-001',
            'status' => IepIntake::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'submitted_by_user_id' => $user->id,
            'locked_at' => now(),
            'lock_reason' => 'Submitted',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/intakes/{$intake->id}/submit");

        $response->assertStatus(403)
            ->assertJsonStructure(['error']);
    }

    /**
     * Test unauthenticated requests return 401.
     */
    public function test_unauthenticated_requests_return_401()
    {
        $intake = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => 1,
            'student_identifier' => 'STUDENT-001',
            'status' => IepIntake::STATUS_DRAFT,
        ]);

        $this->getJson('/api/intakes')->assertStatus(401);
        $this->postJson('/api/intakes', [])->assertStatus(401);
        $this->getJson("/api/intakes/{$intake->id}")->assertStatus(401);
        $this->patchJson("/api/intakes/{$intake->id}", [])->assertStatus(401);
        $this->deleteJson("/api/intakes/{$intake->id}")->assertStatus(401);
        $this->postJson("/api/intakes/{$intake->id}/submit")->assertStatus(401);
        $this->postJson("/api/intakes/{$intake->id}/seed", [])->assertStatus(401);
    }

    /**
     * Test seed() creates a new intake with copied notes.
     */
    public function test_seed_creates_new_intake_with_copied_notes()
    {
        $user = User::factory()->create();

        $sourceIntake = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-001',
            'raw_notes' => ['observation' => 'Original raw notes'],
            'refined_notes' => ['summary' => 'Original refined notes'],
            'status' => IepIntake::STATUS_DRAFT,
        ]);

        $payload = [
            'district_id' => 'DIST-002',
            'student_identifier' => 'STUDENT-002',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/intakes/{$sourceIntake->id}/seed", $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'status', 'district_id', 'student_identifier', 'raw_notes', 'refined_notes']);

        $newIntake = IepIntake::find($response->json('id'));

        // Verify new intake has different identifiers
        $this->assertEquals('DIST-002', $newIntake->district_id);
        $this->assertEquals('STUDENT-002', $newIntake->student_identifier);

        // Verify notes were copied
        $this->assertEquals(['observation' => 'Original raw notes'], $newIntake->raw_notes);
        $this->assertEquals(['summary' => 'Original refined notes'], $newIntake->refined_notes);

        // Verify new intake is a draft
        $this->assertEquals(IepIntake::STATUS_DRAFT, $newIntake->status);
        $this->assertEquals($user->id, $newIntake->created_by_user_id);

        // Verify source intake was not modified
        $sourceIntake->refresh();
        $this->assertEquals('DIST-001', $sourceIntake->district_id);
        $this->assertEquals('STUDENT-001', $sourceIntake->student_identifier);
    }

    /**
     * Test seed() validates required fields.
     */
    public function test_seed_validates_required_fields()
    {
        $user = User::factory()->create();

        $sourceIntake = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-001',
            'status' => IepIntake::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/intakes/{$sourceIntake->id}/seed", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['district_id', 'student_identifier']);
    }

    /**
     * Test seed() returns 403 when user doesn't own source intake.
     */
    public function test_seed_returns_403_when_user_does_not_own_source_intake()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $sourceIntake = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $otherUser->id,
            'student_identifier' => 'STUDENT-001',
            'raw_notes' => ['note' => 'content'],
            'status' => IepIntake::STATUS_DRAFT,
        ]);

        $payload = [
            'district_id' => 'DIST-002',
            'student_identifier' => 'STUDENT-002',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/intakes/{$sourceIntake->id}/seed", $payload);

        $response->assertStatus(403)
            ->assertJson(['error' => 'Unauthorized']);

        // Verify no new intake was created
        $this->assertCount(1, IepIntake::all());
    }

    /**
     * Test seed() works with null notes.
     */
    public function test_seed_works_with_null_notes()
    {
        $user = User::factory()->create();

        $sourceIntake = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-001',
            'raw_notes' => null,
            'refined_notes' => null,
            'status' => IepIntake::STATUS_DRAFT,
        ]);

        $payload = [
            'district_id' => 'DIST-002',
            'student_identifier' => 'STUDENT-002',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/intakes/{$sourceIntake->id}/seed", $payload);

        $response->assertStatus(201);

        $newIntake = IepIntake::find($response->json('id'));
        $this->assertNull($newIntake->raw_notes);
        $this->assertNull($newIntake->refined_notes);
    }

    /**
     * Test seed() can seed from submitted intake.
     */
    public function test_seed_can_seed_from_submitted_intake()
    {
        $user = User::factory()->create();

        $sourceIntake = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-001',
            'raw_notes' => ['observation' => 'Submitted notes'],
            'refined_notes' => ['summary' => 'Submitted refined'],
            'status' => IepIntake::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'submitted_by_user_id' => $user->id,
            'locked_at' => now(),
            'lock_reason' => 'Submitted',
        ]);

        $payload = [
            'district_id' => 'DIST-002',
            'student_identifier' => 'STUDENT-002',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/intakes/{$sourceIntake->id}/seed", $payload);

        $response->assertStatus(201);

        $newIntake = IepIntake::find($response->json('id'));
        $this->assertEquals(['observation' => 'Submitted notes'], $newIntake->raw_notes);
        $this->assertEquals(['summary' => 'Submitted refined'], $newIntake->refined_notes);
        $this->assertEquals(IepIntake::STATUS_DRAFT, $newIntake->status);
        $this->assertNull($newIntake->locked_at);
    }

    /**
     * Test store() sets auto_delete_at on draft creation.
     */
    public function test_store_sets_auto_delete_at_on_draft()
    {
        $user = User::factory()->create();

        $payload = [
            'district_id' => 'DIST-001',
            'student_identifier' => 'STUDENT-001',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/intakes', $payload);

        $response->assertStatus(201);

        $intake = IepIntake::find($response->json('id'));
        $this->assertNotNull($intake->auto_delete_at);

        // Verify it's set to approximately 90 days from now (default config)
        $expectedDeleteAt = now()->addDays(90);
        $this->assertTrue(
            $intake->auto_delete_at->diffInMinutes($expectedDeleteAt) < 1,
            'auto_delete_at should be set to 90 days from creation'
        );
    }

    /**
     * Test seed() sets auto_delete_at on seeded draft.
     */
    public function test_seed_sets_auto_delete_at_on_draft()
    {
        $user = User::factory()->create();

        // Create source intake with auto_delete_at set to 30 days from now
        $originalAutoDeleteAt = now()->addDays(30);
        $sourceIntake = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-001',
            'status' => IepIntake::STATUS_DRAFT,
            'auto_delete_at' => $originalAutoDeleteAt,
        ]);

        $payload = [
            'district_id' => 'DIST-002',
            'student_identifier' => 'STUDENT-002',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/intakes/{$sourceIntake->id}/seed", $payload);

        $response->assertStatus(201);

        $newIntake = IepIntake::find($response->json('id'));
        $this->assertNotNull($newIntake->auto_delete_at);

        // Verify new intake has its own auto_delete_at (90 days from now)
        $expectedNewDeleteAt = now()->addDays(90);
        $this->assertTrue(
            $newIntake->auto_delete_at->diffInMinutes($expectedNewDeleteAt) < 1,
            'New intake should have auto_delete_at set to 90 days from creation'
        );

        // Verify source intake auto_delete_at was not modified
        $sourceIntake->refresh();
        $this->assertEquals(
            $originalAutoDeleteAt->timestamp,
            $sourceIntake->auto_delete_at->timestamp,
            'Source intake auto_delete_at should not be modified'
        );
    }

    /**
     * Test submit() updates auto_delete_at on submission.
     */
    public function test_submit_updates_auto_delete_at()
    {
        $user = User::factory()->create();

        $intake = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-001',
            'status' => IepIntake::STATUS_DRAFT,
            'auto_delete_at' => now()->addDays(90),
        ]);

        $originalAutoDeleteAt = $intake->auto_delete_at;

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/intakes/{$intake->id}/submit");

        $response->assertStatus(200);

        $intake->refresh();

        // Verify auto_delete_at was updated to 180 days (submitted retention period)
        $this->assertNotNull($intake->auto_delete_at);
        $this->assertTrue(
            $intake->auto_delete_at->gt($originalAutoDeleteAt),
            'auto_delete_at should be extended on submission'
        );

        $expectedDeleteAt = now()->addDays(180);
        $this->assertTrue(
            $intake->auto_delete_at->diffInMinutes($expectedDeleteAt) < 1,
            'auto_delete_at should be set to 180 days from submission'
        );
    }

    /**
     * Test destroy() deletes a draft intake.
     */
    public function test_destroy_deletes_draft_intake()
    {
        $user = User::factory()->create();

        $intake = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-001',
            'status' => IepIntake::STATUS_DRAFT,
            'auto_delete_at' => now()->addDays(90),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/intakes/{$intake->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'IEP intake deleted successfully']);

        $this->assertDatabaseMissing('iep_intakes', [
            'id' => $intake->id,
        ]);
    }

    /**
     * Test destroy() returns 403 when user doesn't own intake.
     */
    public function test_destroy_returns_403_when_user_does_not_own_intake()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $intake = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $otherUser->id,
            'student_identifier' => 'STUDENT-001',
            'status' => IepIntake::STATUS_DRAFT,
            'auto_delete_at' => now()->addDays(90),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/intakes/{$intake->id}");

        $response->assertStatus(403)
            ->assertJson(['error' => 'Unauthorized']);

        // Verify intake was not deleted
        $this->assertDatabaseHas('iep_intakes', [
            'id' => $intake->id,
        ]);
    }

    /**
     * Test destroy() returns 403 when trying to delete submitted intake.
     */
    public function test_destroy_returns_403_when_deleting_submitted_intake()
    {
        $user = User::factory()->create();

        $intake = IepIntake::create([
            'district_id' => 'DIST-001',
            'created_by_user_id' => $user->id,
            'student_identifier' => 'STUDENT-001',
            'status' => IepIntake::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'submitted_by_user_id' => $user->id,
            'locked_at' => now(),
            'lock_reason' => 'Submitted',
            'auto_delete_at' => now()->addDays(180),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/intakes/{$intake->id}");

        $response->assertStatus(403)
            ->assertJson(['error' => 'Only draft intakes can be deleted']);

        // Verify intake was not deleted
        $this->assertDatabaseHas('iep_intakes', [
            'id' => $intake->id,
        ]);
    }
}
