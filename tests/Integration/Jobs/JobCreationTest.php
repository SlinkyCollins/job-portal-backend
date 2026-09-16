<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class JobCreationTest extends TestCase
{
	private const BASE_URL = 'http://localhost/JobPortal';
	private const TEST_EMAIL = 'test.job.creation@jobnet.test';
	private const TEST_PASSWORD = 'TestPassword123!';
	private const TEST_CATEGORY = 'Test Job Creation Category';

	private mysqli $db;

	protected function setUp(): void
	{
		parent::setUp();

		$this->db = new mysqli(
			$_ENV['DB_HOST_TEST'],
			$_ENV['DB_USER_TEST'],
			$_ENV['DB_PASS_TEST'],
			$_ENV['DB_NAME_TEST'],
			(int) $_ENV['DB_PORT_TEST']
		);

		if ($this->db->connect_error) {
			$this->fail('Could not connect to test database: ' . $this->db->connect_error);
		}

		$this->deleteTestData();
	}

	protected function tearDown(): void
	{
		$this->deleteTestData();
		$this->db->close();

		parent::tearDown();
	}

	public function test_authenticated_employer_can_create_job(): void
	{
		$passwordHash = password_hash(self::TEST_PASSWORD, PASSWORD_DEFAULT);
		$firstname = 'Test';
		$lastname = 'Employer';
		$role = 'employer';
		$suspended = 0;

		$userStatement = $this->db->prepare(
			'INSERT INTO users_table
				(firstname, lastname, email, password, role, suspended)
			 VALUES (?, ?, ?, ?, ?, ?)'
		);
		$email = self::TEST_EMAIL;
		$userStatement->bind_param(
			'sssssi',
			$firstname,
			$lastname,
			$email,
			$passwordHash,
			$role,
			$suspended
		);
		$userStatement->execute();
		$userId = $this->db->insert_id;
		$userStatement->close();

		$employerStatement = $this->db->prepare(
			'INSERT INTO employers_table (user_id) VALUES (?)'
		);
		$employerStatement->bind_param('i', $userId);
		$employerStatement->execute();
		$employerStatement->close();

		$companyName = 'Test Company';
		$companyLocation = 'Lagos';
		$companyWebsite = 'https://example.test';
		$companyDescription = 'A test company for job creation integration coverage.';
		$companyStatement = $this->db->prepare(
			'INSERT INTO companies
				(name, location, website, description, user_id)
			 VALUES (?, ?, ?, ?, ?)'
		);
		$companyStatement->bind_param(
			'ssssi',
			$companyName,
			$companyLocation,
			$companyWebsite,
			$companyDescription,
			$userId
		);
		$companyStatement->execute();
		$companyId = $this->db->insert_id;
		$companyStatement->close();

		$linkStatement = $this->db->prepare(
			'UPDATE employers_table SET company_id = ? WHERE user_id = ?'
		);
		$linkStatement->bind_param('ii', $companyId, $userId);
		$linkStatement->execute();
		$linkStatement->close();

		$categoryStatement = $this->db->prepare(
			'INSERT INTO categories (name) VALUES (?)'
		);
		$categoryName = self::TEST_CATEGORY;
		$categoryStatement->bind_param('s', $categoryName);
		$categoryStatement->execute();
		$categoryId = $this->db->insert_id;
		$categoryStatement->close();

		$login = $this->postJson('/api/auth/login.php', [
			'mail' => self::TEST_EMAIL,
			'pword' => self::TEST_PASSWORD,
		]);

		$this->assertSame(200, $login['statusCode']);
		$this->assertNotEmpty($login['body']['token']);

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
			$login['body']['token']
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
		$email = self::TEST_EMAIL;
		$userStatement = $this->db->prepare(
			'SELECT user_id FROM users_table WHERE email = ?'
		);
		$userStatement->bind_param('s', $email);
		$userStatement->execute();
		$userResult = $userStatement->get_result();
		$userIds = [];
		while ($user = $userResult->fetch_assoc()) {
			$userIds[] = (int) $user['user_id'];
		}
		$userStatement->close();

		foreach ($userIds as $userId) {
			$jobStatement = $this->db->prepare('DELETE FROM jobs_table WHERE employer_id = ?');
			$jobStatement->bind_param('i', $userId);
			$jobStatement->execute();
			$jobStatement->close();

			$companyStatement = $this->db->prepare('SELECT id FROM companies WHERE user_id = ?');
			$companyStatement->bind_param('i', $userId);
			$companyStatement->execute();
			$companyResult = $companyStatement->get_result();
			$companyIds = [];
			while ($company = $companyResult->fetch_assoc()) {
				$companyIds[] = (int) $company['id'];
			}
			$companyStatement->close();

			$employerStatement = $this->db->prepare('DELETE FROM employers_table WHERE user_id = ?');
			$employerStatement->bind_param('i', $userId);
			$employerStatement->execute();
			$employerStatement->close();

			foreach ($companyIds as $companyId) {
				$deleteCompany = $this->db->prepare('DELETE FROM companies WHERE id = ?');
				$deleteCompany->bind_param('i', $companyId);
				$deleteCompany->execute();
				$deleteCompany->close();
			}
		}

		$categoryStatement = $this->db->prepare('DELETE FROM categories WHERE name = ?');
		$categoryName = self::TEST_CATEGORY;
		$categoryStatement->bind_param('s', $categoryName);
		$categoryStatement->execute();
		$categoryStatement->close();

		$deleteUser = $this->db->prepare('DELETE FROM users_table WHERE email = ?');
		$deleteUser->bind_param('s', $email);
		$deleteUser->execute();
		$deleteUser->close();
	}

	private function postJson(string $path, array $payload, ?string $token = null): array
	{
		$ch = curl_init(self::BASE_URL . $path);
		$headers = [
			'Content-Type: application/json',
			'Accept: application/json',
		];

		if ($token !== null) {
			$headers[] = 'Authorization: Bearer ' . $token;
		}

		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => json_encode($payload),
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_RETURNTRANSFER => true,
		]);

		$body = curl_exec($ch);
		if ($body === false) {
			$error = curl_error($ch);
			curl_close($ch);
			$this->fail('HTTP request failed: ' . $error);
		}

		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$decodedBody = json_decode($body, true);
		$this->assertIsArray($decodedBody, 'API response was not valid JSON.');

		return [
			'statusCode' => $statusCode,
			'body' => $decodedBody,
		];
	}
}