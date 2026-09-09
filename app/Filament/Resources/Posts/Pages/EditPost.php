<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\On;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    public ?string $lastAutoSaved = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label(fn (): string => $this->record->post_status === 'publish' ? 'View Published' : 'Preview Draft')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color(fn (): string => $this->record->post_status === 'publish' ? 'gray' : 'warning')
                ->url(fn (): string => $this->record->post_status === 'publish' ? route('post', $this->record->slug) : URL::signedRoute('preview.post', ['slug' => $this->record->slug]), shouldOpenInNewTab: true),
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    #[On('auto-save')]
    public function autoSave(): void
    {
        try {
            if ($this->record->post_status !== 'draft') {
                return;
            }

            $state = $this->form->getState();

            $thumbnail = $state['thumbnail'] ?? $this->record->thumbnail;

            if (is_array($thumbnail)) {
                $thumbnail = $thumbnail[0] ?? null;
            }

            $this->record->skip_revision_pending = true;

            try {
                $this->record->update([
                    'post_title' => $state['post_title'] ?? null,
                    'slug' => $state['slug'] ?? null,
                    'post_content' => $state['post_content'] ?? null,
                    'thumbnail' => $thumbnail,
                ]);
            } finally {
                $this->record->skip_revision_pending = false;
            }

            $this->lastAutoSaved = now()->format('H:i:s');

            Notification::make()
                ->title('Draft saved')
                ->success()
                ->duration(1500)
                ->send();

            $this->dispatch('auto-saved', time: $this->lastAutoSaved);
        } catch (\Throwable $e) {
            // Auto-save must never clobber valid data with an incomplete draft,
            // so all failures (e.g. missing required fields) are silently
            // ignored in the UI, but still reported to the error log.
            report($e);
        }
    }
}
