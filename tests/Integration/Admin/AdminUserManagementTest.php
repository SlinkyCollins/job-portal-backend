<?php

declare(strict_types=1);

final class AdminUserManagementTest extends BaseTestCase
{
	private const ADMIN_ONE_EMAIL = 'test.admin1.mgmt@jobnet.test';
	private const ADMIN_TWO_EMAIL = 'test.admin2.mgmt@jobnet.test';
	private const MANAGED_SEEKER_EMAIL = 'test.seeker.mgmt@jobnet.test';
	private const MANAGED_EMPLOYER_EMAIL = 'test.employer.mgmt@jobnet.test';

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

	public function test_admin_can_suspend_user_and_prevent_login(): void
	{
		$adminId = $this->createAdmin(self::ADMIN_ONE_EMAIL);
		$adminToken = $this->loginAndGetToken(self::ADMIN_ONE_EMAIL);

		$seekerId = $this->createJobSeeker(self::MANAGED_SEEKER_EMAIL);

		// Admin suspends the seeker
		$response = $this->postJson(
			'/api/dashboard/admin/suspend_user.php',
			[
				'user_id' => $seekerId,
				'action' => 'suspend',
			],
			$adminToken
		);

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertSame('User suspended successfully', $response['body']['message']);

		// Verify database state: suspended = 1
		$statement = $this->db->prepare('SELECT suspended FROM users_table WHERE user_id = ?');
		$statement->bind_param('i', $seekerId);
		$statement->execute();
		$row = $statement->get_result()->fetch_assoc();
		$statement->close();

		$this->assertIsArray($row);
		$this->assertSame(1, (int) $row['suspended']);

		// Consequential impact: Suspended user can no longer log in
		$loginResponse = $this->postJson('/api/auth/login.php', [
			'mail' => self::MANAGED_SEEKER_EMAIL,
			'pword' => self::DEFAULT_PASSWORD,
		]);

		$this->assertSame(403, $loginResponse['statusCode']);
		$this->assertFalse($loginResponse['body']['status']);
		$this->assertSame('Your account has been suspended. Please contact support.', $loginResponse['body']['message']);
	}

	public function test_admin_can_unsuspend_user_and_restore_login(): void
	{
		$adminId = $this->createAdmin(self::ADMIN_ONE_EMAIL);
		$adminToken = $this->loginAndGetToken(self::ADMIN_ONE_EMAIL);

		$seekerId = $this->createJobSeeker(self::MANAGED_SEEKER_EMAIL);

		// Pre-suspend the user directly in database
		$statement = $this->db->prepare('UPDATE users_table SET suspended = 1 WHERE user_id = ?');
		$statement->bind_param('i', $seekerId);
		$statement->execute();
		$statement->close();

		// Verify login is initially blocked
		$initialLogin = $this->postJson('/api/auth/login.php', [
			'mail' => self::MANAGED_SEEKER_EMAIL,
			'pword' => self::DEFAULT_PASSWORD,
		]);
		$this->assertSame(403, $initialLogin['statusCode']);

		// Admin unsuspends the user
		$response = $this->postJson(
			'/api/dashboard/admin/suspend_user.php',
			[
				'user_id' => $seekerId,
				'action' => 'unsuspend',
			],
			$adminToken
		);

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertSame('User unsuspended successfully', $response['body']['message']);

		// Verify database state: suspended = 0
		$statement = $this->db->prepare('SELECT suspended FROM users_table WHERE user_id = ?');
		$statement->bind_param('i', $seekerId);
		$statement->execute();
		$row = $statement->get_result()->fetch_assoc();
		$statement->close();

		$this->assertIsArray($row);
		$this->assertSame(0, (int) $row['suspended']);

		// Consequential impact: User can now log in successfully
		$restoredLogin = $this->postJson('/api/auth/login.php', [
			'mail' => self::MANAGED_SEEKER_EMAIL,
			'pword' => self::DEFAULT_PASSWORD,
		]);

		$this->assertSame(200, $restoredLogin['statusCode']);
		$this->assertTrue($restoredLogin['body']['status']);
		$this->assertSame('Login successful.', $restoredLogin['body']['message']);
		$this->assertNotEmpty($restoredLogin['body']['token']);
	}

	public function test_admin_cannot_suspend_another_admin(): void
	{
		$admin1Id = $this->createAdmin(self::ADMIN_ONE_EMAIL, 'Admin', 'One');
		$admin1Token = $this->loginAndGetToken(self::ADMIN_ONE_EMAIL);

		$admin2Id = $this->createAdmin(self::ADMIN_TWO_EMAIL, 'Admin', 'Two');

		// Admin 1 attempts to suspend Admin 2
		$response = $this->postJson(
			'/api/dashboard/admin/suspend_user.php',
			[
				'user_id' => $admin2Id,
				'action' => 'suspend',
			],
			$admin1Token
		);

		$this->assertSame(403, $response['statusCode']);
		$this->assertFalse($response['body']['status']);
		$this->assertSame('Cannot suspend other admins', $response['body']['message']);

		// Verify Admin 2 was NOT suspended in database
		$statement = $this->db->prepare('SELECT suspended FROM users_table WHERE user_id = ?');
		$statement->bind_param('i', $admin2Id);
		$statement->execute();
		$row = $statement->get_result()->fetch_assoc();
		$statement->close();

		$this->assertIsArray($row);
		$this->assertSame(0, (int) $row['suspended']);
	}

	public function test_admin_can_retrieve_user_list_excluding_self(): void
	{
		$adminId = $this->createAdmin(self::ADMIN_ONE_EMAIL, 'Super', 'Admin');
		$adminToken = $this->loginAndGetToken(self::ADMIN_ONE_EMAIL);

		$seekerId = $this->createJobSeeker(self::MANAGED_SEEKER_EMAIL, 'Jane', 'Seeker');
		$employerId = $this->createEmployer(self::MANAGED_EMPLOYER_EMAIL, 'John', 'Employer');

		$response = $this->getJson('/api/dashboard/admin/get_users.php', $adminToken);

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertSame('Users retrieved successfully.', $response['body']['message']);
		$this->assertArrayHasKey('data', $response['body']);

		$users = $response['body']['data'];
		$userIds = array_map(static fn(array $u): int => (int) $u['user_id'], $users);

		// Other users are listed with their roles and status
		$this->assertContains($seekerId, $userIds);
		$this->assertContains($employerId, $userIds);

		// Requesting admin is excluded from the listing
		$this->assertNotContains($adminId, $userIds);

		// Verify record structure for listed user
		$seekerRecord = null;
		foreach ($users as $u) {
			if ((int) $u['user_id'] === $seekerId) {
				$seekerRecord = $u;
				break;
			}
		}
		$this->assertNotNull($seekerRecord);
		$this->assertSame('Jane', $seekerRecord['firstname']);
		$this->assertSame('Seeker', $seekerRecord['lastname']);
		$this->assertSame(self::MANAGED_SEEKER_EMAIL, $seekerRecord['email']);
		$this->assertSame('job_seeker', $seekerRecord['role']);
		$this->assertSame(0, (int) $seekerRecord['suspended']);
		$this->assertArrayHasKey('job_count', $seekerRecord);
	}

	public function test_suspend_user_validates_input_and_existence(): void
	{
		$this->createAdmin(self::ADMIN_ONE_EMAIL);
		$adminToken = $this->loginAndGetToken(self::ADMIN_ONE_EMAIL);

		// Non-existent user ID returns 404
		$notFoundResponse = $this->postJson(
			'/api/dashboard/admin/suspend_user.php',
			[
				'user_id' => 99999999,
				'action' => 'suspend',
			],
			$adminToken
		);
		$this->assertSame(404, $notFoundResponse['statusCode']);
		$this->assertFalse($notFoundResponse['body']['status']);
		$this->assertSame('User not found', $notFoundResponse['body']['message']);

		// Invalid action returns 400
		$seekerId = $this->createJobSeeker(self::MANAGED_SEEKER_EMAIL);
		$invalidActionResponse = $this->postJson(
			'/api/dashboard/admin/suspend_user.php',
			[
				'user_id' => $seekerId,
				'action' => 'invalid_action',
			],
			$adminToken
		);
		$this->assertSame(400, $invalidActionResponse['statusCode']);
		$this->assertFalse($invalidActionResponse['body']['status']);
		$this->assertSame('Invalid request', $invalidActionResponse['body']['message']);
	}

	private function deleteTestData(): void
	{
		$this->cleanupUsersByEmails([
			self::ADMIN_ONE_EMAIL,
			self::ADMIN_TWO_EMAIL,
			self::MANAGED_SEEKER_EMAIL,
			self::MANAGED_EMPLOYER_EMAIL,
		]);
	}
}
