<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iep_intakes', function (Blueprint $table) {
            $table->id();

            // --- Multi-tenant / ownership ---
            $table->string('district_id')->index();
            $table->unsignedBigInteger('created_by_user_id')->index();

            // --- Student reference (not FK yet) ---
            $table->string('student_identifier')->index();
            // e.g. PEN, local SIS ID, or district-defined identifier

            // --- Intake content layers ---
            $table->longText('raw_notes')->nullable();
            $table->longText('refined_notes')->nullable();
            $table->longText('final_summary')->nullable();

            // --- Workflow state ---
            $table->enum('status', [
                'draft',
                'reviewed',
                'submitted',
                'archived'
            ])->default('draft')->index();

            // --- Submission / locking ---
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('submitted_by_user_id')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_reason')->nullable();

            // --- Versioning ---
            $table->unsignedInteger('version')->default(1);

            // --- Timestamps ---
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iep_intakes');
    }
};
