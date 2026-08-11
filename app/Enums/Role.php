<?php

namespace App\Enums;

/**
 * Replaces the source app's `roles` + `user_roles` tables. Phase 2 builds middleware and
 * policies on top of this; the column itself is a plain string(20) on `users`.
 */
enum Role: string
{
    case Ap = 'ap';
    case Admin = 'admin';
    case Trainer = 'trainer';
}
