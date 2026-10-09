<?php

declare(strict_types=1);

final class JobApplicationTest extends BaseTestCase
{
	private const TEST_EMAIL = 'test.job.application@jobnet.test';
	private const EMPLOYER_EMAIL = 'test.job.application.employer@jobnet.test';
	private const TEST_CATEGORY = 'Test Job Application Category';

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

	public function test_authenticated_seeker_can_apply_to_active_job(): void
	{
		$seekerUserId = $this->createJobSeeker(self::TEST_EMAIL);
		$this->setSeekerDefaultCv($seekerUserId);
		$employerUserId = $this->createEmployer(self::EMPLOYER_EMAIL);
		$companyId = $this->createCompany($employerUserId, 'Test Application Company');
		$this->linkCompanyToEmployer($employerUserId, $companyId);
		$categoryId = $this->createCategory(self::TEST_CATEGORY);
		$jobId = $this->createActiveJob(
			$employerUserId,
			$companyId,
			$categoryId,
			'Test Application Developer',
			[
				'overview' => 'Application role for backend integration testing.',
				'description' => 'An active job used to protect the application contract.',
			]
		);

		$token = $this->loginAndGetToken(self::TEST_EMAIL);

		$response = $this->postForm(
			'/api/jobs/apply.php',
			['jobId' => $jobId, 'cover_letter' => 'I am very interested in this role.'],
			$token
		);

		$this->assertSame(201, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertSame('Application submitted successfully!', $response['body']['message']);
		$this->assertTrue($response['body']['hasApplied']);

		// Verify the application record exists in the database with the correct associations
		$statement = $this->db->prepare(
			'SELECT application_id, job_id, seeker_id, status, cover_letter
			 FROM applications_table
			 WHERE job_id = ? AND seeker_id = ?'
		);
		$statement->bind_param('ii', $jobId, $seekerUserId);
		$statement->execute();
		$application = $statement->get_result()->fetch_assoc();
		$statement->close();

		$this->assertIsArray($application);
		$this->assertSame($jobId, (int) $application['job_id']);
		$this->assertSame($seekerUserId, (int) $application['seeker_id']);
		$this->assertSame('pending', $application['status']);
		$this->assertSame('I am very interested in this role.', $application['cover_letter']);
	}

	public function test_duplicate_application_is_rejected(): void
	{
		$seekerUserId = $this->createJobSeeker(self::TEST_EMAIL);
		$this->setSeekerDefaultCv($seekerUserId);
		$employerUserId = $this->createEmployer(self::EMPLOYER_EMAIL);
		$companyId = $this->createCompany($employerUserId, 'Test Application Company');
		$this->linkCompanyToEmployer($employerUserId, $companyId);
		$categoryId = $this->createCategory(self::TEST_CATEGORY);
		$jobId = $this->createActiveJob($employerUserId, $companyId, $categoryId);

		$token = $this->loginAndGetToken(self::TEST_EMAIL);

		// First application succeeds
		$first = $this->postForm(
			'/api/jobs/apply.php',
			['jobId' => $jobId],
			$token
		);
		$this->assertSame(201, $first['statusCode']);

		// Second application is rejected as duplicate
		$second = $this->postForm(
			'/api/jobs/apply.php',
			['jobId' => $jobId],
			$token
		);

		$this->assertSame(400, $second['statusCode']);
		$this->assertFalse($second['body']['status']);
		$this->assertSame('You have already applied for this job.', $second['body']['message']);
		$this->assertTrue($second['body']['hasApplied']);
	}

	public function test_unauthenticated_request_is_rejected(): void
	{
		$response = $this->postForm(
			'/api/jobs/apply.php',
			['jobId' => 1]
		);

		$this->assertSame(401, $response['statusCode']);
		$this->assertFalse($response['body']['status']);
	}

	private function deleteTestData(): void
	{
		$this->cleanupUsersByEmails([self::TEST_EMAIL, self::EMPLOYER_EMAIL]);
		$this->cleanupCategoriesByNames([self::TEST_CATEGORY]);
	}
}
