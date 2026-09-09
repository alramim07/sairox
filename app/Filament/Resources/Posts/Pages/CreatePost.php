<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
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
                $post = new Post([
                    'post_title' => $data['post_title'],
                    'slug' => $data['slug'],
                    'post_content' => $data['post_content'] ?? '',
                    'post_status' => 'draft',
                    'post_author' => $data['post_author'] ?? auth()->id(),
                ]);

                $post->skip_revision_pending = true;

                try {
                    $post->save();
                } finally {
                    $post->skip_revision_pending = false;
                }

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

        Post::query()->find($this->autoSavedPostId)?->forceDelete();

        $this->autoSavedPostId = null;

        parent::create($another);
    }
}
