<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to watch for certain events or change its behaviors.
|
*/

uses(\Gin\EnvMerger\Tests\TestCase::class)->in(__DIR__);

