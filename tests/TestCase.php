<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The array cache normally keeps live PHP objects. Real stores (database, file, Redis) serialize values and,
        // since Laravel 13, refuse to un-serialize classes missing from cache.serializable_classes. Serializing here
        // makes the tests behave like production, so a cached Eloquent model that can't survive a real cache fails a test.
        $this->app['config']->set('cache.stores.array.serialize', true);
        $this->app['cache']->forgetDriver('array');
    }
}
