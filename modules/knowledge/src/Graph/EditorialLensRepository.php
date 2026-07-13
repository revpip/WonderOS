<?php

declare(strict_types=1);

namespace WonderOS\Knowledge\Graph;

interface EditorialLensRepository
{
    public function get(string $slug): EditorialLens;

    /** @return list<EditorialLens> */
    public function active(): array;
}
