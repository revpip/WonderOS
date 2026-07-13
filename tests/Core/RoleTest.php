<?php

declare(strict_types=1);

namespace WonderOS\Tests\Core;

use DomainException;
use PHPUnit\Framework\TestCase;
use WonderOS\Core\Auth\Role;

final class RoleTest extends TestCase
{
    public function test_editor_can_manage_editorial_lenses(): void
    {
        self::assertTrue(Role::Editor->permits('editorial.manage'));
        self::assertFalse(Role::Editor->permits('users.manage'));
    }

    public function test_administrator_can_manage_users(): void
    {
        self::assertTrue(Role::Administrator->permits('users.manage'));
    }

    public function test_viewer_cannot_write_editorial_configuration(): void
    {
        $this->expectException(DomainException::class);
        Role::Viewer->assertPermits('editorial.manage');
    }
}
