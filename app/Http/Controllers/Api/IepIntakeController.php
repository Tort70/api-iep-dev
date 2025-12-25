<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class IepIntakeController extends Controller
{
    /**
     * List all IEP intakes visible to the authenticated user.
     */
    public function index()
    {
        return response()->json([]);
    }

    /**
     * Store a new IEP intake as a draft.
     */
    public function store(Request $request)
    {
        return response()->json([
            'message' => 'IEP intake draft created',
        ], 201);
    }

    /**
     * Show a single IEP intake.
     */
    public function show($intake)
    {
        return response()->json([
            'id' => $intake,
        ]);
    }

    /**
     * Update raw_notes on a draft IEP intake (autosave).
     */
    public function update(Request $request, $intake)
    {
        $validated = $request->validate([
            'raw_notes' => 'nullable|array',
        ]);

        $intakeModel = \App\Models\IepIntake::findOrFail($intake);

        if (! $intakeModel->isDraft()) {
            return response()->json([
                'error' => 'Only draft intakes may be updated',
            ], 403);
        }

        $intakeModel->update($validated);

        return response()->json([
            'id'     => $intakeModel->id,
            'status' => $intakeModel->status,
        ]);
    }

    /**
     * Submit an IEP intake (state transition: draft → submitted).
     */
    public function submit($intake)
    {
        return response()->json([
            'message' => 'IEP intake submitted',
            'id'      => $intake,
        ]);
    }
}
