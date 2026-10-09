<?php

declare(strict_types=1);

final class JobDetailsTest extends BaseTestCase
{
	private const TEST_EMAIL = 'test.job.details@jobnet.test';
	private const TEST_CATEGORY = 'Test Job Details Category';

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

	public function test_existing_job_returns_details_with_correct_structure(): void
	{
		$userId = $this->createEmployer(self::TEST_EMAIL, 'Test', 'Details');
		$companyId = $this->createCompany(
			$userId,
			'Test Details Company',
			'Lagos',
			'https://example.test',
			'A test company for job details integration coverage.'
		);
		$this->linkCompanyToEmployer($userId, $companyId);
		$categoryId = $this->createCategory(self::TEST_CATEGORY);
		$jobId = $this->createActiveJob(
			$userId,
			$companyId,
			$categoryId,
			'Test Details Backend Developer',
			[
				'overview' => 'Details role for backend integration testing.',
				'description' => 'A searchable active job used to protect the details contract.',
			]
		);

		$response = $this->getJson('/api/jobs/jobdetails.php?id=' . $jobId);

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertSame('Job details retrieved successfully.', $response['body']['message']);
		$this->assertArrayHasKey('job', $response['body']);

		$job = $response['body']['job'];

		// Verify the returned job is the one we requested
		$this->assertSame($jobId, (int) $job['job_id']);

		// Verify representative important fields
		$this->assertSame('Test Details Backend Developer', $job['title']);
		$this->assertSame('Details role for backend integration testing.', $job['overview']);
		$this->assertSame('A searchable active job used to protect the details contract.', $job['description']);
		$this->assertSame('Lagos', $job['job_location']);
		$this->assertSame('250000.00', $job['salary_amount']);
		$this->assertSame('NGN', $job['currency']);
		$this->assertSame('Monthly', $job['salary_duration']);
		$this->assertSame('Mid', $job['experience_level']);
		$this->assertSame('fulltime', $job['employment_type']);

		// Verify company information is included
		$this->assertSame('Test Details Company', $job['company_name']);
		$this->assertSame('Lagos', $job['company_location']);
		$this->assertSame('https://example.test', $job['website']);

		// Verify computed flags default to false for unauthenticated requests
		$this->assertFalse($job['hasApplied']);
		$this->assertFalse($job['isSaved']);
		$this->assertFalse($job['isRetracted']);

		// Verify is_closed is computed (active job with future deadline should not be closed)
		$this->assertFalse($job['is_closed']);
	}

	public function test_nonexistent_job_returns_not_found(): void
	{
		$response = $this->getJson('/api/jobs/jobdetails.php?id=999999999');

		$this->assertSame(404, $response['statusCode']);
		$this->assertFalse($response['body']['status']);
		$this->assertSame('Job not found', $response['body']['message']);
		$this->assertArrayNotHasKey('job', $response['body']);
	}

	public function test_missing_job_id_returns_bad_request(): void
	{
		$response = $this->getJson('/api/jobs/jobdetails.php');

		$this->assertSame(400, $response['statusCode']);
		$this->assertFalse($response['body']['status']);
		$this->assertSame('Job ID is required.', $response['body']['message']);
	}

	private function deleteTestData(): void
	{
		$this->cleanupUsersByEmails([self::TEST_EMAIL]);
		$this->cleanupCategoriesByNames([self::TEST_CATEGORY]);
	}
}
