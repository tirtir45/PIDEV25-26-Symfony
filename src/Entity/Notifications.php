<?php

namespace App\Entity;

/**
 * Alias for Notification entity — used by EntrepreneurController project management features.
 * Extends Notification to reuse the same ORM mapping without duplicating the table.
 */
class Notifications extends Notification
{
}
