<?php

namespace Tests\Feature;

use App\Services\RefereneCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ReferenceCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReferenceCodeService  $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serivce = new RefereneCodeService();
    }

}
