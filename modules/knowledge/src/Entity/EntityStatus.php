<?php

declare(strict_types=1);

namespace WonderOS\Knowledge\Entity;

enum EntityStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Approved = 'approved';
    case Archived = 'archived';
}
