<?php

namespace App\Saas\Filament\Pages;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Pages\Auth\EditProfile;

/** The super admin's own profile: photo, name, email and password. Opens from the user menu. */
class Profile extends EditProfile
{
    public function form(Form $form): Form
    {
        return $form->schema([
            FileUpload::make('avatar')
                ->label('Profile photo')
                ->avatar()
                ->image()
                ->imageEditor()
                ->circleCropper()
                ->disk('public')
                ->directory('avatars')
                ->visibility('public')
                ->maxSize(2048),
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
        ]);
    }
}
