<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    public const PENDING = 'pending';
    public const IN_PROGRESS = 'in-progress';
    public const DONE = 'done';

    public const STATUSES = [self::PENDING, self::IN_PROGRESS, self::DONE];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'position',
    ];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'position' => 'integer',
    ];

    // Same defaults as the database columns, so a task that was just created
    // already has them when it is returned.
    protected $attributes = [
        'status'   => self::PENDING,
        'priority' => 'normal',
        'position' => 0,
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Give the tasks of one column the positions 0, 1, 2... again,
     * in their current order. Used after a task is moved or deleted.
     */
    public static function reindexColumn(int $projectId, string $status): void
    {
        $ids = static::where('project_id', $projectId)
            ->where('status', $status)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $position => $id) {
            static::whereKey($id)->update(['position' => $position]);
        }
    }
}
