<?php

use App\Libraries\UserRoleResolver;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class UserRoleResolverTest extends CIUnitTestCase
{
    public function testPrimaryRoleFollowsConfiguredPriority(): void
    {
        $resolver = new UserRoleResolver();

        $this->assertSame('admin', $resolver->primaryRole(['student', 'pic', 'admin']));
        $this->assertSame('manager', $resolver->primaryRole(['pic', 'manager']));
        $this->assertSame('external', $resolver->primaryRole(['external']));
    }

    public function testPrimaryRoleFallsBackToUserWhenNoKnownRoleExists(): void
    {
        $resolver = new UserRoleResolver();

        $this->assertSame('user', $resolver->primaryRole([]));
    }
}
