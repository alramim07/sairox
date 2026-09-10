<?php

namespace App\Models;

use App\Traits\HasRevisions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Post extends Model
{
    use HasRevisions, SoftDeletes;

    protected $attributes = [
        'post_format' => 'standard',
    ];

    protected $fillable = [
        'post_author',
        'post_content',
        'post_title',
        'post_excerpt',
        'post_status',
        'post_format',
        'comment_status',
        'slug',
        'post_type',
        'comment_count',
        'thumbnail',
        'template',
        'scheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }

    public const FORMATS = [
        'standard' => 'Standard',
        'aside' => 'Aside',
        'gallery' => 'Gallery',
        'link' => 'Link',
        'image' => 'Image',
        'quote' => 'Quote',
        'status' => 'Status',
        'video' => 'Video',
        'audio' => 'Audio',
        'chat' => 'Chat',
    ];

    protected static function booted(): void
    {
        static::saving(function ($post) {
            if ($post->scheduled_at && $post->scheduled_at->isFuture() && $post->post_status !== 'scheduled') {
                $post->post_status = 'scheduled';
            }

            if (! $post->scheduled_at && $post->post_status === 'scheduled') {
                $post->post_status = 'draft';
            }
        });

        static::saved(function ($post) {
            Cache::flush();
        });

        static::deleted(function ($post) {
            Cache::flush();
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'post_author');
    }

    public function categories(): MorphToMany
    {
        return $this->morphToMany(Term::class, 'termable', 'term_relationships')
            ->whereHas('taxonomy', fn ($q) => $q->where('taxonomy', 'category'));
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Term::class, 'termable', 'term_relationships')
            ->whereHas('taxonomy', fn ($q) => $q->where('taxonomy', 'post_tag'));
    }

    public function meta(): HasMany
    {
        return $this->hasMany(PostMeta::class);
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        static $metaCache = [];

        if (! isset($metaCache[$this->id])) {
            $metaCache[$this->id] = $this->meta->pluck('meta_value', 'meta_key')->toArray();
        }

        return $metaCache[$this->id][$key] ?? $default;
    }

    public function chatLines(): array
    {
        preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $this->post_content, $matches);

        return $matches[1] ?? [];
    }

    public function galleryImages(): array
    {
        preg_match_all('/<img[^>]*src=["\']([^"\']+)["\']/i', $this->post_content, $matches);

        return $matches[1] ?? [];
    }
}
