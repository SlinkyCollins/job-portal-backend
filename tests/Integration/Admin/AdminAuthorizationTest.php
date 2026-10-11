<?php

declare(strict_types=1);

final class AdminAuthorizationTest extends BaseTestCase
{
	private const ADMIN_EMAIL = 'test.admin.auth@jobnet.test';
	private const SEEKER_EMAIL = 'test.seeker.auth@jobnet.test';
	private const EMPLOYER_EMAIL = 'test.employer.auth@jobnet.test';

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

	public function test_admin_can_access_admin_dashboard_endpoint(): void
	{
		$adminId = $this->createAdmin(self::ADMIN_EMAIL, 'Super', 'Administrator');
		$adminToken = $this->loginAndGetToken(self::ADMIN_EMAIL);

		$response = $this->getJson('/api/dashboard/admin/admin_dashboard.php', $adminToken);

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertSame('Admin dashboard loaded successfully.', $response['body']['message']);
		$this->assertArrayHasKey('user', $response['body']);

		$user = $response['body']['user'];
		$this->assertSame($adminId, (int) $user['user_id']);
		$this->assertSame('Super', $user['firstname']);
		$this->assertSame('Administrator', $user['lastname']);
		$this->assertSame(self::ADMIN_EMAIL, $user['email']);
		$this->assertSame('admin', $user['role']);
	}

	public function test_admin_can_access_admin_stats_endpoint(): void
	{
		$this->createAdmin(self::ADMIN_EMAIL);
		$adminToken = $this->loginAndGetToken(self::ADMIN_EMAIL);

		$response = $this->getJson('/api/dashboard/admin/get_stats.php', $adminToken);

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertSame('Stats retrieved successfully', $response['body']['message']);

		$this->assertArrayHasKey('total_users', $response['body']);
		$this->assertArrayHasKey('job_seekers', $response['body']);
		$this->assertArrayHasKey('employers', $response['body']);
		$this->assertArrayHasKey('total_jobs', $response['body']);
		$this->assertArrayHasKey('applications', $response['body']);

		$this->assertGreaterThanOrEqual(1, (int) $response['body']['total_users']);
	}

	public function test_job_seeker_is_rejected_from_admin_endpoints(): void
	{
		$this->createJobSeeker(self::SEEKER_EMAIL);
		$seekerToken = $this->loginAndGetToken(self::SEEKER_EMAIL);

		// Dashboard endpoint
		$dashResponse = $this->getJson('/api/dashboard/admin/admin_dashboard.php', $seekerToken);
		$this->assertSame(403, $dashResponse['statusCode']);
		$this->assertFalse($dashResponse['body']['status']);
		$this->assertSame('Access denied. Requires admin role.', $dashResponse['body']['message']);

		// Stats endpoint
		$statsResponse = $this->getJson('/api/dashboard/admin/get_stats.php', $seekerToken);
		$this->assertSame(403, $statsResponse['statusCode']);
		$this->assertFalse($statsResponse['body']['status']);
		$this->assertSame('Access denied. Requires admin role.', $statsResponse['body']['message']);
	}

	public function test_employer_is_rejected_from_admin_endpoints(): void
	{
		$this->createEmployer(self::EMPLOYER_EMAIL);
		$employerToken = $this->loginAndGetToken(self::EMPLOYER_EMAIL);

		// Dashboard endpoint
		$dashResponse = $this->getJson('/api/dashboard/admin/admin_dashboard.php', $employerToken);
		$this->assertSame(403, $dashResponse['statusCode']);
		$this->assertFalse($dashResponse['body']['status']);
		$this->assertSame('Access denied. Requires admin role.', $dashResponse['body']['message']);

		// Stats endpoint
		$statsResponse = $this->getJson('/api/dashboard/admin/get_stats.php', $employerToken);
		$this->assertSame(403, $statsResponse['statusCode']);
		$this->assertFalse($statsResponse['body']['status']);
		$this->assertSame('Access denied. Requires admin role.', $statsResponse['body']['message']);
	}

	public function test_unauthenticated_request_is_rejected_from_admin_endpoints(): void
	{
		$dashResponse = $this->getJson('/api/dashboard/admin/admin_dashboard.php');
		$this->assertSame(401, $dashResponse['statusCode']);
		$this->assertFalse($dashResponse['body']['status']);
		$this->assertSame('No token provided.', $dashResponse['body']['message']);

		$statsResponse = $this->getJson('/api/dashboard/admin/get_stats.php');
		$this->assertSame(401, $statsResponse['statusCode']);
		$this->assertFalse($statsResponse['body']['status']);
		$this->assertSame('No token provided.', $statsResponse['body']['message']);
	}

	private function deleteTestData(): void
	{
		$this->cleanupUsersByEmails([
			self::ADMIN_EMAIL,
			self::SEEKER_EMAIL,
			self::EMPLOYER_EMAIL,
		]);
	}
}
