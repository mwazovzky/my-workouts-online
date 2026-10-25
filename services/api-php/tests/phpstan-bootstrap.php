<?php

use Tests\Concerns\AssertsProblems;

/*
 * Registers the test-only TestResponse macros so PHPStan can resolve
 * `$response->assertProblem()` and friends.
 */
AssertsProblems::registerResponseMacros();
