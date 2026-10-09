<?php

declare(strict_types=1);

final class ApplicationOwnershipTest extends BaseTestCase
{
	private const EMPLOYER_ONE_EMAIL = 'test.employer1.ownership@jobnet.test';
	private const EMPLOYER_TWO_EMAIL = 'test.employer2.ownership@jobnet.test';
	private const SEEKER_EMAIL = 'test.seeker.ownership@jobnet.test';
	private const SEEKER_TWO_EMAIL = 'test.seeker2.ownership@jobnet.test';
	private const TEST_CATEGORY = 'Test Ownership Category';

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

	public function test_employer_can_only_view_applications_for_own_jobs(): void
	{
		// Setup Employer 1 with a company and a job
		$employer1Id = $this->createEmployer(self::EMPLOYER_ONE_EMAIL, 'Employer', 'One');
		$company1Id = $this->createCompany($employer1Id, 'Company One');
		$this->linkCompanyToEmployer($employer1Id, $company1Id);
		$categoryId = $this->createCategory(self::TEST_CATEGORY);
		$job1Id = $this->createActiveJob($employer1Id, $company1Id, $categoryId, 'Employer 1 Job');

		// Setup Employer 2 with a company and a job
		$employer2Id = $this->createEmployer(self::EMPLOYER_TWO_EMAIL, 'Employer', 'Two');
		$company2Id = $this->createCompany($employer2Id, 'Company Two');
		$this->linkCompanyToEmployer($employer2Id, $company2Id);
		$job2Id = $this->createActiveJob($employer2Id, $company2Id, $categoryId, 'Employer 2 Job');

		// Setup Job Seekers
		$seekerId = $this->createJobSeeker(self::SEEKER_EMAIL);
		$seeker2Id = $this->createJobSeeker(self::SEEKER_TWO_EMAIL, 'Second', 'Applicant');

		// Create applications:
		// App 1: Seeker 1 -> Job 1 (pending)
		$app1Id = $this->createApplication($job1Id, $seekerId, 'pending', 'Interested in Job 1');
		// App 2: Seeker 1 -> Job 2 (pending)
		$app2Id = $this->createApplication($job2Id, $seekerId, 'pending', 'Interested in Job 2');
		// App 3: Seeker 2 -> Job 1 (retracted - should be excluded by get_applications.php)
		$app3Id = $this->createApplication($job1Id, $seeker2Id, 'retracted', 'Retracted application');

		// Login as Employer 1
		$employer1Token = $this->loginAndGetToken(self::EMPLOYER_ONE_EMAIL);

		// Request applications as Employer 1
		$response1 = $this->getJson('/api/dashboard/employer/get_applications.php', $employer1Token);

		$this->assertSame(200, $response1['statusCode']);
		$this->assertTrue($response1['body']['status']);
		$this->assertSame('Applications retrieved successfully.', $response1['body']['message']);
		$this->assertArrayHasKey('data', $response1['body']);

		$applications1 = $response1['body']['data'];
		$app1Ids = array_map(static fn(array $item): int => (int) $item['application_id'], $applications1);

		// Employer 1 should see App 1, but NOT App 2 (owned by Employer 2) and NOT App 3 (retracted)
		$this->assertContains($app1Id, $app1Ids);
		$this->assertNotContains($app2Id, $app1Ids);
		$this->assertNotContains($app3Id, $app1Ids);

		// Verify fields on Employer 1's application record
		$firstApp = null;
		foreach ($applications1 as $app) {
			if ((int) $app['application_id'] === $app1Id) {
				$firstApp = $app;
				break;
			}
		}
		$this->assertNotNull($firstApp);
		$this->assertSame('Employer 1 Job', $firstApp['job_title']);
		$this->assertSame('Test', $firstApp['firstname']);
		$this->assertSame('Applicant', $firstApp['lastname']);
		$this->assertSame(self::SEEKER_EMAIL, $firstApp['email']);
		$this->assertSame('pending', $firstApp['status']);
		$this->assertSame('Interested in Job 1', $firstApp['cover_letter']);

		// Login as Employer 2
		$employer2Token = $this->loginAndGetToken(self::EMPLOYER_TWO_EMAIL);

		// Request applications as Employer 2
		$response2 = $this->getJson('/api/dashboard/employer/get_applications.php', $employer2Token);

		$this->assertSame(200, $response2['statusCode']);
		$this->assertTrue($response2['body']['status']);
		$applications2 = $response2['body']['data'];
		$app2Ids = array_map(static fn(array $item): int => (int) $item['application_id'], $applications2);

		// Employer 2 should see App 2, but NOT App 1 (owned by Employer 1) and NOT App 3
		$this->assertContains($app2Id, $app2Ids);
		$this->assertNotContains($app1Id, $app2Ids);
		$this->assertNotContains($app3Id, $app2Ids);
	}

	public function test_employer_cannot_update_application_belonging_to_another_employer(): void
	{
		// Setup Employer 1 with a job and an application
		$employer1Id = $this->createEmployer(self::EMPLOYER_ONE_EMAIL, 'Employer', 'One');
		$company1Id = $this->createCompany($employer1Id, 'Company One');
		$this->linkCompanyToEmployer($employer1Id, $company1Id);
		$categoryId = $this->createCategory(self::TEST_CATEGORY);
		$job1Id = $this->createActiveJob($employer1Id, $company1Id, $categoryId, 'Employer 1 Job');

		// Setup Employer 2 with a company
		$employer2Id = $this->createEmployer(self::EMPLOYER_TWO_EMAIL, 'Employer', 'Two');
		$company2Id = $this->createCompany($employer2Id, 'Company Two');
		$this->linkCompanyToEmployer($employer2Id, $company2Id);

		// Setup Job Seeker and Application to Job 1
		$seekerId = $this->createJobSeeker(self::SEEKER_EMAIL);
		$app1Id = $this->createApplication($job1Id, $seekerId, 'pending', 'Interested in Job 1');

		// Login as Employer 2 (unauthorized to manage Job 1's applications)
		$employer2Token = $this->loginAndGetToken(self::EMPLOYER_TWO_EMAIL);

		// Attempt to update Application 1 status as Employer 2
		$response = $this->postJson(
			'/api/dashboard/employer/update_application_status.php',
			[
				'application_id' => $app1Id,
				'status' => 'shortlisted',
			],
			$employer2Token
		);

		// Must return 403 Forbidden with access denied message
		$this->assertSame(403, $response['statusCode']);
		$this->assertFalse($response['body']['status']);
		$this->assertSame('Update failed. Application not found or access denied.', $response['body']['message']);

		// Verify database state was NOT changed
		$statement = $this->db->prepare('SELECT status FROM applications_table WHERE application_id = ?');
		$statement->bind_param('i', $app1Id);
		$statement->execute();
		$row = $statement->get_result()->fetch_assoc();
		$statement->close();

		$this->assertSame('pending', $row['status']);
	}

	public function test_employer_can_update_status_of_owned_job_application(): void
	{
		// Setup Employer 1 with a job and an application
		$employer1Id = $this->createEmployer(self::EMPLOYER_ONE_EMAIL, 'Employer', 'One');
		$company1Id = $this->createCompany($employer1Id, 'Company One');
		$this->linkCompanyToEmployer($employer1Id, $company1Id);
		$categoryId = $this->createCategory(self::TEST_CATEGORY);
		$job1Id = $this->createActiveJob($employer1Id, $company1Id, $categoryId, 'Employer 1 Job');

		$seekerId = $this->createJobSeeker(self::SEEKER_EMAIL);
		$app1Id = $this->createApplication($job1Id, $seekerId, 'pending', 'Interested in Job 1');

		// Login as Employer 1 (authorized owner)
		$employer1Token = $this->loginAndGetToken(self::EMPLOYER_ONE_EMAIL);

		// Update Application 1 status to 'shortlisted'
		$response = $this->postJson(
			'/api/dashboard/employer/update_application_status.php',
			[
				'application_id' => $app1Id,
				'status' => 'shortlisted',
			],
			$employer1Token
		);

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertSame('Status updated successfully.', $response['body']['message']);

		// Verify database state reflects the update
		$statement = $this->db->prepare('SELECT status FROM applications_table WHERE application_id = ?');
		$statement->bind_param('i', $app1Id);
		$statement->execute();
		$row = $statement->get_result()->fetch_assoc();
		$statement->close();

		$this->assertSame('shortlisted', $row['status']);
	}

	public function test_non_employer_cannot_access_employer_applications(): void
	{
		// Setup a Job Seeker
		$seekerId = $this->createJobSeeker(self::SEEKER_EMAIL);
		$seekerToken = $this->loginAndGetToken(self::SEEKER_EMAIL);

		// Job seeker attempts to access employer get_applications endpoint -> 403 Forbidden
		$forbiddenResponse = $this->getJson('/api/dashboard/employer/get_applications.php', $seekerToken);
		$this->assertSame(403, $forbiddenResponse['statusCode']);
		$this->assertFalse($forbiddenResponse['body']['status']);

		// Unauthenticated request to get_applications -> 401 Unauthorized
		$unauthenticatedResponse = $this->getJson('/api/dashboard/employer/get_applications.php');
		$this->assertSame(401, $unauthenticatedResponse['statusCode']);
		$this->assertFalse($unauthenticatedResponse['body']['status']);
	}

	private function deleteTestData(): void
	{
		$this->cleanupUsersByEmails([
			self::EMPLOYER_ONE_EMAIL,
			self::EMPLOYER_TWO_EMAIL,
			self::SEEKER_EMAIL,
			self::SEEKER_TWO_EMAIL,
		]);

		$this->cleanupCategoriesByNames([self::TEST_CATEGORY]);
	}
}
