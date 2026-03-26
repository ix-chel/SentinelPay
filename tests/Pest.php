<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
| Feature tests use RefreshDatabase against PostgreSQL so every API test
| exercises the same relational model as the runtime application.
|
| Concurrent tests use DatabaseMigrations instead. Each concurrent test
| runs migrate:fresh so data inserted by the parent process is committed
| to PostgreSQL and visible to child processes spawned via proc_open.
*/

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

pest()
    ->extend(TestCase::class)
    ->use(DatabaseMigrations::class)
    ->in('Concurrent');

pest()->extend(TestCase::class)->in('Unit');
