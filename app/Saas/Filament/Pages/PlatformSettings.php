<?php

namespace App\Saas\Filament\Pages;

use App\Saas\Models\PlatformSetting;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use App\Saas\Services\MailSettings;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Illuminate\Support\Facades\Mail;

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
        'indigo' => 'Indigo', 'blue' => 'Blue', 'sky' => 'Sky', 'teal' => 'Teal', 'emerald' => 'Emerald',
        'amber' => 'Amber', 'orange' => 'Orange', 'rose' => 'Rose', 'violet' => 'Violet',
    ];

    /** Stored encrypted; never loaded back into the form. A blank field keeps the saved value. */
    // private const SECRETS = ['stripe_secret', 'stripe_webhook_secret', 'paypal_secret'];
    private const SECRETS = ['stripe_secret', 'stripe_webhook_secret', 'paypal_secret', 'smtp_password'];

    public function mount(): void
    {
        $v = PlatformSetting::allValues();

        $this->form->fill([
            'app_name' => $v['app_name'] ?? 'SPMS Super Admin',
            'logo' => $v['logo'] ?? null,
            'favicon' => $v['favicon'] ?? null,
            'primary_color' => $v['primary_color'] ?? 'indigo',
            'billing_currency' => $v['billing_currency'] ?? config('billing.currency', 'USD'),
            'stripe_enabled' => ($v['stripe_enabled'] ?? '1') !== '0',
            'paypal_enabled' => ($v['paypal_enabled'] ?? '1') !== '0',
            'manual_enabled' => ($v['manual_enabled'] ?? '1') !== '0',
            'paypal_mode' => $v['paypal_mode'] ?? config('billing.paypal.mode', 'sandbox'),
            'paypal_client_id' => $v['paypal_client_id'] ?? null,
            'paypal_webhook_id' => $v['paypal_webhook_id'] ?? null,
            'smtp_enabled' => ($v['smtp_enabled'] ?? '0') === '1',
            'smtp_host' => $v['smtp_host'] ?? null,
            'smtp_port' => $v['smtp_port'] ?? 587,
            'smtp_encryption' => $v['smtp_encryption'] ?? 'tls',
            'smtp_username' => $v['smtp_username'] ?? null,
            'smtp_from_address' => $v['smtp_from_address'] ?? null,
            'smtp_from_name' => $v['smtp_from_name'] ?? null,
            'notify_email_enabled' => ($v['notify_email_enabled'] ?? '1') !== '0',
            'notify_web_enabled' => ($v['notify_web_enabled'] ?? '1') !== '0',
            'notify_emails' => $v['notify_emails'] ?? null,
        ]);
    }

    private static function secretField(string $name, string $label, string $example): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->password()
            ->revealable()
            ->autocomplete('new-password')
            ->placeholder(fn () => PlatformSetting::get($name) ? '•••••••• saved (leave blank to keep)' : $example);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Tabs::make('settings')->tabs([
                    Tabs\Tab::make('Brand')->icon('heroicon-o-paint-brush')->schema([
                        Section::make('Brand')
                            ->description('Shown in the panel header, on the sign-in page and in the browser tab.')
                            ->columns(2)
                            ->schema([
                                TextInput::make('app_name')->label('Company / platform name')->required()->maxLength(80)->columnSpanFull(),
                                FileUpload::make('logo')->label('Main company logo')->image()->disk('public')->directory('branding')
                                    ->visibility('public')->maxSize(2048)->helperText('PNG, SVG or JPG, up to 2 MB. A wide logo works best.'),
                                FileUpload::make('favicon')->label('Favicon')->image()->disk('public')->directory('branding')
                                    ->visibility('public')->maxSize(512)->helperText('Small square icon, up to 512 KB.'),
                                Select::make('primary_color')->label('Accent colour')->options(self::COLORS)->required()->native(false),
                            ]),
                    ]),

                    Tabs\Tab::make('Payments')->icon('heroicon-o-credit-card')->schema([
                        Section::make('General')->columns(2)->schema([
                            TextInput::make('billing_currency')->label('Currency (3 letters)')->required()->length(3)
                                ->helperText('For example USD or EUR. Must match the currency of your plans.'),
                            Toggle::make('manual_enabled')->label('Manual upgrade (tenant asks the admin)')
                                ->helperText('Tenants can send an upgrade request that you approve in Upgrade requests.')->inline(false),
                        ]),
                        Section::make('Stripe (cards)')->columns(2)->schema([
                            Toggle::make('stripe_enabled')->label('Enable Stripe')->inline(false)->columnSpanFull(),
                            self::secretField('stripe_secret', 'Secret key', 'sk_live_… or sk_test_…'),
                            self::secretField('stripe_webhook_secret', 'Webhook signing secret', 'whsec_…'),
                            TextInput::make('stripe_webhook_url')->label('Webhook URL (add this in Stripe)')
                                ->default(url('/api/v1/billing/webhooks/stripe'))->readOnly()->dehydrated(false)->columnSpanFull(),
                        ]),
                        Section::make('PayPal')->columns(2)->schema([
                            Toggle::make('paypal_enabled')->label('Enable PayPal')->inline(false)->columnSpanFull(),
                            Select::make('paypal_mode')->label('Mode')->options(['sandbox' => 'Sandbox (testing)', 'live' => 'Live'])->native(false),
                            TextInput::make('paypal_client_id')->label('Client ID'),
                            self::secretField('paypal_secret', 'Client secret', 'Client secret'),
                            TextInput::make('paypal_webhook_id')->label('Webhook ID'),
                            TextInput::make('paypal_webhook_url')->label('Webhook URL (add this in PayPal)')
                                ->default(url('/api/v1/billing/webhooks/paypal'))->readOnly()->dehydrated(false)->columnSpanFull(),
                        ]),
                    ]),

                                        Tabs\Tab::make('Email')->icon('heroicon-o-envelope')->schema([
                        Section::make('SMTP server')
                            ->description('Used for every email the platform sends (verification, approvals, notifications). Leave off to use the .env mail settings.')
                            ->columns(2)
                            ->schema([
                                Toggle::make('smtp_enabled')->label('Use these SMTP settings')->inline(false)->columnSpanFull(),
                                TextInput::make('smtp_host')->label('Host')->placeholder('smtp.example.com'),
                                TextInput::make('smtp_port')->label('Port')->numeric()->placeholder('587'),
                                Select::make('smtp_encryption')->label('Encryption')
                                    ->options(['tls' => 'TLS / STARTTLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None'])->native(false),
                                TextInput::make('smtp_username')->label('Username')->autocomplete('off'),
                                self::secretField('smtp_password', 'Password', 'SMTP password'),
                                TextInput::make('smtp_from_address')->label('From address')->email(),
                                TextInput::make('smtp_from_name')->label('From name'),
                                Actions::make([
                                    Action::make('sendTest')->label('Send test email')->icon('heroicon-o-paper-airplane')
                                        ->color('gray')->action('sendTest'),
                                ])->columnSpanFull(),
                                \Filament\Forms\Components\Placeholder::make('test_hint')
                                    ->label('')->content('Save first: the test uses the saved settings and goes to your own email address.')->columnSpanFull(),
                            ]),
                        Section::make('Notifications to you')
                            ->description('When a company registers, verifies its email, or asks to change its plan.')
                            ->columns(2)
                            ->schema([
                                Toggle::make('notify_web_enabled')->label('Bell notification in this panel')->inline(false),
                                Toggle::make('notify_email_enabled')->label('Email notification')->inline(false),
                                TextInput::make('notify_emails')->label('Send emails to')
                                    ->helperText('Comma-separated. Leave empty to email every super admin.')->columnSpanFull(),
                            ]),
                    ]),
                ])->columnSpanFull(),
            ]);
    }

    public function sendTest(): void
    {
        $to = auth()->user()->email;

        try {
            MailSettings::apply();
            Mail::raw('This is a test email from '.config('app.name').'. Your email settings work.', fn ($m) => $m->to($to)->subject('Test email'));

            Notification::make()->title('Test email sent to '.$to)->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Could not send the test email')->body($e->getMessage())->danger()->send();
        }
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

        $values = Arr::only($state, ['app_name', 'logo', 'favicon', 'primary_color']) + [
            'billing_currency' => strtoupper(trim($state['billing_currency'] ?? 'USD')),
            'stripe_enabled' => ! empty($state['stripe_enabled']) ? '1' : '0',
            'paypal_enabled' => ! empty($state['paypal_enabled']) ? '1' : '0',
            'manual_enabled' => ! empty($state['manual_enabled']) ? '1' : '0',
            'paypal_mode' => $state['paypal_mode'] ?? 'sandbox',
            'paypal_client_id' => trim((string) ($state['paypal_client_id'] ?? '')),
            'paypal_webhook_id' => trim((string) ($state['paypal_webhook_id'] ?? '')),
            'smtp_enabled' => ! empty($state['smtp_enabled']) ? '1' : '0',
            'smtp_host' => trim((string) ($state['smtp_host'] ?? '')),
            'smtp_port' => (string) ($state['smtp_port'] ?? '587'),
            'smtp_encryption' => $state['smtp_encryption'] ?? 'tls',
            'smtp_username' => trim((string) ($state['smtp_username'] ?? '')),
            'smtp_from_address' => trim((string) ($state['smtp_from_address'] ?? '')),
            'smtp_from_name' => trim((string) ($state['smtp_from_name'] ?? '')),
            'notify_email_enabled' => ! empty($state['notify_email_enabled']) ? '1' : '0',
            'notify_web_enabled' => ! empty($state['notify_web_enabled']) ? '1' : '0',
            'notify_emails' => trim((string) ($state['notify_emails'] ?? '')),
        ];

        foreach (self::SECRETS as $secret) {
            if (filled($state[$secret] ?? null)) {
                $values[$secret] = Crypt::encryptString(trim($state[$secret]));
            }
        }

        PlatformSetting::put($values);
        Cache::forget('paypal_access_token'); // new PayPal keys or mode must not reuse an old token

        Notification::make()->title('Settings saved')->success()->send();

        // Reload so the new logo, name and colour apply straight away.
        $this->redirect(static::getUrl());
    }
}