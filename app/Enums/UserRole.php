<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Reseller = 'reseller';
    case Vendor = 'vendor';
    case Customer = 'customer';
}
