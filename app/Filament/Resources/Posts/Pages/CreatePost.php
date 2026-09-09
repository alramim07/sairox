<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Facades\FilamentView;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    public ?string $lastAutoSaved = null;

    public ?int $autoSavedPostId = null;

    #[On('auto-save')]
    public function autoSave(): void
    {
        try {
            $data = $this->form->getState();

            if ($this->autoSavedPostId === null) {
                $post = Post::create([
                    'post_title' => $data['post_title'],
                    'slug' => $data['slug'],
                    'post_content' => $data['post_content'] ?? '',
                    'post_status' => 'draft',
                    'post_author' => $data['post_author'] ?? auth()->id(),
                ]);

                $this->autoSavedPostId = $post->id;
            } else {
                $post = Post::findOrFail($this->autoSavedPostId);

                $post->skip_revision_pending = true;

                try {
                    $post->update([
                        'post_title' => $data['post_title'],
                        'slug' => $data['slug'],
                        'post_content' => $data['post_content'] ?? '',
                    ]);
                } finally {
                    $post->skip_revision_pending = false;
                }
            }

            $this->lastAutoSaved = now()->format('H:i:s');

            Notification::make()
                ->title('Draft saved')
                ->success()
                ->duration(1500)
                ->send();

            $this->dispatch('auto-saved', time: $this->lastAutoSaved);
        } catch (ValidationException $e) {

        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function create(bool $another = false): void
    {
        if ($this->autoSavedPostId === null) {
            parent::create($another);

            return;
        }

        $data = $this->form->getState();

        $thumbnail = $data['thumbnail'] ?? null;

        if (is_array($thumbnail)) {
            $thumbnail = $thumbnail[0] ?? null;
        }

        $this->record = Post::findOrFail($this->autoSavedPostId);

        $this->record->skip_revision_pending = true;

        try {
            $this->record->update([
                'post_title' => $data['post_title'],
                'slug' => $data['slug'],
                'post_content' => $data['post_content'] ?? '',
                'post_excerpt' => $data['post_excerpt'] ?? null,
                'thumbnail' => $thumbnail,
                'post_status' => $data['post_status'] ?? 'publish',
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'post_author' => $data['post_author'] ?? auth()->id(),
            ]);
        } finally {
            $this->record->skip_revision_pending = false;
        }

        if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
            $this->record->meta()->delete();

            foreach ($data['custom_fields'] as $item) {
                $metaKey = $item['meta_key'] ?? null;

                if (blank($metaKey)) {
                    continue;
                }

                $this->record->meta()->create([
                    'meta_key' => $metaKey,
                    'meta_value' => $item['meta_value'] ?? null,
                ]);
            }
        }

        $this->autoSavedPostId = null;

        if ($another) {
            $this->form->model($this->record::class);

            $this->record = null;

            $this->fillForm();

            return;
        }

        $redirectUrl = $this->getResourceUrl('edit', ['record' => $this->record]);

        $this->redirect($redirectUrl, navigate: FilamentView::hasSpaMode($redirectUrl));
    }
}
