<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IepIntake;
use Illuminate\Http\Request;
use RuntimeException;

class IepIntakeController extends Controller
{
    /**
     * List all IEP intakes visible to the authenticated user.
     */
    public function index(Request $request)
    {
        $intakes = IepIntake::where('created_by_user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($intakes);
    }

    /**
     * Get the authenticated user's existing draft intake.
     * Returns 404 if no draft exists.
     */
    public function getDraft(Request $request)
    {
        $draft = IepIntake::where('created_by_user_id', $request->user()->id)
            ->where('status', IepIntake::STATUS_DRAFT)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$draft) {
            return response()->json(['message' => 'No draft found'], 404);
        }

        return response()->json($draft);
    }

    /**
     * Store a new IEP intake as a draft.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'district_id' => 'required|string',
            'student_identifier' => 'required|string',
            'raw_notes' => 'nullable|array',
        ]);

        $draftRetentionDays = config('iep.retention.draft', 90);

        $intake = IepIntake::create([
            'district_id' => $validated['district_id'],
            'created_by_user_id' => $request->user()->id,
            'student_identifier' => $validated['student_identifier'],
            'raw_notes' => $validated['raw_notes'] ?? null,
            'status' => IepIntake::STATUS_DRAFT,
            'auto_delete_at' => now()->addDays($draftRetentionDays),
        ]);

        return response()->json($intake, 201);
    }

    /**
     * Show a single IEP intake.
     */
    public function show(Request $request, IepIntake $intake)
    {
        // Check ownership
        if ($intake->created_by_user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }

        return response()->json($intake);
    }

    /**
     * Update raw_notes on a draft IEP intake (autosave).
     */
    public function update(Request $request, IepIntake $intake)
    {
        // Check ownership
        if ($intake->created_by_user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }

        $validated = $request->validate([
            'raw_notes' => 'nullable|array',
        ]);

        if (! $intake->isDraft()) {
            return response()->json([
                'error' => 'Only draft intakes may be updated',
            ], 403);
        }

        $intake->update($validated);

        return response()->json([
            'id'     => $intake->id,
            'status' => $intake->status,
        ]);
    }

    /**
     * Submit an IEP intake (state transition: draft → submitted).
     */
    public function submit(Request $request, IepIntake $intake)
    {
        // Check ownership
        if ($intake->created_by_user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }

        try {
            $intake->submit($request->user()->id);

            return response()->json([
                'message' => 'IEP intake submitted',
                'id' => $intake->id,
                'status' => $intake->status,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 403);
        }
    }

    /**
     * Seed a new IEP intake from an existing intake.
     *
     * Copies raw_notes and refined_notes from the source intake
     * to create a new draft intake. This requires explicit teacher action
     * and does not extend retention of the source intake.
     */
    public function seed(Request $request, IepIntake $sourceIntake)
    {
        // Check ownership of source intake
        if ($sourceIntake->created_by_user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }

        $validated = $request->validate([
            'district_id' => 'required|string',
            'student_identifier' => 'required|string',
        ]);

        $draftRetentionDays = config('iep.retention.draft', 90);

        $newIntake = IepIntake::create([
            'district_id' => $validated['district_id'],
            'created_by_user_id' => $request->user()->id,
            'student_identifier' => $validated['student_identifier'],
            'raw_notes' => $sourceIntake->raw_notes,
            'refined_notes' => $sourceIntake->refined_notes,
            'status' => IepIntake::STATUS_DRAFT,
            'auto_delete_at' => now()->addDays($draftRetentionDays),
        ]);

        return response()->json($newIntake, 201);
    }

    /**
     * Delete a draft IEP intake.
     *
     * Teachers can delete their own draft intakes at any time.
     * Submitted (locked) intakes cannot be manually deleted.
     */
    public function destroy(Request $request, IepIntake $intake)
    {
        // Check ownership
        if ($intake->created_by_user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }

        // Only drafts can be deleted
        if (! $intake->isDraft()) {
            return response()->json([
                'error' => 'Only draft intakes can be deleted'
            ], 403);
        }

        $intake->delete();

        return response()->json([
            'message' => 'IEP intake deleted successfully'
        ], 200);
    }
}
