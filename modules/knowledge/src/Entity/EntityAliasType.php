<?php

declare(strict_types=1);

namespace WonderOS\Knowledge\Entity;

enum EntityAliasType: string
{
    case Common = 'common';
    case Scientific = 'scientific';
    case Historic = 'historic';
    case Regional = 'regional';
    case Spelling = 'spelling';
    case Other = 'other';
}
