<?php

namespace Tests\Feature;

use App\Services\ReferenceCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Task;

class ReferenceCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReferenceCodeService  $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReferenceCodeService();
    }

    public function test_increment_from_last_existing_when_no_tasks_exist():void
    {
        $ref = $this->service->generate('CFG', 2026);

        $this->assertSame('CFG-2026-001', $ref);
    }

    public function test_increment_from_last_existing_reference(): void
    {
        Task::factory()->create(['reference_code' => 'CFG-2026-001']);
        Task::factory()->create(['reference_code' => 'CFG-2026-002']);

        $ref = $this->service->generate('CFG', 2026);

        $this->assertSame('CFG-2026-003', $ref);
    }

    public function test_does_not_conflict_between_org_types(): void
    {
        Task::factory()->create(['reference_code' => 'CFG-2026-001']);

        $ref = $this->service->generate('COMITE', 2026);

        $this->assertSame('COMITE-2026-001', $ref);

    }

    public function test_does_not_conflict_between_years(): void
    {
        Task::factory()->create(['reference_code' => 'CFG-2025-010']);

        $ref = $this->service->generate('CFG', 2026);

        $this->assertSame('CFG-2026-001', $ref);
    }

    public function test_generates_correctly_after_deletion(): void
    {
        Task::factory()->create(['reference_code' => 'CFG-2026-001']);
        Task::factory()->create(['reference_code' => 'CFG-2026-002']);
        Task::factory()->create(['reference_code' => 'CFG-2026-003']);

        Task::where('reference_code', 'CFG-2026-002')->delete();

        $ref = $this->service->generate('CFG', 2026);

        $this->assertSame('CFG-2026-004', $ref);
    }

    public function test_pads_number_with_leading_zeros(): void
    {
        for ($i = 1; $i <= 9; $i++) {
            Task::factory()->create([
                'reference_code' => 'CFG-2026-' . str_pad($i, 3, '0', STR_PAD_LEFT),
            ]);
        }

        $ref = $this->service->generate('CFG', 2026);

        $this->assertSame('CFG-2026-010', $ref);
    }

}
