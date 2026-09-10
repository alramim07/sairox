<?php

namespace App\Traits;

use App\Models\Revision;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasRevisions
{
    public bool $skip_revision_pending = false;

    public static function bootHasRevisions(): void
    {
        static::saved(function ($model) {
            if ($model->skip_revision_pending) {
                return;
            }

            $changes = $model->getChanges();

            if (empty($changes)) {
                if (! $model->wasRecentlyCreated) {
                    return;
                }

                $model->wasRecentlyCreated = false;
            } else {
                $meaningful = array_diff_key($changes, array_flip(['updated_at', 'created_at']));

                if (empty($meaningful)) {
                    return;
                }
            }

            $model->revisions()->create([
                'user_id' => auth()->id(),
                'data' => $model->fresh()->toArray(),
            ]);

            while ($model->revisions()->count() > 50) {
                $model->revisions()->orderBy('id')->limit(1)->delete();
            }
        });
    }

    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisionable');
    }

    public function restoreRevision(Revision $revision): void
    {
        $this->skip_revision_pending = true;

        $this->update($revision->data);

        $this->revisions()->create([
            'user_id' => auth()->id(),
            'data' => $this->fresh()->toArray(),
            'note' => 'Restored revision #'.$revision->id,
        ]);

        $this->skip_revision_pending = false;
    }

    public function diffRevision(?Revision $from, Revision $to): array
    {
        $fromData = $from ? $from->data : [];
        $toData = $to->data;

        $diffs = [];
        $fields = ['post_title', 'post_content', 'post_excerpt', 'post_status', 'post_format'];

        foreach ($fields as $field) {
            $old = $fromData[$field] ?? '';
            $new = $toData[$field] ?? '';

            if ($old !== $new) {
                $diffs[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return $diffs;
    }
}
