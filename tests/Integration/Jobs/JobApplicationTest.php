<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class JobApplicationTest extends TestCase
{
	private const BASE_URL = 'http://localhost/JobPortal';
	private const TEST_EMAIL = 'test.job.application@jobnet.test';
	private const TEST_PASSWORD = 'TestPassword123!';
	private const TEST_CATEGORY = 'Test Job Application Category';

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

	public function test_authenticated_seeker_can_apply_to_active_job(): void
	{
		$seekerUserId = $this->createJobSeeker();
		$this->setSeekerDefaultCv($seekerUserId);
		$employerUserId = $this->createEmployer();
		$companyId = $this->createCompany($employerUserId);
		$this->linkCompanyToEmployer($employerUserId, $companyId);
		$categoryId = $this->createCategory();
		$jobId = $this->createActiveJob($employerUserId, $companyId, $categoryId);

		$token = $this->loginAndGetToken(self::TEST_EMAIL, self::TEST_PASSWORD);

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
		$seekerUserId = $this->createJobSeeker();
		$this->setSeekerDefaultCv($seekerUserId);
		$employerUserId = $this->createEmployer();
		$companyId = $this->createCompany($employerUserId);
		$this->linkCompanyToEmployer($employerUserId, $companyId);
		$categoryId = $this->createCategory();
		$jobId = $this->createActiveJob($employerUserId, $companyId, $categoryId);

		$token = $this->loginAndGetToken(self::TEST_EMAIL, self::TEST_PASSWORD);

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

	// ------------------------------------------------------------------
	// Test-data helpers
	// ------------------------------------------------------------------

	private function createJobSeeker(): int
	{
		$firstname = 'Test';
		$lastname = 'Applicant';
		$email = self::TEST_EMAIL;
		$passwordHash = password_hash(self::TEST_PASSWORD, PASSWORD_DEFAULT);
		$role = 'job_seeker';
		$suspended = 0;

		$statement = $this->db->prepare(
			'INSERT INTO users_table
				(firstname, lastname, email, password, role, suspended)
			 VALUES (?, ?, ?, ?, ?, ?)'
		);
		$statement->bind_param('sssssi', $firstname, $lastname, $email, $passwordHash, $role, $suspended);
		$statement->execute();
		$userId = $this->db->insert_id;
		$statement->close();

		$statement = $this->db->prepare('INSERT INTO job_seekers_table (user_id) VALUES (?)');
		$statement->bind_param('i', $userId);
		$statement->execute();
		$statement->close();

		return $userId;
	}

	private function setSeekerDefaultCv(int $userId): void
	{
		$cvUrl = 'https://example.test/default_cv.pdf';
		$cvFilename = 'default_cv.pdf';

		$statement = $this->db->prepare(
			'UPDATE job_seekers_table SET cv_url = ?, cv_filename = ? WHERE user_id = ?'
		);
		$statement->bind_param('ssi', $cvUrl, $cvFilename, $userId);
		$statement->execute();
		$statement->close();
	}

	private function createEmployer(): int
	{
		$firstname = 'Test';
		$lastname = 'AppEmployer';
		$email = 'test.job.application.employer@jobnet.test';
		$passwordHash = password_hash('TestPassword123!', PASSWORD_DEFAULT);
		$role = 'employer';
		$suspended = 0;

		$statement = $this->db->prepare(
			'INSERT INTO users_table
				(firstname, lastname, email, password, role, suspended)
			 VALUES (?, ?, ?, ?, ?, ?)'
		);
		$statement->bind_param('sssssi', $firstname, $lastname, $email, $passwordHash, $role, $suspended);
		$statement->execute();
		$userId = $this->db->insert_id;
		$statement->close();

		$statement = $this->db->prepare('INSERT INTO employers_table (user_id) VALUES (?)');
		$statement->bind_param('i', $userId);
		$statement->execute();
		$statement->close();

		return $userId;
	}

	private function createCompany(int $userId): int
	{
		$name = 'Test Application Company';
		$location = 'Lagos';
		$website = 'https://example.test';
		$description = 'A test company for job application integration coverage.';

		$statement = $this->db->prepare(
			'INSERT INTO companies (name, location, website, description, user_id)
			 VALUES (?, ?, ?, ?, ?)'
		);
		$statement->bind_param('ssssi', $name, $location, $website, $description, $userId);
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
		$statement = $this->db->prepare('INSERT INTO categories (name) VALUES (?)');
		$statement->bind_param('s', $name);
		$statement->execute();
		$categoryId = $this->db->insert_id;
		$statement->close();

		return $categoryId;
	}

	private function createActiveJob(int $userId, int $companyId, int $categoryId): int
	{
		$title = 'Test Application Developer';
		$location = 'Lagos';
		$employmentType = 'fulltime';
		$salaryAmount = 250000.0;
		$currency = 'NGN';
		$salaryDuration = 'Monthly';
		$experienceLevel = 'Mid';
		$overview = 'Application role for backend integration testing.';
		$description = 'An active job used to protect the application contract.';
		$deadline = date('Y-m-d', strtotime('+30 days'));

		$statement = $this->db->prepare(
			'INSERT INTO jobs_table (
				title, category_id, employment_type, location, salary_amount,
				currency, salary_duration, experience_level, overview, description,
				deadline, employer_id, company_id, status, published_at
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'active\', NOW())'
		);
		$statement->bind_param(
			'sissdssssssii',
			$title,
			$categoryId,
			$employmentType,
			$location,
			$salaryAmount,
			$currency,
			$salaryDuration,
			$experienceLevel,
			$overview,
			$description,
			$deadline,
			$userId,
			$companyId
		);
		$statement->execute();
		$jobId = $this->db->insert_id;
		$statement->close();

		return $jobId;
	}

	private function loginAndGetToken(string $email, string $password): string
	{
		$response = $this->postJson('/api/auth/login.php', [
			'mail' => $email,
			'pword' => $password,
		]);

		$this->assertSame(200, $response['statusCode'], 'Login failed during test setup.');
		$this->assertNotEmpty($response['body']['token'], 'No token returned from login.');

		return $response['body']['token'];
	}

	private function deleteTestData(): void
	{
		$emails = [self::TEST_EMAIL, 'test.job.application.employer@jobnet.test'];

		foreach ($emails as $email) {
			$statement = $this->db->prepare('SELECT user_id FROM users_table WHERE email = ?');
			$statement->bind_param('s', $email);
			$statement->execute();
			$result = $statement->get_result();
			$userIds = [];
			while ($user = $result->fetch_assoc()) {
				$userIds[] = (int) $user['user_id'];
			}
			$statement->close();

			foreach ($userIds as $userId) {
				$statement = $this->db->prepare('DELETE FROM applications_table WHERE seeker_id = ?');
				$statement->bind_param('i', $userId);
				$statement->execute();
				$statement->close();

				$statement = $this->db->prepare('DELETE FROM jobs_table WHERE employer_id = ?');
				$statement->bind_param('i', $userId);
				$statement->execute();
				$statement->close();

				$statement = $this->db->prepare('SELECT id FROM companies WHERE user_id = ?');
				$statement->bind_param('i', $userId);
				$statement->execute();
				$companyResult = $statement->get_result();
				$companyIds = [];
				while ($company = $companyResult->fetch_assoc()) {
					$companyIds[] = (int) $company['id'];
				}
				$statement->close();

				$statement = $this->db->prepare('DELETE FROM job_seekers_table WHERE user_id = ?');
				$statement->bind_param('i', $userId);
				$statement->execute();
				$statement->close();

				$statement = $this->db->prepare('DELETE FROM employers_table WHERE user_id = ?');
				$statement->bind_param('i', $userId);
				$statement->execute();
				$statement->close();

				foreach ($companyIds as $companyId) {
					$statement = $this->db->prepare('DELETE FROM companies WHERE id = ?');
					$statement->bind_param('i', $companyId);
					$statement->execute();
					$statement->close();
				}
			}

			$statement = $this->db->prepare('DELETE FROM users_table WHERE email = ?');
			$statement->bind_param('s', $email);
			$statement->execute();
			$statement->close();
		}

		$categoryName = self::TEST_CATEGORY;
		$statement = $this->db->prepare('DELETE FROM categories WHERE name = ?');
		$statement->bind_param('s', $categoryName);
		$statement->execute();
		$statement->close();
	}

	private function postForm(string $path, array $fields, ?string $token = null): array
	{
		$ch = curl_init(self::BASE_URL . $path);
		$headers = ['Accept: application/json'];

		if ($token !== null) {
			$headers[] = 'Authorization: Bearer ' . $token;
		}

		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $fields,
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
		$this->assertIsArray($decodedBody, 'API response was not valid JSON. Raw: ' . $body);

		return [
			'statusCode' => $statusCode,
			'body' => $decodedBody,
		];
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
