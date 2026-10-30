<?php

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Roles/permissions must exist for member assignment on register/device auth.
        if (in_array(RefreshDatabase::class, class_uses_recursive($this), true)) {
            $this->seed(RolesAndPermissionsSeeder::class);
        }
    }
}
