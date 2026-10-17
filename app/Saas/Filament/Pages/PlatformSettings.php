<?php

namespace App\Saas\Filament\Pages;

use App\Saas\Models\PlatformSetting;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class PlatformSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Platform';
    protected static ?string $navigationLabel = 'Settings';
    protected static ?string $title = 'Platform settings';
    protected static ?string $slug = 'settings';
    protected static string $view = 'filament.saas.platform-settings';

    public ?array $data = [];

    public const COLORS = [
        'indigo' => 'Indigo',
        'blue' => 'Blue',
        'sky' => 'Sky',
        'teal' => 'Teal',
        'emerald' => 'Emerald',
        'amber' => 'Amber',
        'orange' => 'Orange',
        'rose' => 'Rose',
        'violet' => 'Violet',
    ];

    public function mount(): void
    {
        $values = PlatformSetting::allValues();

        $this->form->fill([
            'app_name' => $values['app_name'] ?? 'SPMS Super Admin',
            'logo' => $values['logo'] ?? null,
            'favicon' => $values['favicon'] ?? null,
            'primary_color' => $values['primary_color'] ?? 'indigo',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make('Brand')
                    ->description('Shown in the panel header, on the sign-in page and in the browser tab.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('app_name')
                            ->label('Company / platform name')
                            ->required()
                            ->maxLength(80)
                            ->columnSpanFull(),
                        FileUpload::make('logo')
                            ->label('Main company logo')
                            ->image()
                            ->disk('public')
                            ->directory('branding')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->helperText('PNG, SVG or JPG, up to 2 MB. A wide logo works best.'),
                        FileUpload::make('favicon')
                            ->label('Favicon')
                            ->image()
                            ->disk('public')
                            ->directory('branding')
                            ->visibility('public')
                            ->maxSize(512)
                            ->helperText('Small square icon, up to 512 KB.'),
                        Select::make('primary_color')
                            ->label('Accent colour')
                            ->options(self::COLORS)
                            ->required()
                            ->native(false),
                    ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $old = PlatformSetting::allValues();

        // Remove replaced or cleared images so the storage folder does not fill up with unused files.
        foreach (['logo', 'favicon'] as $field) {
            $previous = $old[$field] ?? null;

            if ($previous && $previous !== ($state[$field] ?? null)) {
                Storage::disk('public')->delete($previous);
            }
        }

        PlatformSetting::put(Arr::only($state, ['app_name', 'logo', 'favicon', 'primary_color']));

        Notification::make()->title('Settings saved')->success()->send();

        // Reload so the new logo, name and colour apply straight away.
        $this->redirect(static::getUrl());
    }
}
