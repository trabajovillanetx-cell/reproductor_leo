<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Suspended = 'suspended';
}
