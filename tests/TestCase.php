<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Store the initial output buffer level so we can clean only
     * buffers that were leaked during the test.
     */
    protected int $initialObLevel;

    /**
     * Prevent output buffer warnings during tests.
     *
     * Blade's @stack/@push directives and the @vite() helper can open
     * output buffers during view rendering that persist after the test
     * finishes, causing PHPUnit's "risky test" warning. We track the
     * initial buffer level and clean any extras in tearDown().
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->initialObLevel = ob_get_level();

        $this->withoutVite();
    }

    /**
     * Close any output buffers that were opened during the test
     * beyond what PHPUnit itself started.
     */
    protected function tearDown(): void
    {
        // Close any extra output buffers leaked by view rendering
        // (PHPUnit owns level 1+; anything above that is a leak)
        while (ob_get_level() > $this->initialObLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }
}
