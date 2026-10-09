<?php

declare(strict_types=1);

final class JobListingTest extends BaseTestCase
{
	private const TEST_EMAIL = 'test.job.listing@jobnet.test';
	private const TEST_CATEGORY = 'Test Job Listing Category';
	private const TEST_KEYWORD = 'Listing Contract';

	protected function setUp(): void
	{
		parent::setUp();
		$this->deleteTestData();
	}

	protected function tearDown(): void
	{
		$this->deleteTestData();
		parent::tearDown();
	}

	public function test_job_listing_returns_matching_active_job_for_keyword(): void
	{
		$userId = $this->createEmployer(self::TEST_EMAIL, 'Test', 'Listing');
		$companyId = $this->createCompany($userId, 'Test Listing Company');
		$this->linkCompanyToEmployer($userId, $companyId);
		$categoryId = $this->createCategory(self::TEST_CATEGORY);
		$jobId = $this->createActiveJob(
			$userId,
			$companyId,
			$categoryId,
			'Test Listing Contract Developer'
		);

		$response = $this->getJson(
			'/api/jobs/all_jobs.php?keyword=' . rawurlencode(self::TEST_KEYWORD)
		);

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertArrayHasKey('jobs', $response['body']);
		$this->assertArrayHasKey('total', $response['body']);
		$this->assertSame(1, (int) $response['body']['total']);
		$this->assertCount(1, $response['body']['jobs']);

		$job = $response['body']['jobs'][0];
		$this->assertSame($jobId, (int) $job['job_id']);
		$this->assertSame('Test Listing Contract Developer', $job['title']);
		$this->assertSame('Lagos', $job['location']);
		$this->assertSame('Test Listing Company', $job['company_name']);
		$this->assertSame(self::TEST_CATEGORY, $job['category_name']);
		$this->assertArrayHasKey('isSaved', $job);
		$this->assertFalse($job['isSaved']);
	}

	private function deleteTestData(): void
	{
		$this->cleanupUsersByEmails([self::TEST_EMAIL]);
		$this->cleanupCategoriesByNames([self::TEST_CATEGORY]);
	}
}
