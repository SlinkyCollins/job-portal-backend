<?php

declare(strict_types=1);

final class UserDeletionTest extends BaseTestCase
{
	private const SEEKER_EMAIL = 'test.seeker.delete@jobnet.test';
	private const SEEKER_TWO_EMAIL = 'test.seeker2.delete@jobnet.test';
	private const EMPLOYER_EMAIL = 'test.employer.delete@jobnet.test';
	private const ADMIN_EMAIL = 'test.admin.delete@jobnet.test';
	private const ADMIN_TWO_EMAIL = 'test.admin2.delete@jobnet.test';
	private const TARGET_EMAIL = 'test.target.delete@jobnet.test';
	private const TEST_CATEGORY = 'Test Deletion Category';

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

	public function test_seeker_can_delete_account_and_cascades_all_associated_records(): void
	{
		// Setup employer, company, category, and an active job
		$employerId = $this->createEmployer(self::EMPLOYER_EMAIL);
		$companyId = $this->createCompany($employerId);
		$this->linkCompanyToEmployer($employerId, $companyId);
		$categoryId = $this->createCategory(self::TEST_CATEGORY);
		$jobId = $this->createActiveJob($employerId, $companyId, $categoryId);

		// Setup seeker
		$seekerId = $this->createJobSeeker(self::SEEKER_EMAIL);

		// Seeker applies to the job
		$applicationId = $this->createApplication($jobId, $seekerId, 'pending', 'Interested in role');

		// Seeker saves the job
		$saveStmt = $this->db->prepare('INSERT INTO saved_jobs_table (job_id, user_id) VALUES (?, ?)');
		$saveStmt->bind_param('ii', $jobId, $seekerId);
		$saveStmt->execute();
		$saveStmt->close();

		// Seeker uploads a CV and profile pic references
		$updateSeeker = $this->db->prepare(
			'UPDATE job_seekers_table
			 SET cv_url = ?, cv_filename = ?, cv_public_id = ?, profile_pic_url = ?, profile_pic_public_id = ?
			 WHERE user_id = ?'
		);
		$cvUrl = 'https://res.cloudinary.com/jobnet-mock/raw/upload/fl_attachment/jobnet/cvs/cv_1.pdf';
		$cvFilename = 'Resume.pdf';
		$cvPublicId = 'jobnet/cvs/cv_1.pdf';
		$picUrl = 'https://res.cloudinary.com/jobnet-mock/image/upload/jobnet/profiles/pic_1.jpg';
		$picPublicId = 'jobnet/profiles/pic_1.jpg';
		$updateSeeker->bind_param('sssssi', $cvUrl, $cvFilename, $cvPublicId, $picUrl, $picPublicId, $seekerId);
		$updateSeeker->execute();
		$updateSeeker->close();

		$seekerToken = $this->loginAndGetToken(self::SEEKER_EMAIL);

		// Seeker requests account deletion
		$response = $this->postJson(
			'/api/dashboard/shared/delete_account.php',
			['confirmation' => 'DELETE'],
			$seekerToken,
			['X-Mock-Cloudinary: true']
		);

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertSame('Account deleted successfully', $response['body']['message']);

		// Verify database cascade integrity:
		// 1. Seeker user record is deleted
		$stmt = $this->db->prepare('SELECT user_id FROM users_table WHERE user_id = ?');
		$stmt->bind_param('i', $seekerId);
		$stmt->execute();
		$this->assertNull($stmt->get_result()->fetch_assoc());
		$stmt->close();

		// 2. Seeker profile record is cascaded
		$stmt = $this->db->prepare('SELECT user_id FROM job_seekers_table WHERE user_id = ?');
		$stmt->bind_param('i', $seekerId);
		$stmt->execute();
		$this->assertNull($stmt->get_result()->fetch_assoc());
		$stmt->close();

		// 3. Application record is cascaded
		$stmt = $this->db->prepare('SELECT application_id FROM applications_table WHERE application_id = ?');
		$stmt->bind_param('i', $applicationId);
		$stmt->execute();
		$this->assertNull($stmt->get_result()->fetch_assoc());
		$stmt->close();

		// 4. Saved job entry is cascaded
		$stmt = $this->db->prepare('SELECT id FROM saved_jobs_table WHERE user_id = ?');
		$stmt->bind_param('i', $seekerId);
		$stmt->execute();
		$this->assertNull($stmt->get_result()->fetch_assoc());
		$stmt->close();

		// 5. The job and employer remain intact
		$stmt = $this->db->prepare('SELECT job_id FROM jobs_table WHERE job_id = ?');
		$stmt->bind_param('i', $jobId);
		$stmt->execute();
		$this->assertNotNull($stmt->get_result()->fetch_assoc());
		$stmt->close();
	}

	public function test_employer_can_delete_account_and_cascades_company_jobs_and_applications(): void
	{
		// Setup employer with company, category, and job
		$employerId = $this->createEmployer(self::EMPLOYER_EMAIL);
		$companyId = $this->createCompany($employerId);
		$this->linkCompanyToEmployer($employerId, $companyId);
		$categoryId = $this->createCategory(self::TEST_CATEGORY);
		$jobId = $this->createActiveJob($employerId, $companyId, $categoryId);

		// Setup seeker and application to the employer's job
		$seekerId = $this->createJobSeeker(self::SEEKER_TWO_EMAIL);
		$applicationId = $this->createApplication($jobId, $seekerId, 'pending', 'Application to be cascaded');

		$employerToken = $this->loginAndGetToken(self::EMPLOYER_EMAIL);

		// Employer requests account deletion
		$response = $this->postJson(
			'/api/dashboard/shared/delete_account.php',
			['confirmation' => 'DELETE'],
			$employerToken,
			['X-Mock-Cloudinary: true']
		);

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertSame('Account deleted successfully', $response['body']['message']);

		// Verify database cascade integrity:
		// 1. Employer user record is deleted
		$stmt = $this->db->prepare('SELECT user_id FROM users_table WHERE user_id = ?');
		$stmt->bind_param('i', $employerId);
		$stmt->execute();
		$this->assertNull($stmt->get_result()->fetch_assoc());
		$stmt->close();

		// 2. Employer profile is cascaded
		$stmt = $this->db->prepare('SELECT user_id FROM employers_table WHERE user_id = ?');
		$stmt->bind_param('i', $employerId);
		$stmt->execute();
		$this->assertNull($stmt->get_result()->fetch_assoc());
		$stmt->close();

		// 3. Company is cascaded
		$stmt = $this->db->prepare('SELECT id FROM companies WHERE id = ?');
		$stmt->bind_param('i', $companyId);
		$stmt->execute();
		$this->assertNull($stmt->get_result()->fetch_assoc());
		$stmt->close();

		// 4. Job is cascaded
		$stmt = $this->db->prepare('SELECT job_id FROM jobs_table WHERE job_id = ?');
		$stmt->bind_param('i', $jobId);
		$stmt->execute();
		$this->assertNull($stmt->get_result()->fetch_assoc());
		$stmt->close();

		// 5. Application to this job is cascaded
		$stmt = $this->db->prepare('SELECT application_id FROM applications_table WHERE application_id = ?');
		$stmt->bind_param('i', $applicationId);
		$stmt->execute();
		$this->assertNull($stmt->get_result()->fetch_assoc());
		$stmt->close();

		// 6. The applying seeker user remains intact
		$stmt = $this->db->prepare('SELECT user_id FROM users_table WHERE user_id = ?');
		$stmt->bind_param('i', $seekerId);
		$stmt->execute();
		$this->assertNotNull($stmt->get_result()->fetch_assoc());
		$stmt->close();
	}

	public function test_delete_account_requires_exact_delete_confirmation(): void
	{
		$seekerId = $this->createJobSeeker(self::SEEKER_EMAIL);
		$seekerToken = $this->loginAndGetToken(self::SEEKER_EMAIL);

		$response = $this->postJson(
			'/api/dashboard/shared/delete_account.php',
			['confirmation' => 'INCORRECT_TEXT'],
			$seekerToken,
			['X-Mock-Cloudinary: true']
		);

		$this->assertSame(400, $response['statusCode']);
		$this->assertFalse($response['body']['status']);
		$this->assertSame('Invalid confirmation text', $response['body']['message']);

		// Verify user record was NOT deleted
		$stmt = $this->db->prepare('SELECT user_id FROM users_table WHERE user_id = ?');
		$stmt->bind_param('i', $seekerId);
		$stmt->execute();
		$this->assertNotNull($stmt->get_result()->fetch_assoc());
		$stmt->close();
	}

	public function test_user_can_delete_profile_photo_and_clears_db_references(): void
	{
		$seekerId = $this->createJobSeeker(self::SEEKER_EMAIL);

		// Seed profile photo in job_seekers_table
		$picUrl = 'https://res.cloudinary.com/jobnet-mock/image/upload/jobnet/profiles/pic_1.jpg';
		$picPublicId = 'jobnet/profiles/pic_1.jpg';
		$updateStmt = $this->db->prepare(
			'UPDATE job_seekers_table SET profile_pic_url = ?, profile_pic_public_id = ? WHERE user_id = ?'
		);
		$updateStmt->bind_param('ssi', $picUrl, $picPublicId, $seekerId);
		$updateStmt->execute();
		$updateStmt->close();

		$seekerToken = $this->loginAndGetToken(self::SEEKER_EMAIL);

		// Delete profile photo
		$response = $this->postForm(
			'/api/dashboard/shared/delete_profile_photo.php',
			[],
			$seekerToken,
			['X-Mock-Cloudinary: true']
		);

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertSame('Profile photo deleted successfully', $response['body']['message']);

		// Verify database columns are cleared
		$stmt = $this->db->prepare(
			'SELECT profile_pic_url, profile_pic_public_id FROM job_seekers_table WHERE user_id = ?'
		);
		$stmt->bind_param('i', $seekerId);
		$stmt->execute();
		$row = $stmt->get_result()->fetch_assoc();
		$stmt->close();

		$this->assertIsArray($row);
		$this->assertNull($row['profile_pic_url']);
		$this->assertNull($row['profile_pic_public_id']);

		// User account remains intact
		$stmt = $this->db->prepare('SELECT user_id FROM users_table WHERE user_id = ?');
		$stmt->bind_param('i', $seekerId);
		$stmt->execute();
		$this->assertNotNull($stmt->get_result()->fetch_assoc());
		$stmt->close();
	}

	public function test_admin_can_delete_user_and_cascades_associated_records(): void
	{
		$adminId = $this->createAdmin(self::ADMIN_EMAIL);
		$adminToken = $this->loginAndGetToken(self::ADMIN_EMAIL);

		$targetSeekerId = $this->createJobSeeker(self::TARGET_EMAIL);

		$response = $this->postJson(
			'/api/dashboard/admin/delete_user.php',
			['user_id' => $targetSeekerId],
			$adminToken,
			['X-Mock-Cloudinary: true']
		);

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertSame('User and all associated data deleted successfully.', $response['body']['message']);

		// Verify target user is deleted
		$stmt = $this->db->prepare('SELECT user_id FROM users_table WHERE user_id = ?');
		$stmt->bind_param('i', $targetSeekerId);
		$stmt->execute();
		$this->assertNull($stmt->get_result()->fetch_assoc());
		$stmt->close();

		// Admin user remains intact
		$stmt = $this->db->prepare('SELECT user_id FROM users_table WHERE user_id = ?');
		$stmt->bind_param('i', $adminId);
		$stmt->execute();
		$this->assertNotNull($stmt->get_result()->fetch_assoc());
		$stmt->close();
	}

	public function test_admin_cannot_delete_another_admin(): void
	{
		$admin1Id = $this->createAdmin(self::ADMIN_EMAIL, 'Admin', 'One');
		$admin1Token = $this->loginAndGetToken(self::ADMIN_EMAIL);

		$admin2Id = $this->createAdmin(self::ADMIN_TWO_EMAIL, 'Admin', 'Two');

		$response = $this->postJson(
			'/api/dashboard/admin/delete_user.php',
			['user_id' => $admin2Id],
			$admin1Token,
			['X-Mock-Cloudinary: true']
		);

		$this->assertSame(403, $response['statusCode']);
		$this->assertFalse($response['body']['status']);
		$this->assertSame('Cannot delete other admins', $response['body']['message']);

		// Verify Admin 2 was NOT deleted
		$stmt = $this->db->prepare('SELECT user_id FROM users_table WHERE user_id = ?');
		$stmt->bind_param('i', $admin2Id);
		$stmt->execute();
		$this->assertNotNull($stmt->get_result()->fetch_assoc());
		$stmt->close();
	}

	private function deleteTestData(): void
	{
		$this->cleanupUsersByEmails([
			self::SEEKER_EMAIL,
			self::SEEKER_TWO_EMAIL,
			self::EMPLOYER_EMAIL,
			self::ADMIN_EMAIL,
			self::ADMIN_TWO_EMAIL,
			self::TARGET_EMAIL,
		]);

		$this->cleanupCategoriesByNames([self::TEST_CATEGORY]);
	}
}
