<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // On Windows the running dev server locks files in storage/framework/views, so tests compile elsewhere.
        $compiledViews = sys_get_temp_dir().DIRECTORY_SEPARATOR.'blexchat-test-views';

        if (! is_dir($compiledViews)) {
            mkdir($compiledViews, 0777, true);
        }

        config(['view.compiled' => $compiledViews]);
    }
}
