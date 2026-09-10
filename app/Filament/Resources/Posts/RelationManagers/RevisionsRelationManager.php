<?php

namespace App\Filament\Resources\Posts\RelationManagers;

use App\Models\Revision;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Author')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Saved')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('note')
                    ->label('Note')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('view_diff')
                    ->label('View Changes')
                    ->icon('heroicon-o-document-text')
                    ->modal()
                    ->modalHeading(fn (Revision $record) => "Revision #{$record->id}")
                    ->modalContent(function (Revision $record): HtmlString {
                        $prev = Revision::where('revisionable_id', $record->revisionable_id)
                            ->where('revisionable_type', $record->revisionable_type)
                            ->where('id', '<', $record->id)
                            ->orderBy('id', 'desc')
                            ->first();

                        $modelClass = $record->revisionable_type;
                        $diffs = $modelClass::find($record->revisionable_id)?->diffRevision($prev, $record) ?? [];

                        if (empty($diffs)) {
                            return new HtmlString('<p class="text-gray-500">No changes in this revision.</p>');
                        }

                        $html = '<div class="space-y-4">';
                        foreach ($diffs as $field => $diff) {
                            $label = match ($field) {
                                'post_title' => 'Title',
                                'post_content' => 'Content',
                                'post_excerpt' => 'Excerpt',
                                'post_status' => 'Status',
                                'post_format' => 'Format',
                                default => $field,
                            };
                            $html .= '<div class="border rounded-lg p-4">';
                            $html .= "<h4 class=\"font-semibold mb-2\">{$label}</h4>";
                            $html .= '<div class="grid grid-cols-2 gap-4">';
                            $html .= '<div class="bg-red-50 p-3 rounded"><p class="text-xs text-red-600 font-medium mb-1">Before</p><div class="text-sm text-red-800">'.e($diff['old']).'</div></div>';
                            $html .= '<div class="bg-green-50 p-3 rounded"><p class="text-xs text-green-600 font-medium mb-1">After</p><div class="text-sm text-green-800">'.e($diff['new']).'</div></div>';
                            $html .= '</div></div>';
                        }
                        $html .= '</div>';

                        return new HtmlString($html);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Revision $record) {
                        $this->getOwnerRecord()->restoreRevision($record);
                        $this->dispatch('$refresh');
                    }),
            ])
            ->toolbarActions([]);
    }

    protected function canCreate(): bool
    {
        return false;
    }

    protected function canEdit(Model $record): bool
    {
        return false;
    }

    protected function canDelete(Model $record): bool
    {
        return false;
    }

    protected function canDeleteAny(): bool
    {
        return false;
    }
}
