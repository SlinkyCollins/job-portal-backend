<?php

declare(strict_types=1);

final class AccountSettingsTest extends BaseTestCase
{
	private const TEST_SEEKER_EMAIL = 'test.settings.seeker@jobnet.test';
	private const TEST_NEW_PASSWORD = 'BrandNewPassword123!';

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

	public function test_user_can_retrieve_account_settings(): void
	{
		$userId = $this->createJobSeeker(self::TEST_SEEKER_EMAIL, 'Alice', 'Smith');
		$token = $this->loginAndGetToken(self::TEST_SEEKER_EMAIL);

		$response = $this->getJson('/api/dashboard/shared/get_settings.php', $token);

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertSame('Settings retrieved successfully', $response['body']['message']);

		$this->assertSame('Alice', $response['body']['firstname']);
		$this->assertSame('Smith', $response['body']['lastname']);
		$this->assertSame(self::TEST_SEEKER_EMAIL, $response['body']['email']);
		$this->assertArrayHasKey('created_at', $response['body']);
		$this->assertTrue($response['body']['has_password']);
	}

	public function test_user_can_update_account_name(): void
	{
		$userId = $this->createJobSeeker(self::TEST_SEEKER_EMAIL, 'OriginalFirst', 'OriginalLast');
		$token = $this->loginAndGetToken(self::TEST_SEEKER_EMAIL);

		// Valid update
		$updateResponse = $this->postJson('/api/dashboard/shared/update_account.php', [
			'firstName' => 'UpdatedFirst',
			'lastName' => 'UpdatedLast',
		], $token);

		$this->assertSame(200, $updateResponse['statusCode']);
		$this->assertTrue($updateResponse['body']['status']);
		$this->assertSame('Account updated successfully.', $updateResponse['body']['message']);
		$this->assertSame('UpdatedFirst', $updateResponse['body']['firstName']);
		$this->assertSame('UpdatedLast', $updateResponse['body']['lastName']);

		// Verify database persistence
		$stmt = $this->db->prepare('SELECT firstname, lastname FROM users_table WHERE user_id = ?');
		$stmt->bind_param('i', $userId);
		$stmt->execute();
		$user = $stmt->get_result()->fetch_assoc();
		$stmt->close();

		$this->assertSame('UpdatedFirst', $user['firstname']);
		$this->assertSame('UpdatedLast', $user['lastname']);

		// Verify reflected via get_settings.php
		$settingsResponse = $this->getJson('/api/dashboard/shared/get_settings.php', $token);
		$this->assertSame(200, $settingsResponse['statusCode']);
		$this->assertSame('UpdatedFirst', $settingsResponse['body']['firstname']);
		$this->assertSame('UpdatedLast', $settingsResponse['body']['lastname']);

		// Validation: Name too short (min:2 required)
		$shortResponse = $this->postJson('/api/dashboard/shared/update_account.php', [
			'firstName' => 'A',
			'lastName' => 'UpdatedLast',
		], $token);

		$this->assertSame(400, $shortResponse['statusCode']);
		$this->assertFalse($shortResponse['body']['status']);
		$this->assertSame('Invalid input data.', $shortResponse['body']['message']);
	}

	public function test_user_can_verify_current_password(): void
	{
		$this->createJobSeeker(self::TEST_SEEKER_EMAIL, 'Test', 'User', self::DEFAULT_PASSWORD);
		$token = $this->loginAndGetToken(self::TEST_SEEKER_EMAIL, self::DEFAULT_PASSWORD);

		// Correct current password
		$correctResponse = $this->postJson('/api/dashboard/shared/verify_old_password.php', [
			'oldPassword' => self::DEFAULT_PASSWORD,
		], $token);

		$this->assertSame(200, $correctResponse['statusCode']);
		$this->assertTrue($correctResponse['body']['status']);
		$this->assertSame('Current password verified.', $correctResponse['body']['message']);

		// Incorrect current password
		$wrongResponse = $this->postJson('/api/dashboard/shared/verify_old_password.php', [
			'oldPassword' => 'WrongPassword123!',
		], $token);

		$this->assertSame(400, $wrongResponse['statusCode']);
		$this->assertFalse($wrongResponse['body']['status']);
		$this->assertSame('Current password is incorrect.', $wrongResponse['body']['message']);
	}

	public function test_user_can_change_password_and_authenticates_with_new_password(): void
	{
		$this->createJobSeeker(self::TEST_SEEKER_EMAIL, 'Test', 'User', self::DEFAULT_PASSWORD);
		$token = $this->loginAndGetToken(self::TEST_SEEKER_EMAIL, self::DEFAULT_PASSWORD);

		// Reject when old password is incorrect
		$wrongOldResponse = $this->postJson('/api/dashboard/shared/change_password.php', [
			'oldPassword' => 'WrongCurrentPassword!',
			'newPassword' => self::TEST_NEW_PASSWORD,
		], $token);

		$this->assertSame(400, $wrongOldResponse['statusCode']);
		$this->assertFalse($wrongOldResponse['body']['status']);
		$this->assertSame('Current password is incorrect.', $wrongOldResponse['body']['message']);

		// Reject when new password fails length validation (minimum 8 characters)
		$shortNewResponse = $this->postJson('/api/dashboard/shared/change_password.php', [
			'oldPassword' => self::DEFAULT_PASSWORD,
			'newPassword' => 'short',
		], $token);

		$this->assertSame(400, $shortNewResponse['statusCode']);
		$this->assertFalse($shortNewResponse['body']['status']);
		$this->assertSame('Validation failed.', $shortNewResponse['body']['message']);

		// Successful password change
		$successResponse = $this->postJson('/api/dashboard/shared/change_password.php', [
			'oldPassword' => self::DEFAULT_PASSWORD,
			'newPassword' => self::TEST_NEW_PASSWORD,
		], $token);

		$this->assertSame(200, $successResponse['statusCode']);
		$this->assertTrue($successResponse['body']['status']);
		$this->assertSame('Password updated successfully.', $successResponse['body']['message']);

		// Login with new password MUST succeed
		$newLoginResponse = $this->postJson('/api/auth/login.php', [
			'mail' => self::TEST_SEEKER_EMAIL,
			'pword' => self::TEST_NEW_PASSWORD,
		]);
		$this->assertSame(200, $newLoginResponse['statusCode']);
		$this->assertTrue($newLoginResponse['body']['status']);
		$this->assertNotEmpty($newLoginResponse['body']['token']);

		// Login with old password MUST be rejected
		$oldLoginResponse = $this->postJson('/api/auth/login.php', [
			'mail' => self::TEST_SEEKER_EMAIL,
			'pword' => self::DEFAULT_PASSWORD,
		]);
		$this->assertSame(401, $oldLoginResponse['statusCode']);
		$this->assertFalse($oldLoginResponse['body']['status']);
		$this->assertSame('Incorrect password.', $oldLoginResponse['body']['message']);
	}

	public function test_account_settings_endpoints_require_authentication(): void
	{
		// get_settings without token -> 401
		$unauthGet = $this->getJson('/api/dashboard/shared/get_settings.php');
		$this->assertSame(401, $unauthGet['statusCode']);

		// update_account without token -> 401
		$unauthUpdate = $this->postJson('/api/dashboard/shared/update_account.php', [
			'firstName' => 'Bob',
			'lastName' => 'Jones',
		]);
		$this->assertSame(401, $unauthUpdate['statusCode']);

		// verify_old_password without token -> 401
		$unauthVerify = $this->postJson('/api/dashboard/shared/verify_old_password.php', [
			'oldPassword' => 'SomePassword',
		]);
		$this->assertSame(401, $unauthVerify['statusCode']);

		// change_password without token -> 401
		$unauthChange = $this->postJson('/api/dashboard/shared/change_password.php', [
			'oldPassword' => 'SomePassword',
			'newPassword' => 'AnotherPassword123!',
		]);
		$this->assertSame(401, $unauthChange['statusCode']);
	}

	private function deleteTestData(): void
	{
		$this->cleanupUsersByEmails([self::TEST_SEEKER_EMAIL]);
	}
}
