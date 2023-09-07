<?php

namespace Rmsramos\Activitylog\Resources;

use Filament\Forms\Components\Group;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Rmsramos\Activitylog\ActivitylogPlugin;
use Rmsramos\Activitylog\Resources\ActivitylogResource\Pages\ListActivitylog;
use Spatie\Activitylog\Models\Activity;

class ActivitylogResource extends Resource
{
    public static function getModel(): string
    {
        return Activity::class;
    }

    public static function getModelLabel(): string
    {
        return ActivitylogPlugin::get()->getLabel();
    }

    public static function getPluralModelLabel(): string
    {
        return ActivitylogPlugin::get()->getPluralLabel();
    }

    public static function getNavigationIcon(): string
    {
        return ActivitylogPlugin::get()->getNavigationIcon();
    }

    public static function getNavigationLabel(): string
    {
        return Str::title(static::getPluralModelLabel()) ?? Str::title(static::getModelLabel());
    }

    public static function getNavigationSort(): ?int
    {
        return ActivitylogPlugin::get()->getNavigationSort();
    }

    public static function getNavigationGroup(): ?string
    {
        return ActivitylogPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationBadge(): ?string
    {
        return ActivitylogPlugin::get()->getNavigationCountBadge() ?
            number_format(static::getModel()::count()) : null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Group::make([
                    Section::make([
                        TextInput::make('causer_id')
                            ->afterStateHydrated(function ($component, ?Model $record) {
                                /** @phpstan-ignore-next-line */
                                return $component->state($record->causer?->name);
                            })
                            ->label(__('user')),

                        TextInput::make('subject_type')
                            ->afterStateHydrated(function ($component, ?Model $record, $state) {
                                /** @var Activity&ActivityModel $record */
                                return $state ? $component->state(Str::of($state)->afterLast('\\')->headline().' # '.$record->subject_id) : '-';
                            })
                            ->label(__('subject')),

                        Textarea::make('description')
                            ->label(__('description'))
                            ->rows(2)
                            ->columnSpan('full'),
                    ])
                        ->columns(2),
                ])->columnSpan(['sm' => 3]),
                Group::make([
                    Section::make([
                        Placeholder::make('log_name')
                            ->content(function (?Model $record): string {
                                /** @var Activity&ActivityModel $record */
                                return $record->log_name ? ucwords($record->log_name) : '-';
                            })
                            ->label(__('type')),

                        Placeholder::make('event')
                            ->content(function (?Model $record): string {
                                /** @phpstan-ignore-next-line */
                                return $record?->event ? ucwords($record?->event) : '-';
                            })
                            ->label(__('event')),

                        Placeholder::make('created_at')
                            ->label(__('event date'))
                            ->content(function (?Model $record): string {
                                /** @var Activity&ActivityModel $record */
                                return $record->created_at ? "{$record->created_at->format(config('filament-logger.datetime_format', 'd/m/Y H:i:s'))}" : '-';
                            }),
                    ]),
                ]),
                Section::make()
                    ->columns()
                    ->visible(fn ($record) => $record->properties?->count() > 0)
                    ->schema(function (?Model $record) {
                        /** @var Activity&ActivityModel $record */
                        $properties = $record->properties->except(['attributes', 'old']);

                        $schema = [];

                        if ($properties->count()) {
                            $schema[] = KeyValue::make('properties')
                                ->label(__('properties'))
                                ->columnSpan('full');
                        }

                        if ($old = $record->properties->get('old')) {
                            $schema[] = KeyValue::make('old')
                                ->afterStateHydrated(fn (KeyValue $component) => $component->state($old))
                                ->label(__('old'));
                        }

                        if ($attributes = $record->properties->get('attributes')) {
                            $schema[] = KeyValue::make('attributes')
                                ->afterStateHydrated(fn (KeyValue $component) => $component->state($attributes))
                                ->label(__('new'));
                        }

                        return $schema;
                    }),
            ])->columns(['sm' => 4, 'lg' => null]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::getLogNameColumnCompoment(),
                static::getEventColumnCompoment(),
                static::getSubjectTypeColumnCompoment(),
                static::getCauserNameColumnCompoment(),
                static::getPropertiesColumnCompoment(),
                static::getCreatedAtColumnCompoment(),
            ]);
    }

    public static function getLogNameColumnCompoment(): Column
    {
        return TextColumn::make('log_name')
            ->badge()
            ->label(__('Type'))
            ->formatStateUsing(fn ($state) => ucwords($state))
            ->sortable();
    }

    public static function getEventColumnCompoment(): Column
    {
        return TextColumn::make('event')
            ->label(__('Event'))
            ->formatStateUsing(fn ($state) => ucwords($state))
            ->badge()
            ->color(fn (string $state): string => match ($state) {
                'draft' => 'gray',
                'updated' => 'warning',
                'created' => 'success',
                'deleted' => 'danger',
            })
            ->sortable();
    }

    public static function getSubjectTypeColumnCompoment(): Column
    {
        return TextColumn::make('subject_type')
            ->label(__('Subject'))
            ->formatStateUsing(function ($state, Model $record) {
                /** @var Activity&ActivityModel $record */
                if (! $state) {
                    return '-';
                }

                return Str::of($state)->afterLast('\\')->headline().' # '.$record->subject_id;
            });
    }

    public static function getCauserNameColumnCompoment(): Column
    {
        return TextColumn::make('causer.name')
            ->label(__('User'))
            ->getStateUsing(function (Model $record) {

                if ($record->causer_id == null) {
                    return new HtmlString('&mdash;');
                }

                return $record->causer->name;
            })
            ->searchable();
    }

    public static function getPropertiesColumnCompoment(): Column
    {
        return ViewColumn::make('properties')
            ->view('activitylog::filament.tables.columns.activity-logs-properties')
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function getCreatedAtColumnCompoment(): Column
    {
        return TextColumn::make('created_at')
            ->label(__('Logged At'))
            ->dateTime()
            ->sortable();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivitylog::route('/'),
        ];
    }
}
