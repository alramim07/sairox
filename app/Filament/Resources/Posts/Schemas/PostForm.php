<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Models\Post;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Main Content')
                    ->schema([
                        TextInput::make('post_title')->required(),
                        TextInput::make('slug')->required(),
                        RichEditor::make('post_content')->columnSpanFull(),
                    ])->columns(2),

                Section::make('Media & Settings')
                    ->schema([
                        FileUpload::make('thumbnail')
                            ->label('Featured Image')
                            ->image()
                            ->disk('public')
                            ->directory('thumbnails')
                            ->visibility('public')
                            ->preserveFilenames() // Optional: keeps original name
                            ->imageEditor()
                            ->columnSpanFull(),

                        Select::make('post_status')
                            ->options(['publish' => 'Publish', 'draft' => 'Draft', 'scheduled' => 'Scheduled'])
                            ->default('draft')
                            ->helperText(fn ($get) => $get('scheduled_at') ? 'Auto-set to Scheduled when future date is set' : ''),

                        Select::make('post_format')
                            ->label('Post Format')
                            ->options(Post::FORMATS)
                            ->default('standard')
                            ->helperText('Controls how the theme renders this post'),

                        DateTimePicker::make('scheduled_at')
                            ->label('Schedule Publication')
                            ->native(false)
                            ->seconds(false)
                            ->minDate(now()->addMinute())
                            ->helperText('Set a future date to auto-publish this post'),

                        Select::make('post_author')
                            ->label('Author')
                            ->relationship('author', 'name')
                            ->default(auth()->id())
                            ->required(),
                    ])->columns(2),

                Section::make('Custom Fields')
                    ->schema([
                        Repeater::make('custom_fields')
                            ->relationship('meta')
                            ->schema([
                                TextInput::make('meta_key')
                                    ->label('Key')
                                    ->required()
                                    ->maxLength(255),
                                Textarea::make('meta_value')
                                    ->label('Value'),
                            ])
                            ->defaultItems(0)
                            ->collapsible(),
                    ]),
            ])
            ->columns(1);
    }
}
