<?php

namespace App\Enums;

enum UserRoleEnum: string
{
    case SuperAdmin = 'superadmin';
    case Admin = 'admin';
    case Employee = 'employee';
    case Customer = 'customer';
}
