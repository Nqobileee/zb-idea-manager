<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class IdeaFile extends Model
{
    protected $guarded = [];

    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }

    public function getUrlAttribute(): ?string
    {
        if (! $this->path) {
            return null;
        }

        return Storage::disk(config('ideas.upload_disk'))->url($this->path);
    }

    public function getExtAttribute(): string
    {
        $e = strtolower(pathinfo($this->name, PATHINFO_EXTENSION));

        return in_array($e, ['pdf', 'xlsx', 'pptx', 'docx'], true) ? $e : 'other';
    }
}
