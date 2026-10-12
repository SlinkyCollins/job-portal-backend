<?php

declare(strict_types=1);

final class SavedJobsTest extends BaseTestCase
{
	private const SEEKER_1_EMAIL = 'test.saved.seeker1@jobnet.test';
	private const SEEKER_2_EMAIL = 'test.saved.seeker2@jobnet.test';
	private const EMPLOYER_EMAIL = 'test.saved.employer@jobnet.test';
	private const CATEGORY_NAME = 'Test Saved Jobs Category';

	protected function setUp(): void
	{
		parent::setUp();
		$this->deleteTestData();
		// Ensure exchange rates cache is fresh to avoid external HTTP requests in saved_jobs.php
		$this->db->query('UPDATE exchange_rates SET last_updated = NOW()');
	}

	protected function tearDown(): void
	{
		$this->deleteTestData();
		parent::tearDown();
	}

	public function test_seeker_can_save_job_and_persists_in_database(): void
	{
		$employerId = $this->createEmployer(self::EMPLOYER_EMAIL);
		$companyId = $this->createCompany($employerId, 'Saved Job Co');
		$this->linkCompanyToEmployer($employerId, $companyId);
		$categoryId = $this->createCategory(self::CATEGORY_NAME);
		$jobId = $this->createActiveJob($employerId, $companyId, $categoryId, 'Senior PHP Developer');

		$seekerId = $this->createJobSeeker(self::SEEKER_1_EMAIL);
		$token = $this->loginAndGetToken(self::SEEKER_1_EMAIL);

		// Save the job
		$response = $this->postJson('/api/jobs/wishlist.php', [
			'jobId' => $jobId,
		], $token);

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertSame('Added to Wishlist!', $response['body']['message']);
		$this->assertTrue($response['body']['isSaved']);

		// Verify record exists in saved_jobs_table
		$stmt = $this->db->prepare('SELECT id FROM saved_jobs_table WHERE user_id = ? AND job_id = ?');
		$stmt->bind_param('ii', $seekerId, $jobId);
		$stmt->execute();
		$result = $stmt->get_result();
		$this->assertSame(1, $result->num_rows);
		$stmt->close();

		// Duplicate save attempt should be rejected with 400
		$dupResponse = $this->postJson('/api/jobs/wishlist.php', [
			'jobId' => $jobId,
		], $token);

		$this->assertSame(400, $dupResponse['statusCode']);
		$this->assertFalse($dupResponse['body']['status']);
		$this->assertSame('You have already saved this job.', $dupResponse['body']['message']);
	}

	public function test_seeker_can_unsave_job_and_removes_from_database(): void
	{
		$employerId = $this->createEmployer(self::EMPLOYER_EMAIL);
		$companyId = $this->createCompany($employerId, 'Saved Job Co');
		$this->linkCompanyToEmployer($employerId, $companyId);
		$categoryId = $this->createCategory(self::CATEGORY_NAME);
		$jobId = $this->createActiveJob($employerId, $companyId, $categoryId, 'Senior PHP Developer');

		$seekerId = $this->createJobSeeker(self::SEEKER_1_EMAIL);
		$token = $this->loginAndGetToken(self::SEEKER_1_EMAIL);

		// Save job first
		$saveResponse = $this->postJson('/api/jobs/wishlist.php', [
			'jobId' => $jobId,
		], $token);
		$this->assertSame(200, $saveResponse['statusCode']);

		// Unsave the job via DELETE request
		$unsaveResponse = $this->deleteJson('/api/jobs/wishlist_delete.php', [
			'jobId' => $jobId,
		], $token);

		$this->assertSame(200, $unsaveResponse['statusCode']);
		$this->assertTrue($unsaveResponse['body']['status']);
		$this->assertSame('Removed from Wishlist!', $unsaveResponse['body']['message']);
		$this->assertFalse($unsaveResponse['body']['isSaved']);

		// Verify record was deleted from saved_jobs_table
		$stmt = $this->db->prepare('SELECT id FROM saved_jobs_table WHERE user_id = ? AND job_id = ?');
		$stmt->bind_param('ii', $seekerId, $jobId);
		$stmt->execute();
		$result = $stmt->get_result();
		$this->assertSame(0, $result->num_rows);
		$stmt->close();
	}

	public function test_saved_jobs_are_retrieved_correctly_with_expected_structure(): void
	{
		$employerId = $this->createEmployer(self::EMPLOYER_EMAIL);
		$companyId = $this->createCompany($employerId, 'Saved Job Co');
		$this->linkCompanyToEmployer($employerId, $companyId);
		$categoryId = $this->createCategory(self::CATEGORY_NAME);
		$jobId = $this->createActiveJob($employerId, $companyId, $categoryId, 'Lead Backend Architect', [
			'salary_amount' => 450000.0,
			'currency' => 'NGN',
			'location' => 'Abuja',
		]);

		$seekerId = $this->createJobSeeker(self::SEEKER_1_EMAIL);
		$token = $this->loginAndGetToken(self::SEEKER_1_EMAIL);

		// Direct DB insert or API save
		$saveResponse = $this->postJson('/api/jobs/wishlist.php', [
			'jobId' => $jobId,
		], $token);
		$this->assertSame(200, $saveResponse['statusCode']);

		// Retrieve saved jobs
		$response = $this->getJson('/api/dashboard/seeker/saved_jobs.php', $token);

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertSame('Saved jobs retrieved successfully.', $response['body']['message']);
		$this->assertArrayHasKey('savedJobs', $response['body']);
		$this->assertArrayHasKey('pagination', $response['body']);

		$savedJobs = $response['body']['savedJobs'];
		$this->assertCount(1, $savedJobs);

		$job = $savedJobs[0];
		$this->assertSame($jobId, (int) $job['job_id']);
		$this->assertSame('Lead Backend Architect', $job['title']);
		$this->assertSame('Saved Job Co', $job['company_name']);
		$this->assertSame(self::CATEGORY_NAME, $job['category_name']);
		$this->assertSame('Abuja', $job['location']);
		$this->assertArrayHasKey('salary_amount', $job);
		$this->assertArrayHasKey('tags', $job);

		$pagination = $response['body']['pagination'];
		$this->assertSame(1, (int) $pagination['total_jobs']);
		$this->assertSame(1, (int) $pagination['current_page']);
	}

	public function test_one_users_saved_jobs_are_not_exposed_to_another_user(): void
	{
		$employerId = $this->createEmployer(self::EMPLOYER_EMAIL);
		$companyId = $this->createCompany($employerId, 'Saved Job Co');
		$this->linkCompanyToEmployer($employerId, $companyId);
		$categoryId = $this->createCategory(self::CATEGORY_NAME);

		$job1Id = $this->createActiveJob($employerId, $companyId, $categoryId, 'Job For Seeker 1');
		$job2Id = $this->createActiveJob($employerId, $companyId, $categoryId, 'Job For Seeker 2');

		$seeker1Id = $this->createJobSeeker(self::SEEKER_1_EMAIL);
		$token1 = $this->loginAndGetToken(self::SEEKER_1_EMAIL);

		$seeker2Id = $this->createJobSeeker(self::SEEKER_2_EMAIL);
		$token2 = $this->loginAndGetToken(self::SEEKER_2_EMAIL);

		// Seeker 1 saves Job 1
		$this->postJson('/api/jobs/wishlist.php', ['jobId' => $job1Id], $token1);

		// Seeker 2 saves Job 2
		$this->postJson('/api/jobs/wishlist.php', ['jobId' => $job2Id], $token2);

		// Verify Seeker 1 only sees Job 1
		$response1 = $this->getJson('/api/dashboard/seeker/saved_jobs.php', $token1);
		$this->assertSame(200, $response1['statusCode']);
		$this->assertCount(1, $response1['body']['savedJobs']);
		$this->assertSame($job1Id, (int) $response1['body']['savedJobs'][0]['job_id']);
		$this->assertSame(1, (int) $response1['body']['pagination']['total_jobs']);

		// Verify Seeker 2 only sees Job 2
		$response2 = $this->getJson('/api/dashboard/seeker/saved_jobs.php', $token2);
		$this->assertSame(200, $response2['statusCode']);
		$this->assertCount(1, $response2['body']['savedJobs']);
		$this->assertSame($job2Id, (int) $response2['body']['savedJobs'][0]['job_id']);
		$this->assertSame(1, (int) $response2['body']['pagination']['total_jobs']);

		// Cross-user delete attempt: Seeker 1 tries to unsave Job 2 (saved by Seeker 2)
		$unsaveAttempt = $this->deleteJson('/api/jobs/wishlist_delete.php', ['jobId' => $job2Id], $token1);
		$this->assertSame(200, $unsaveAttempt['statusCode']);

		// Verify Job 2 is still saved for Seeker 2 in DB
		$stmt = $this->db->prepare('SELECT id FROM saved_jobs_table WHERE user_id = ? AND job_id = ?');
		$stmt->bind_param('ii', $seeker2Id, $job2Id);
		$stmt->execute();
		$result = $stmt->get_result();
		$this->assertSame(1, $result->num_rows, 'Job 2 must remain saved for Seeker 2 after another user unsaves it.');
		$stmt->close();
	}

	public function test_unauthenticated_and_unauthorized_access_are_rejected(): void
	{
		// Unauthenticated GET saved jobs -> 401
		$unauthGet = $this->getJson('/api/dashboard/seeker/saved_jobs.php');
		$this->assertSame(401, $unauthGet['statusCode']);

		// Unauthenticated POST wishlist -> 401
		$unauthPost = $this->postJson('/api/jobs/wishlist.php', ['jobId' => 999]);
		$this->assertSame(401, $unauthPost['statusCode']);

		// Unauthenticated DELETE wishlist_delete -> 401
		$unauthDelete = $this->deleteJson('/api/jobs/wishlist_delete.php', ['jobId' => 999]);
		$this->assertSame(401, $unauthDelete['statusCode']);

		// Missing jobId parameter -> 400
		$this->createJobSeeker(self::SEEKER_1_EMAIL);
		$seekerToken = $this->loginAndGetToken(self::SEEKER_1_EMAIL);

		$missingJobIdPost = $this->postJson('/api/jobs/wishlist.php', [], $seekerToken);
		$this->assertSame(400, $missingJobIdPost['statusCode']);
		$this->assertSame('Job ID is required.', $missingJobIdPost['body']['message']);

		$missingJobIdDelete = $this->deleteJson('/api/jobs/wishlist_delete.php', [], $seekerToken);
		$this->assertSame(400, $missingJobIdDelete['statusCode']);
		$this->assertSame('Job ID is required.', $missingJobIdDelete['body']['message']);

		// Employer token attempting to access seeker saved jobs / wishlist -> 403
		$this->createEmployer(self::EMPLOYER_EMAIL);
		$employerToken = $this->loginAndGetToken(self::EMPLOYER_EMAIL);

		$employerGet = $this->getJson('/api/dashboard/seeker/saved_jobs.php', $employerToken);
		$this->assertSame(403, $employerGet['statusCode']);

		$employerPost = $this->postJson('/api/jobs/wishlist.php', ['jobId' => 999], $employerToken);
		$this->assertSame(403, $employerPost['statusCode']);
	}

	private function deleteTestData(): void
	{
		$this->cleanupUsersByEmails([
			self::SEEKER_1_EMAIL,
			self::SEEKER_2_EMAIL,
			self::EMPLOYER_EMAIL,
		]);
		$this->cleanupCategoriesByNames([self::CATEGORY_NAME]);
	}
}
