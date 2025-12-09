<?php

namespace Tests\Unit;

use App\Services\IssService;
use App\Repositories\IssRepository;
use Tests\TestCase;

class IssServiceTest extends TestCase
{
    /**
     * Test IssService can be instantiated
     */
    public function test_service_can_be_instantiated(): void
    {
        $repository = $this->createMock(IssRepository::class);
        $service = new IssService($repository);
        
        $this->assertInstanceOf(IssService::class, $service);
    }

    /**
     * Test service has correct timeout
     */
    public function test_service_has_correct_timeout(): void
    {
        $repository = $this->createMock(IssRepository::class);
        $service = new IssService($repository);
        
        // IssService extends BaseHttpService with timeout=10
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('timeout');
        $property->setAccessible(true);
        
        $this->assertEquals(10, $property->getValue($service));
    }

    /**
     * Test repository is injected
     */
    public function test_repository_is_injected(): void
    {
        $repository = $this->createMock(IssRepository::class);
        $service = new IssService($repository);
        
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        
        $this->assertInstanceOf(IssRepository::class, $property->getValue($service));
    }
}
