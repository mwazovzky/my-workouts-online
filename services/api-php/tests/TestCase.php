<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\AssertsProblems;
use Tests\Concerns\ReplacesWorkoutActivities;

abstract class TestCase extends BaseTestCase
{
    use AssertsProblems;
    use ReplacesWorkoutActivities;
}
