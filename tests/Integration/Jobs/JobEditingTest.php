<?php

declare(strict_types=1);

final class JobEditingTest extends BaseTestCase
{
	private const TEST_EMAIL = 'test.job.editing@jobnet.test';
	private const TEST_CATEGORY = 'Test Job Editing Category';

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

	public function test_authenticated_employer_can_edit_owned_job(): void
	{
		$userId = $this->createEmployer(self::TEST_EMAIL);
		$companyId = $this->createCompany($userId);
		$this->linkCompanyToEmployer($userId, $companyId);
		$categoryId = $this->createCategory(self::TEST_CATEGORY);
		$jobId = $this->createActiveJob($userId, $companyId, $categoryId);

		$token = $this->loginAndGetToken(self::TEST_EMAIL);

		$updatedTitle = 'Updated PHP Backend Developer';
		$updatedLocation = 'Abuja';
		$response = $this->postJson(
			'/api/dashboard/employer/update_job.php',
			[
				'job_id' => $jobId,
				'title' => $updatedTitle,
				'category_id' => $categoryId,
				'employment_type' => 'fulltime',
				'location' => $updatedLocation,
				'salary_amount' => 300000,
				'currency' => 'NGN',
				'salary_duration' => 'Monthly',
				'experience_level' => 'Senior',
				'english_fluency' => 'Fluent',
				'overview' => 'Updated overview for the backend developer role.',
				'description' => 'Updated description for the PHP backend developer position.',
				'responsibilities' => 'Lead backend implementation and review code changes.',
				'requirements' => 'Strong PHP, MySQL, and API development experience.',
				'nice_to_have' => '',
				'benefits' => '',
				'deadline' => date('Y-m-d', strtotime('+30 days')),
			],
			$token
		);

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);

		$jobStatement = $this->db->prepare(
			'SELECT job_id, title, location, employer_id, company_id
			 FROM jobs_table
			 WHERE job_id = ?'
		);
		$jobStatement->bind_param('i', $jobId);
		$jobStatement->execute();
		$job = $jobStatement->get_result()->fetch_assoc();
		$jobStatement->close();

		$this->assertIsArray($job);
		$this->assertSame($jobId, (int) $job['job_id']);
		$this->assertSame($updatedTitle, $job['title']);
		$this->assertSame($updatedLocation, $job['location']);
		$this->assertSame($userId, (int) $job['employer_id']);
		$this->assertSame($companyId, (int) $job['company_id']);
	}

	private function deleteTestData(): void
	{
		$this->cleanupUsersByEmails([self::TEST_EMAIL]);
		$this->cleanupCategoriesByNames([self::TEST_CATEGORY]);
	}
}
