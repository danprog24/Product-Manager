<?php

namespace App\Enum;

enum Role: string
{
    case ADMIN = 'admin';
    case SELLER = 'seller';
    case BUYER = 'buyer';
}