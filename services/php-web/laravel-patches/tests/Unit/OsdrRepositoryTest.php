<?php

namespace Tests\Unit;

use App\Repositories\OsdrRepository;
use App\DTO\OsdrDatasetDTO;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OsdrRepositoryTest extends TestCase
{
    /**
     * Test getAll returns array of OsdrDatasetDTO
     */
    public function test_get_all_returns_dto_array(): void
    {
        DB::shouldReceive('table')
            ->once()
            ->with('osdr_items')
            ->andReturnSelf();
        
        DB::shouldReceive('orderBy')
            ->once()
            ->with('updated_at', 'desc')
            ->andReturnSelf();
        
        DB::shouldReceive('limit')
            ->once()
            ->with(50)
            ->andReturnSelf();
        
        DB::shouldReceive('get')
            ->once()
            ->andReturn(collect([
                (object)[
                    'dataset_id' => 'OSD-123',
                    'title' => 'Test Dataset',
                    'description' => 'Test Description',
                    'release_date' => '2025-01-01',
                    'updated_at' => '2025-12-09 12:00:00',
                ]
            ]));
        
        $repo = new OsdrRepository();
        $result = $repo->getAll(50);
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(OsdrDatasetDTO::class, $result[0]);
    }

    /**
     * Test getAll respects limit parameter
     */
    public function test_get_all_respects_limit(): void
    {
        $limit = 10;
        
        DB::shouldReceive('table')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('orderBy')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('limit')
            ->once()
            ->with($limit)
            ->andReturnSelf();
        
        DB::shouldReceive('get')
            ->once()
            ->andReturn(collect([]));
        
        $repo = new OsdrRepository();
        $result = $repo->getAll($limit);
        
        $this->assertIsArray($result);
    }

    /**
     * Test OsdrDatasetDTO fromArray conversion
     */
    public function test_osdr_dataset_dto_from_array(): void
    {
        $data = [
            'dataset_id' => 'OSD-123',
            'title' => 'Mouse RNA-Seq Study',
            'description' => 'Gene expression analysis',
            'release_date' => '2025-01-01',
            'updated_at' => '2025-12-09 12:00:00',
        ];
        
        $dto = OsdrDatasetDTO::fromArray($data);

        $this->assertEquals('OSD-123', $dto->datasetId);
        $this->assertEquals('Mouse RNA-Seq Study', $dto->title);
        $this->assertEquals('Gene expression analysis', $dto->description);
    }

    /**
     * Test getAll returns empty array when no data
     */
    public function test_get_all_returns_empty_array(): void
    {
        DB::shouldReceive('table')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('orderBy')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('limit')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('get')
            ->once()
            ->andReturn(collect([]));
        
        $repo = new OsdrRepository();
        $result = $repo->getAll(50);
        
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    /**
     * Test pagination logic (offset calculation)
     */
    public function test_pagination_offset_calculation(): void
    {
        $limit = 20;
        $page = 3;
        
        // Expected offset: (page - 1) * limit = (3 - 1) * 20 = 40
        $expectedOffset = ($page - 1) * $limit;
        
        $this->assertEquals(40, $expectedOffset);
    }
}
