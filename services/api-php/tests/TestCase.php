<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\AssertsProblems;

abstract class TestCase extends BaseTestCase
{
    use AssertsProblems;
}
