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

    public function test_increment_from_last_existing_when_no_tasks_exist():void
    {
        $ref = $this->service->generate('CFG', 2026);

        $this->assertSame('CFG-2026-001', $ref);
    }


}
