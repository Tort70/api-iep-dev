<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class IepIntake extends Model
{
    use HasFactory;

    protected $table = 'iep_intakes';

    /*
    |--------------------------------------------------------------------------
    | Status constants
    |--------------------------------------------------------------------------
    */

    /**
     * Possible status values for an IEP intake.
     */
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SUBMITTED = 'submitted';

    /*
    |--------------------------------------------------------------------------
    | Mass assignment
    |--------------------------------------------------------------------------
    */

    /**
     * Fields that may be mass-assigned.
     *
     * Intentionally limited to protect legal boundaries.
     */
    protected $fillable = [
        'district_id',
        'created_by_user_id',
        'student_identifier',

        'raw_notes',
        'refined_notes',
        'final_summary',

        'status',
        'version',

        'submitted_at',
        'submitted_by_user_id',

        'locked_at',
        'lock_reason',
    ];

    /*
    |--------------------------------------------------------------------------
    | Attribute casting
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'raw_notes'     => 'array',
        'refined_notes' => 'array',

        'submitted_at'  => 'datetime',
        'locked_at'     => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Model boot protections
    |--------------------------------------------------------------------------
    */

    /**
     * Prevent modification of locked fields once an intake is submitted.
     */
    protected static function booted()
    {
        static::updating(function (self $intake) {
            if ($intake->isLocked()) {
                $immutableFields = [
                    'locked_at',
                    'lock_reason',
                    'submitted_at',
                    'submitted_by_user_id',
                    'status',
                ];

                foreach ($immutableFields as $field) {
                    if ($intake->isDirty($field)) {
                        throw new RuntimeException(
                            "Field '{$field}' cannot be modified after intake submission."
                        );
                    }
                }
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | State helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Determine if the intake has crossed the legal submission boundary.
     */
    public function isLocked(): bool
    {
        return ! is_null($this->locked_at);
    }

    /**
     * Determine if the intake is still a draft.
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT && ! $this->isLocked();
    }

    /*
    |--------------------------------------------------------------------------
    | State transitions
    |--------------------------------------------------------------------------
    */

    /**
     * Submit the IEP intake.
     *
     * This represents a legal / procedural boundary:
     * - intake becomes immutable
     * - submission metadata is recorded
     *
     * @param int $userId  ID of the user submitting the intake
     * @param string $reason  Reason for locking the intake
     *
     * @throws RuntimeException if the intake is already locked or not a draft
     */
    public function submit(int $userId, string $reason = 'Submitted'): void
    {
        if ($this->isLocked()) {
            throw new RuntimeException('IEP intake is already locked.');
        }

        if ($this->status !== self::STATUS_DRAFT) {
            throw new RuntimeException('Only draft intakes can be submitted.');
        }

        $this->status = self::STATUS_SUBMITTED;
        $this->submitted_at = now();
        $this->submitted_by_user_id = $userId;

        $this->locked_at = now();
        $this->lock_reason = $reason;

        $this->save();

        // Future hook point:
        // event(new IepIntakeSubmitted($this));
    }

    /*
    |--------------------------------------------------------------------------
    | Revision workflow
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new draft revision based on a submitted intake.
     *
     * This preserves the submitted intake as a historical snapshot
     * while allowing continued professional thinking.
     *
     * @param int $userId ID of the user creating the revision
     *
     * @return self Newly created draft intake
     *
     * @throws RuntimeException if the intake is not yet submitted
     */
    public function createRevision(int $userId): self
    {
        if (! $this->isLocked()) {
            throw new RuntimeException('Only submitted intakes can be revised.');
        }

        return self::create([
            'district_id'        => $this->district_id,
            'created_by_user_id' => $userId,
            'student_identifier' => $this->student_identifier,

            'raw_notes'          => $this->raw_notes,
            'refined_notes'      => null,
            'final_summary'      => null,

            'status'             => self::STATUS_DRAFT,
            'version'            => $this->version + 1,
        ]);
    }
}
