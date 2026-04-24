<?php

namespace Tests;

use Database\Seeders\TestingSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Run the TestingSeeder after every RefreshDatabase migration so that
     * a consistent, reusable baseline dataset is available in all Feature tests.
     */
    protected bool $seed = true;

    protected string $seeder = TestingSeeder::class;
}
