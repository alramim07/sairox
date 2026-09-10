<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Models\Post;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // 1. Show the Title
                TextColumn::make('post_title')
                    ->label('Title')
                    ->searchable()
                    ->sortable(),

                // 2. Show the Author (using the relationship name 'author')
                TextColumn::make('author.name')
                    ->label('Author')
                    ->sortable(),

                // 3. Show the Status
                TextColumn::make('post_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'publish' => 'success',
                        'scheduled' => 'info',
                        'draft' => 'warning',
                        'private' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('scheduled_at')
                    ->label('Scheduled')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),

                // 4. Show the Post Type
                TextColumn::make('post_type')
                    ->label('Type')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('post_format')
                    ->label('Format')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Post::FORMATS[$state] ?? ucfirst($state)),

                // 5. Show the Date
                TextColumn::make('created_at')
                    ->label('Published At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('post_status')
                    ->label('Status')
                    ->options([
                        'publish' => 'Published',
                        'scheduled' => 'Scheduled',
                        'draft' => 'Draft',
                        'private' => 'Private',
                    ]),
                SelectFilter::make('post_format')
                    ->label('Format')
                    ->options(Post::FORMATS),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
