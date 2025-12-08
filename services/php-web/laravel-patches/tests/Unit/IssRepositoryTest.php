<?php

namespace Tests\Unit;

use App\Repositories\IssRepository;
use App\DTO\IssPositionDTO;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IssRepositoryTest extends TestCase
{
    /**
     * Test getHistory returns array of IssPositionDTO
     */
    public function test_get_history_returns_dto_array(): void
    {
        // Mock DB query
        DB::shouldReceive('table')
            ->once()
            ->with('iss_fetch_log')
            ->andReturnSelf();
        
        DB::shouldReceive('orderBy')
            ->once()
            ->with('timestamp', 'desc')
            ->andReturnSelf();
        
        DB::shouldReceive('limit')
            ->once()
            ->with(100)
            ->andReturnSelf();
        
        DB::shouldReceive('get')
            ->once()
            ->andReturn(collect([
                (object)[
                    'id' => 1,
                    'latitude' => 45.5,
                    'longitude' => -122.6,
                    'altitude' => 408.5,
                    'velocity' => 27600.0,
                    'timestamp' => '2025-12-09 12:00:00',
                    'fetched_at' => '2025-12-09 12:00:00',
                ]
            ]));
        
        $repo = new IssRepository();
        $result = $repo->getHistory(null, null, 100);
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(IssPositionDTO::class, $result[0]);
    }

    /**
     * Test getHistory with date filters
     */
    public function test_get_history_with_date_filters(): void
    {
        $startDate = '2025-12-01';
        $endDate = '2025-12-09';
        
        DB::shouldReceive('table')
            ->once()
            ->with('iss_fetch_log')
            ->andReturnSelf();
        
        DB::shouldReceive('orderBy')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('where')
            ->once()
            ->with('timestamp', '>=', $startDate)
            ->andReturnSelf();
        
        DB::shouldReceive('where')
            ->once()
            ->with('timestamp', '<=', $endDate)
            ->andReturnSelf();
        
        DB::shouldReceive('limit')
            ->once()
            ->andReturnSelf();
        
        DB::shouldReceive('get')
            ->once()
            ->andReturn(collect([]));
        
        $repo = new IssRepository();
        $result = $repo->getHistory($startDate, $endDate, 50);
        
        $this->assertIsArray($result);
    }

    /**
     * Test getHistory respects limit parameter
     */
    public function test_get_history_respects_limit(): void
    {
        $limit = 25;
        
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
        
        $repo = new IssRepository();
        $result = $repo->getHistory(null, null, $limit);
        
        $this->assertIsArray($result);
    }

    /**
     * Test IssPositionDTO fromArray conversion
     */
    public function test_iss_position_dto_from_array(): void
    {
        $data = [
            'id' => 1,
            'latitude' => 45.5,
            'longitude' => -122.6,
            'altitude' => 408.5,
            'velocity' => 27600.0,
            'timestamp' => '2025-12-09 12:00:00',
            'fetched_at' => '2025-12-09 12:00:00',
        ];
        
        $dto = IssPositionDTO::fromArray($data);
        
        $this->assertEquals(1, $dto->id);
        $this->assertEquals(45.5, $dto->latitude);
        $this->assertEquals(-122.6, $dto->longitude);
        $this->assertEquals(408.5, $dto->altitude);
        $this->assertEquals(27600.0, $dto->velocity);
    }

    /**
     * Test getHistory returns empty array when no data
     */
    public function test_get_history_returns_empty_array(): void
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
        
        $repo = new IssRepository();
        $result = $repo->getHistory();
        
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }
}
