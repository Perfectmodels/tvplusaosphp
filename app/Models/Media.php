<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Media extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'type',
        'mime_type',
        'file_size',
        'file_path',
        'file_url',
        'thumbnail_url',
        'duration_seconds',
        'resolution',
        'tags',
        'category',
        'copyright',
        'author',
        'source',
        'embedding_code',
        'view_count',
        'download_count',
        'is_archived',
        'published_at',
        'metadata',
    ];

    protected $casts = [
        'file_size' => 'bigint',
        'duration_seconds' => 'integer',
        'view_count' => 'integer',
        'download_count' => 'integer',
        'is_archived' => 'boolean',
        'tags' => 'array',
        'published_at' => 'datetime',
        'metadata' => 'array',
        'type' => 'enum:image,video,audio,document,presentation,archive,autre',
    ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function emissions(): MorphToMany
    {
        return $this->morphedByMany(Emission::class, 'mediaable');
    }

    public function sequences(): MorphToMany
    {
        return $this->morphedByMany(Sequence::class, 'mediaable');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('original')
            ->singleFile()
            // 🔒 Type + taille contrôlés, SVG refusé.
            ->acceptsFile([\App\Services\MediaValidation::class, 'document']);

        $this->addMediaCollection('thumbnails')
            ->acceptsFile([\App\Services\MediaValidation::class, 'imagePortrait']);
    }
}
