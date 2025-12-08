<?php

namespace Tests\Unit;

use App\Services\IssService;
use App\Repositories\IssRepository;
use Tests\TestCase;
use Mockery;

class IssServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test getHistory returns array
     */
    public function test_get_history_returns_array(): void
    {
        $mockRepo = Mockery::mock(IssRepository::class);
        $mockRepo->shouldReceive('getHistory')
            ->once()
            ->with(null, null, 100)
            ->andReturn([]);
        
        $service = new IssService($mockRepo);
        $result = $service->getHistory();
        
        $this->assertIsArray($result);
    }

    /**
     * Test getDatasets with limit
     */
    public function test_get_datasets_respects_limit(): void
    {
        $mockRepo = Mockery::mock(IssRepository::class);
        $mockRepo->shouldReceive('getHistory')
            ->once()
            ->with(null, null, 25)
            ->andReturn([]);
        
        $service = new IssService($mockRepo);
        $result = $service->getHistory(25);
        
        $this->assertIsArray($result);
    }

    /**
     * Test date range filtering
     */
    public function test_get_history_with_date_range(): void
    {
        $mockRepo = Mockery::mock(IssRepository::class);
        $mockRepo->shouldReceive('getHistory')
            ->once()
            ->with('2025-12-01', '2025-12-09', 100)
            ->andReturn([]);
        
        $service = new IssService($mockRepo);
        $result = $service->getHistory('2025-12-01', '2025-12-09', 100);
        
        $this->assertIsArray($result);
    }
}
