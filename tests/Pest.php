<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
| The default test case for Pest. All Feature tests use RefreshDatabase
| which wraps each test in a PostgreSQL transaction and rolls it back,
| keeping the suite fast without leaving data behind.
|
| Concurrent tests use DatabaseMigrations instead. Each concurrent test
| runs `migrate:fresh` so data inserted by the parent process is genuinely
| committed to the database — making it visible to child processes spawned
| via proc_open for true parallel locking tests.
*/

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in("Feature");

pest()
    ->extend(TestCase::class)
    ->use(DatabaseMigrations::class)
    ->in("Concurrent");

pest()->extend(TestCase::class)->in("Unit");
