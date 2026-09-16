<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class JobEditingTest extends TestCase
{
	private const BASE_URL = 'http://localhost/JobPortal';
	private const TEST_EMAIL = 'test.job.editing@jobnet.test';
	private const TEST_PASSWORD = 'TestPassword123!';
	private const TEST_CATEGORY = 'Test Job Editing Category';

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

	public function test_authenticated_employer_can_edit_owned_job(): void
	{
		$userId = $this->createEmployer();
		$companyId = $this->createCompany($userId);
		$this->linkCompanyToEmployer($userId, $companyId);
		$categoryId = $this->createCategory();
		$jobId = $this->createJob($userId, $companyId, $categoryId);

		$login = $this->postJson('/api/auth/login.php', [
			'mail' => self::TEST_EMAIL,
			'pword' => self::TEST_PASSWORD,
		]);

		$this->assertSame(200, $login['statusCode']);
		$this->assertNotEmpty($login['body']['token']);

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
			$login['body']['token']
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

	private function createEmployer(): int
	{
		$passwordHash = password_hash(self::TEST_PASSWORD, PASSWORD_DEFAULT);
		$firstname = 'Test';
		$lastname = 'Employer';
		$email = self::TEST_EMAIL;
		$role = 'employer';
		$suspended = 0;

		$statement = $this->db->prepare(
			'INSERT INTO users_table
				(firstname, lastname, email, password, role, suspended)
			 VALUES (?, ?, ?, ?, ?, ?)'
		);
		$statement->bind_param(
			'sssssi',
			$firstname,
			$lastname,
			$email,
			$passwordHash,
			$role,
			$suspended
		);
		$statement->execute();
		$userId = $this->db->insert_id;
		$statement->close();

		$statement = $this->db->prepare(
			'INSERT INTO employers_table (user_id) VALUES (?)'
		);
		$statement->bind_param('i', $userId);
		$statement->execute();
		$statement->close();

		return $userId;
	}

	private function createCompany(int $userId): int
	{
		$name = 'Test Editing Company';
		$location = 'Lagos';
		$website = 'https://example.test';
		$description = 'A test company for job editing integration coverage.';

		$statement = $this->db->prepare(
			'INSERT INTO companies
				(name, location, website, description, user_id)
			 VALUES (?, ?, ?, ?, ?)'
		);
		$statement->bind_param(
			'ssssi',
			$name,
			$location,
			$website,
			$description,
			$userId
		);
		$statement->execute();
		$companyId = $this->db->insert_id;
		$statement->close();

		return $companyId;
	}

	private function linkCompanyToEmployer(int $userId, int $companyId): void
	{
		$statement = $this->db->prepare(
			'UPDATE employers_table SET company_id = ? WHERE user_id = ?'
		);
		$statement->bind_param('ii', $companyId, $userId);
		$statement->execute();
		$statement->close();
	}

	private function createCategory(): int
	{
		$name = self::TEST_CATEGORY;
		$statement = $this->db->prepare(
			'INSERT INTO categories (name) VALUES (?)'
		);
		$statement->bind_param('s', $name);
		$statement->execute();
		$categoryId = $this->db->insert_id;
		$statement->close();

		return $categoryId;
	}

	private function createJob(int $userId, int $companyId, int $categoryId): int
	{
		$title = 'Original PHP Backend Developer';
		$employmentType = 'fulltime';
		$location = 'Lagos';
		$salaryAmount = 250000.0;
		$currency = 'NGN';
		$salaryDuration = 'Monthly';
		$experienceLevel = 'Mid';
		$englishFluency = 'Fluent';
		$overview = 'Original overview for the backend developer role.';
		$description = 'Original description for the PHP backend developer position.';
		$responsibilities = 'Implement backend features and review code changes.';
		$requirements = 'Experience with PHP, MySQL, and HTTP APIs.';
		$niceToHave = '';
		$benefits = '';
		$deadline = date('Y-m-d', strtotime('+30 days'));

		$statement = $this->db->prepare(
			'INSERT INTO jobs_table (
				title, category_id, employment_type, location, salary_amount,
				currency, salary_duration, experience_level, english_fluency,
				overview, description, responsibilities, requirements,
				nice_to_have, benefits, deadline, employer_id, company_id,
				status, published_at
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', NOW())'
		);
		$statement->bind_param(
			'sissdsssssssssssii',
			$title,
			$categoryId,
			$employmentType,
			$location,
			$salaryAmount,
			$currency,
			$salaryDuration,
			$experienceLevel,
			$englishFluency,
			$overview,
			$description,
			$responsibilities,
			$requirements,
			$niceToHave,
			$benefits,
			$deadline,
			$userId,
			$companyId
		);
		$statement->execute();
		$jobId = $this->db->insert_id;
		$statement->close();

		return $jobId;
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
			$jobStatement = $this->db->prepare(
				'DELETE FROM jobs_table WHERE employer_id = ?'
			);
			$jobStatement->bind_param('i', $userId);
			$jobStatement->execute();
			$jobStatement->close();

			$companyStatement = $this->db->prepare(
				'SELECT id FROM companies WHERE user_id = ?'
			);
			$companyStatement->bind_param('i', $userId);
			$companyStatement->execute();
			$companyResult = $companyStatement->get_result();
			$companyIds = [];
			while ($company = $companyResult->fetch_assoc()) {
				$companyIds[] = (int) $company['id'];
			}
			$companyStatement->close();

			$employerStatement = $this->db->prepare(
				'DELETE FROM employers_table WHERE user_id = ?'
			);
			$employerStatement->bind_param('i', $userId);
			$employerStatement->execute();
			$employerStatement->close();

			foreach ($companyIds as $companyId) {
				$deleteCompany = $this->db->prepare(
					'DELETE FROM companies WHERE id = ?'
				);
				$deleteCompany->bind_param('i', $companyId);
				$deleteCompany->execute();
				$deleteCompany->close();
			}
		}

		$categoryStatement = $this->db->prepare(
			'DELETE FROM categories WHERE name = ?'
		);
		$categoryName = self::TEST_CATEGORY;
		$categoryStatement->bind_param('s', $categoryName);
		$categoryStatement->execute();
		$categoryStatement->close();

		$deleteUser = $this->db->prepare(
			'DELETE FROM users_table WHERE email = ?'
		);
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
