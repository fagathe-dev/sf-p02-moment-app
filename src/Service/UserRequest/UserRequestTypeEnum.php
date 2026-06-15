<?php
namespace App\Service\UserRequest;

enum UserRequestTypeEnum: string
{

    case AUTH_ACCOUNT_VERIFICATION = 'AUTH_ACCOUNT_VERIFICATION';
    case AUTH_PASSWORD_RESET = 'AUTH_PASSWORD_RESET';
    case AUTH_EMAIL_RESET = 'AUTH_EMAIL_RESET';
    // Le nouveau type pour le changement d'e-mail du profil
    case AUTH_PROFILE_CHANGE_EMAIL = 'AUTH_PROFILE_CHANGE_EMAIL';

}