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
		// Ensure fresh exchange rates cache to prevent external API calls
		$this->db->query('UPDATE exchange_rates SET last_updated = NOW()');
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

	public function test_job_listing_pagination_returns_correct_slice_and_metadata(): void
	{
		$userId = $this->createEmployer(self::TEST_EMAIL, 'Test', 'Listing');
		$companyId = $this->createCompany($userId, 'Test Listing Company');
		$this->linkCompanyToEmployer($userId, $companyId);
		$categoryId = $this->createCategory(self::TEST_CATEGORY);

		$job1Id = $this->createActiveJob($userId, $companyId, $categoryId, self::TEST_KEYWORD . ' 1');
		$job2Id = $this->createActiveJob($userId, $companyId, $categoryId, self::TEST_KEYWORD . ' 2');
		$job3Id = $this->createActiveJob($userId, $companyId, $categoryId, self::TEST_KEYWORD . ' 3');

		// Page 1 with per_page = 2
		$page1 = $this->getJson('/api/jobs/all_jobs.php?keyword=' . rawurlencode(self::TEST_KEYWORD) . '&page=1&per_page=2');
		$this->assertSame(200, $page1['statusCode']);
		$this->assertTrue($page1['body']['status']);
		$this->assertSame(3, (int) $page1['body']['total']);
		$this->assertSame(1, (int) $page1['body']['page']);
		$this->assertSame(2, (int) $page1['body']['per_page']);
		$this->assertSame(2, (int) $page1['body']['total_pages']);
		$this->assertCount(2, $page1['body']['jobs']);
		$page1JobIds = array_map(fn($job) => (int) $job['job_id'], $page1['body']['jobs']);

		// Page 2 with per_page = 2
		$page2 = $this->getJson('/api/jobs/all_jobs.php?keyword=' . rawurlencode(self::TEST_KEYWORD) . '&page=2&per_page=2');
		$this->assertSame(200, $page2['statusCode']);
		$this->assertTrue($page2['body']['status']);
		$this->assertSame(3, (int) $page2['body']['total']);
		$this->assertSame(2, (int) $page2['body']['page']);
		$this->assertSame(2, (int) $page2['body']['per_page']);
		$this->assertSame(2, (int) $page2['body']['total_pages']);
		$this->assertCount(1, $page2['body']['jobs']);
		$page2JobIds = array_map(fn($job) => (int) $job['job_id'], $page2['body']['jobs']);

		// Verify non-overlapping slices that together cover all 3 jobs
		$this->assertEmpty(array_intersect($page1JobIds, $page2JobIds), 'Page 1 and Page 2 must not contain overlapping jobs.');
		$allReturnedIds = array_merge($page1JobIds, $page2JobIds);
		$this->assertContains($job1Id, $allReturnedIds);
		$this->assertContains($job2Id, $allReturnedIds);
		$this->assertContains($job3Id, $allReturnedIds);
	}

	public function test_job_listing_sorting_by_date_posted_and_relevance(): void
	{
		$userId = $this->createEmployer(self::TEST_EMAIL, 'Test', 'Listing');
		$companyId = $this->createCompany($userId, 'Test Listing Company');
		$this->linkCompanyToEmployer($userId, $companyId);
		$categoryId = $this->createCategory(self::TEST_CATEGORY);

		$jobAlphaId = $this->createActiveJob($userId, $companyId, $categoryId, self::TEST_KEYWORD . ' Alpha Role');
		$jobZuluId = $this->createActiveJob($userId, $companyId, $categoryId, self::TEST_KEYWORD . ' Zulu Role');

		// Set explicit published_at timestamps
		$this->db->query("UPDATE jobs_table SET published_at = '2026-01-01 10:00:00' WHERE job_id = $jobAlphaId");
		$this->db->query("UPDATE jobs_table SET published_at = '2026-02-01 10:00:00' WHERE job_id = $jobZuluId");

		// 1. Default / datePosted sort (newest published_at first -> Zulu, then Alpha)
		$dateSort = $this->getJson('/api/jobs/all_jobs.php?keyword=' . rawurlencode(self::TEST_KEYWORD) . '&sort=datePosted');
		$this->assertSame(200, $dateSort['statusCode']);
		$dateJobIds = array_map(fn($job) => (int) $job['job_id'], $dateSort['body']['jobs']);
		$this->assertSame([$jobZuluId, $jobAlphaId], $dateJobIds);

		// 2. Relevance sort (alphabetical by title -> Alpha, then Zulu)
		$relevanceSort = $this->getJson('/api/jobs/all_jobs.php?keyword=' . rawurlencode(self::TEST_KEYWORD) . '&sort=relevance');
		$this->assertSame(200, $relevanceSort['statusCode']);
		$relevanceJobIds = array_map(fn($job) => (int) $job['job_id'], $relevanceSort['body']['jobs']);
		$this->assertSame([$jobAlphaId, $jobZuluId], $relevanceJobIds);
	}

	public function test_job_listing_filtering_by_location_employment_type_and_experience_level(): void
	{
		$userId = $this->createEmployer(self::TEST_EMAIL, 'Test', 'Listing');
		$companyId = $this->createCompany($userId, 'Test Listing Company');
		$this->linkCompanyToEmployer($userId, $companyId);
		$categoryId = $this->createCategory(self::TEST_CATEGORY);

		$jobAbujaId = $this->createActiveJob($userId, $companyId, $categoryId, self::TEST_KEYWORD . ' Abuja Contract', [
			'location' => 'Abuja, Nigeria',
			'employment_type' => 'contract',
			'experience_level' => 'Senior',
		]);

		$jobLagosId = $this->createActiveJob($userId, $companyId, $categoryId, self::TEST_KEYWORD . ' Lagos Fulltime', [
			'location' => 'Lagos, Nigeria',
			'employment_type' => 'fulltime',
			'experience_level' => 'Junior',
		]);

		// Filter by location
		$locResponse = $this->getJson('/api/jobs/all_jobs.php?keyword=' . rawurlencode(self::TEST_KEYWORD) . '&location=Abuja');
		$this->assertSame(200, $locResponse['statusCode']);
		$this->assertCount(1, $locResponse['body']['jobs']);
		$this->assertSame($jobAbujaId, (int) $locResponse['body']['jobs'][0]['job_id']);

		// Filter by employment_type
		$typeResponse = $this->getJson('/api/jobs/all_jobs.php?keyword=' . rawurlencode(self::TEST_KEYWORD) . '&employment_type=fulltime');
		$this->assertSame(200, $typeResponse['statusCode']);
		$this->assertCount(1, $typeResponse['body']['jobs']);
		$this->assertSame($jobLagosId, (int) $typeResponse['body']['jobs'][0]['job_id']);

		// Filter by experience_level
		$expResponse = $this->getJson('/api/jobs/all_jobs.php?keyword=' . rawurlencode(self::TEST_KEYWORD) . '&experience_level=Senior');
		$this->assertSame(200, $expResponse['statusCode']);
		$this->assertCount(1, $expResponse['body']['jobs']);
		$this->assertSame($jobAbujaId, (int) $expResponse['body']['jobs'][0]['job_id']);
	}

	public function test_job_listing_excludes_closed_and_expired_jobs(): void
	{
		$userId = $this->createEmployer(self::TEST_EMAIL, 'Test', 'Listing');
		$companyId = $this->createCompany($userId, 'Test Listing Company');
		$this->linkCompanyToEmployer($userId, $companyId);
		$categoryId = $this->createCategory(self::TEST_CATEGORY);

		// Active with future deadline -> should appear
		$activeJobId = $this->createActiveJob($userId, $companyId, $categoryId, self::TEST_KEYWORD . ' Active Valid', [
			'status' => 'active',
			'deadline' => date('Y-m-d', strtotime('+30 days')),
		]);

		// Closed job with future deadline -> must be excluded
		$this->createActiveJob($userId, $companyId, $categoryId, self::TEST_KEYWORD . ' Closed Job', [
			'status' => 'closed',
			'deadline' => date('Y-m-d', strtotime('+30 days')),
		]);

		// Active job with past deadline -> must be excluded
		$this->createActiveJob($userId, $companyId, $categoryId, self::TEST_KEYWORD . ' Expired Job', [
			'status' => 'active',
			'deadline' => date('Y-m-d', strtotime('-5 days')),
		]);

		$response = $this->getJson('/api/jobs/all_jobs.php?keyword=' . rawurlencode(self::TEST_KEYWORD));
		$this->assertSame(200, $response['statusCode']);
		$this->assertCount(1, $response['body']['jobs']);
		$this->assertSame($activeJobId, (int) $response['body']['jobs'][0]['job_id']);
	}

	private function deleteTestData(): void
	{
		$this->cleanupUsersByEmails([self::TEST_EMAIL]);
		$this->cleanupCategoriesByNames([self::TEST_CATEGORY]);
	}
}
