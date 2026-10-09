<?php

declare(strict_types=1);

final class JobCreationTest extends BaseTestCase
{
	private const TEST_EMAIL = 'test.job.creation@jobnet.test';
	private const TEST_CATEGORY = 'Test Job Creation Category';

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

	public function test_authenticated_employer_can_create_job(): void
	{
		$userId = $this->createEmployer(self::TEST_EMAIL);
		$companyId = $this->createCompany(
			$userId,
			'Test Company',
			'Lagos',
			'https://example.test',
			'A test company for job creation integration coverage.'
		);
		$this->linkCompanyToEmployer($userId, $companyId);
		$categoryId = $this->createCategory(self::TEST_CATEGORY);

		$token = $this->loginAndGetToken(self::TEST_EMAIL);

		$jobTitle = 'Test PHP Backend Developer';
		$jobPayload = [
			'title' => $jobTitle,
			'category_id' => $categoryId,
			'employment_type' => 'fulltime',
			'location' => 'Lagos',
			'salary_amount' => 250000,
			'currency' => 'NGN',
			'salary_duration' => 'Monthly',
			'experience_level' => 'Mid',
			'english_fluency' => 'Fluent',
			'overview' => 'Build and maintain reliable backend services for JobNet.',
			'description' => 'Develop and maintain PHP APIs, database integrations, and automated tests.',
			'responsibilities' => 'Implement API features and review code changes.',
			'requirements' => 'Experience with PHP, MySQL, and HTTP APIs.',
			'nice_to_have' => '',
			'benefits' => '',
		];

		$response = $this->postJson(
			'/api/dashboard/employer/post_job.php',
			$jobPayload,
			$token
		);

		$this->assertSame(201, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertArrayHasKey('job_id', $response['body']);
		$jobId = (int) $response['body']['job_id'];
		$this->assertGreaterThan(0, $jobId);

		$jobStatement = $this->db->prepare(
			'SELECT job_id, title, employer_id, company_id, status
			 FROM jobs_table
			 WHERE job_id = ?'
		);
		$jobStatement->bind_param('i', $jobId);
		$jobStatement->execute();
		$job = $jobStatement->get_result()->fetch_assoc();
		$jobStatement->close();

		$this->assertIsArray($job);
		$this->assertSame($jobId, (int) $job['job_id']);
		$this->assertSame($jobTitle, $job['title']);
		$this->assertSame($userId, (int) $job['employer_id']);
		$this->assertSame($companyId, (int) $job['company_id']);
		$this->assertSame('pending', $job['status']);
	}

	private function deleteTestData(): void
	{
		$this->cleanupUsersByEmails([self::TEST_EMAIL]);
		$this->cleanupCategoriesByNames([self::TEST_CATEGORY]);
	}
}