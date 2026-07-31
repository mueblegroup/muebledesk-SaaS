<?php
namespace App\Enums;

enum UserRoleEnum: string
{
    case Admin = 'admin';
    case Employee = 'employee';
    case Customer = 'customer';
}
