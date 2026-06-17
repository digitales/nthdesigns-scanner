<?php

namespace App\Enums;

enum OutreachChannel: string
{
    case Email = 'email';
    case ContactForm = 'contact_form';
    case Linkedin = 'linkedin';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::ContactForm => 'Contact form',
            self::Linkedin => 'LinkedIn',
        };
    }
}
