<?php

declare(strict_types=1);

final class SalaryNormalizationTest extends BaseTestCase
{
	private const EMPLOYER_EMAIL = 'test.salary.employer@jobnet.test';
	private const SEEKER_EMAIL = 'test.salary.seeker@jobnet.test';
	private const CATEGORY_NAME = 'Test Salary Category';
	private const UNIQUE_KEYWORD = 'SalaryNormTest';

	protected function setUp(): void
	{
		parent::setUp();
		$this->deleteTestData();
		// Ensure cached exchange rates are fresh so all_jobs.php doesn't attempt external network calls
		$this->db->query('UPDATE exchange_rates SET last_updated = NOW()');
	}

	protected function tearDown(): void
	{
		$this->deleteTestData();
		parent::tearDown();
	}

	public function test_jobs_sorted_by_salary_high_to_low_are_normalized_across_currencies(): void
	{
		$employerId = $this->createEmployer(self::EMPLOYER_EMAIL);
		$companyId = $this->createCompany($employerId, 'Salary Corp');
		$this->linkCompanyToEmployer($employerId, $companyId);
		$categoryId = $this->createCategory(self::CATEGORY_NAME);

		// With USD=1.0, NGN=1331.0138, GBP=0.7565:
		// Job A: 1,331,013.80 NGN / 1331.0138 = $1,000 USD equivalent (Nominal: 1.33M)
		// Job B: 2,000.00 USD / 1.0 = $2,000 USD equivalent (Nominal: 2K)
		// Job C: 2,269.50 GBP / 0.7565 = $3,000 USD equivalent (Nominal: 2.27K)
		$jobAId = $this->createActiveJob($employerId, $companyId, $categoryId, self::UNIQUE_KEYWORD . ' Junior NGN', [
			'salary_amount' => 1331013.80,
			'currency' => 'NGN',
			'salary_duration' => 'Monthly',
		]);

		$jobBId = $this->createActiveJob($employerId, $companyId, $categoryId, self::UNIQUE_KEYWORD . ' Mid USD', [
			'salary_amount' => 2000.00,
			'currency' => 'USD',
			'salary_duration' => 'Monthly',
		]);

		$jobCId = $this->createActiveJob($employerId, $companyId, $categoryId, self::UNIQUE_KEYWORD . ' Senior GBP', [
			'salary_amount' => 2269.50,
			'currency' => 'GBP',
			'salary_duration' => 'Monthly',
		]);

		// Request sort=salaryHighToLow
		$response = $this->getJson('/api/jobs/all_jobs.php?keyword=' . self::UNIQUE_KEYWORD . '&sort=salaryHighToLow');

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertCount(3, $response['body']['jobs']);

		// Highest USD normalized equivalent must come first:
		// 1st: Job C ($3,000 USD eq)
		// 2nd: Job B ($2,000 USD eq)
		// 3rd: Job A ($1,000 USD eq, despite nominal amount 1,331,013.80 being numerically largest)
		$returnedJobIds = array_map(fn($job) => (int) $job['job_id'], $response['body']['jobs']);
		$this->assertSame([$jobCId, $jobBId, $jobAId], $returnedJobIds);
	}

	public function test_jobs_sorted_by_salary_low_to_high_are_normalized_across_currencies(): void
	{
		$employerId = $this->createEmployer(self::EMPLOYER_EMAIL);
		$companyId = $this->createCompany($employerId, 'Salary Corp');
		$this->linkCompanyToEmployer($employerId, $companyId);
		$categoryId = $this->createCategory(self::CATEGORY_NAME);

		// Job A ($1,000 USD eq), Job B ($2,000 USD eq), Job C ($3,000 USD eq)
		$jobAId = $this->createActiveJob($employerId, $companyId, $categoryId, self::UNIQUE_KEYWORD . ' Junior NGN', [
			'salary_amount' => 1331013.80,
			'currency' => 'NGN',
			'salary_duration' => 'Monthly',
		]);

		$jobBId = $this->createActiveJob($employerId, $companyId, $categoryId, self::UNIQUE_KEYWORD . ' Mid USD', [
			'salary_amount' => 2000.00,
			'currency' => 'USD',
			'salary_duration' => 'Monthly',
		]);

		$jobCId = $this->createActiveJob($employerId, $companyId, $categoryId, self::UNIQUE_KEYWORD . ' Senior GBP', [
			'salary_amount' => 2269.50,
			'currency' => 'GBP',
			'salary_duration' => 'Monthly',
		]);

		// Request sort=salaryLowToHigh
		$response = $this->getJson('/api/jobs/all_jobs.php?keyword=' . self::UNIQUE_KEYWORD . '&sort=salaryLowToHigh');

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertCount(3, $response['body']['jobs']);

		// Lowest USD normalized equivalent must come first:
		// 1st: Job A ($1,000 USD eq)
		// 2nd: Job B ($2,000 USD eq)
		// 3rd: Job C ($3,000 USD eq)
		$returnedJobIds = array_map(fn($job) => (int) $job['job_id'], $response['body']['jobs']);
		$this->assertSame([$jobAId, $jobBId, $jobCId], $returnedJobIds);
	}

	public function test_salary_range_and_currency_filters_in_job_search(): void
	{
		$employerId = $this->createEmployer(self::EMPLOYER_EMAIL);
		$companyId = $this->createCompany($employerId, 'Salary Corp');
		$this->linkCompanyToEmployer($employerId, $companyId);
		$categoryId = $this->createCategory(self::CATEGORY_NAME);

		$jobAId = $this->createActiveJob($employerId, $companyId, $categoryId, self::UNIQUE_KEYWORD . ' Low USD', [
			'salary_amount' => 800.00,
			'currency' => 'USD',
			'salary_duration' => 'Monthly',
		]);

		$jobBId = $this->createActiveJob($employerId, $companyId, $categoryId, self::UNIQUE_KEYWORD . ' Mid USD', [
			'salary_amount' => 2000.00,
			'currency' => 'USD',
			'salary_duration' => 'Monthly',
		]);

		$jobCId = $this->createActiveJob($employerId, $companyId, $categoryId, self::UNIQUE_KEYWORD . ' High GBP', [
			'salary_amount' => 5000.00,
			'currency' => 'GBP',
			'salary_duration' => 'Monthly',
		]);

		// Filter by range: BETWEEN 1000 AND 3000 (should only match Job B: 2000.00)
		$rangeResponse = $this->getJson(
			'/api/jobs/all_jobs.php?keyword=' . self::UNIQUE_KEYWORD . '&min_salary=1000&max_salary=3000'
		);

		$this->assertSame(200, $rangeResponse['statusCode']);
		$this->assertCount(1, $rangeResponse['body']['jobs']);
		$this->assertSame($jobBId, (int) $rangeResponse['body']['jobs'][0]['job_id']);

		// Filter by currency: USD (should match Job A and Job B, excluding Job C in GBP)
		$currResponse = $this->getJson(
			'/api/jobs/all_jobs.php?keyword=' . self::UNIQUE_KEYWORD . '&currency=USD'
		);

		$this->assertSame(200, $currResponse['statusCode']);
		$this->assertCount(2, $currResponse['body']['jobs']);
		$currJobIds = array_map(fn($job) => (int) $job['job_id'], $currResponse['body']['jobs']);
		$this->assertContains($jobAId, $currJobIds);
		$this->assertContains($jobBId, $currJobIds);
		$this->assertNotContains($jobCId, $currJobIds);
	}

	public function test_saved_jobs_salary_sorting_normalizes_currencies(): void
	{
		$employerId = $this->createEmployer(self::EMPLOYER_EMAIL);
		$companyId = $this->createCompany($employerId, 'Salary Corp');
		$this->linkCompanyToEmployer($employerId, $companyId);
		$categoryId = $this->createCategory(self::CATEGORY_NAME);

		// Job A ($1,000 USD eq), Job B ($2,000 USD eq), Job C ($3,000 USD eq)
		$jobAId = $this->createActiveJob($employerId, $companyId, $categoryId, self::UNIQUE_KEYWORD . ' Saved NGN', [
			'salary_amount' => 1331013.80,
			'currency' => 'NGN',
			'salary_duration' => 'Monthly',
		]);

		$jobBId = $this->createActiveJob($employerId, $companyId, $categoryId, self::UNIQUE_KEYWORD . ' Saved USD', [
			'salary_amount' => 2000.00,
			'currency' => 'USD',
			'salary_duration' => 'Monthly',
		]);

		$jobCId = $this->createActiveJob($employerId, $companyId, $categoryId, self::UNIQUE_KEYWORD . ' Saved GBP', [
			'salary_amount' => 2269.50,
			'currency' => 'GBP',
			'salary_duration' => 'Monthly',
		]);

		// Seeker saves all 3 jobs
		$seekerId = $this->createJobSeeker(self::SEEKER_EMAIL);
		$token = $this->loginAndGetToken(self::SEEKER_EMAIL);

		$this->postJson('/api/jobs/wishlist.php', ['jobId' => $jobAId], $token);
		$this->postJson('/api/jobs/wishlist.php', ['jobId' => $jobBId], $token);
		$this->postJson('/api/jobs/wishlist.php', ['jobId' => $jobCId], $token);

		// Request saved jobs sorted by salary-high (highest normalized first: Job C, Job B, Job A)
		$responseHigh = $this->getJson('/api/dashboard/seeker/saved_jobs.php?sort=salary-high', $token);
		$this->assertSame(200, $responseHigh['statusCode']);
		$highJobIds = array_map(fn($job) => (int) $job['job_id'], $responseHigh['body']['savedJobs']);
		$this->assertSame([$jobCId, $jobBId, $jobAId], $highJobIds);

		// Request saved jobs sorted by salary-low (lowest normalized first: Job A, Job B, Job C)
		$responseLow = $this->getJson('/api/dashboard/seeker/saved_jobs.php?sort=salary-low', $token);
		$this->assertSame(200, $responseLow['statusCode']);
		$lowJobIds = array_map(fn($job) => (int) $job['job_id'], $responseLow['body']['savedJobs']);
		$this->assertSame([$jobAId, $jobBId, $jobCId], $lowJobIds);
	}

	public function test_salary_amount_boundary_validation_on_job_creation(): void
	{
		$employerId = $this->createEmployer(self::EMPLOYER_EMAIL);
		$companyId = $this->createCompany($employerId, 'Salary Corp');
		$this->linkCompanyToEmployer($employerId, $companyId);
		$categoryId = $this->createCategory(self::CATEGORY_NAME);

		$token = $this->loginAndGetToken(self::EMPLOYER_EMAIL);

		$baseJobPayload = [
			'title' => 'Software Engineer',
			'category_id' => $categoryId,
			'employment_type' => 'fulltime',
			'location' => 'Lagos',
			'description' => 'Job description',
			'currency' => 'USD',
			'salary_duration' => 'Monthly',
			'experience_level' => 'Mid',
			'overview' => 'Job overview',
			'responsibilities' => 'Responsibilities',
			'requirements' => 'Requirements',
		];

		// Boundary 1: salary_amount = 0 (must be > 0)
		$zeroPayload = array_merge($baseJobPayload, ['salary_amount' => 0]);
		$zeroResponse = $this->postJson('/api/dashboard/employer/post_job.php', $zeroPayload, $token);
		$this->assertSame(400, $zeroResponse['statusCode']);
		$this->assertFalse($zeroResponse['body']['status']);
		$this->assertArrayHasKey('errors', $zeroResponse['body']);
		$this->assertStringContainsString('Salary amount must be greater than 0', json_encode($zeroResponse['body']['errors']));

		// Boundary 2: salary_amount = -500 (negative number)
		$negPayload = array_merge($baseJobPayload, ['salary_amount' => -500]);
		$negResponse = $this->postJson('/api/dashboard/employer/post_job.php', $negPayload, $token);
		$this->assertSame(400, $negResponse['statusCode']);
		$this->assertStringContainsString('Salary amount must be greater than 0', json_encode($negResponse['body']['errors']));

		// Boundary 3: salary_amount = "abc" (non-numeric string)
		$alphaPayload = array_merge($baseJobPayload, ['salary_amount' => 'abc']);
		$alphaResponse = $this->postJson('/api/dashboard/employer/post_job.php', $alphaPayload, $token);
		$this->assertSame(400, $alphaResponse['statusCode']);
		$this->assertStringContainsString('Salary amount must be greater than 0', json_encode($alphaResponse['body']['errors']));

		// Boundary 4: salary_amount = null (missing/empty)
		$nullPayload = array_merge($baseJobPayload, ['salary_amount' => null]);
		$nullResponse = $this->postJson('/api/dashboard/employer/post_job.php', $nullPayload, $token);
		$this->assertSame(400, $nullResponse['statusCode']);

		// Boundary 5: salary_amount = 0.01 (smallest positive decimal) -> valid
		$validPayload = array_merge($baseJobPayload, ['salary_amount' => 0.01]);
		$validResponse = $this->postJson('/api/dashboard/employer/post_job.php', $validPayload, $token);
		$this->assertSame(201, $validResponse['statusCode']);
		$this->assertTrue($validResponse['body']['status']);
	}

	private function deleteTestData(): void
	{
		$this->cleanupUsersByEmails([
			self::EMPLOYER_EMAIL,
			self::SEEKER_EMAIL,
		]);
		$this->cleanupCategoriesByNames([self::CATEGORY_NAME]);
	}
}
