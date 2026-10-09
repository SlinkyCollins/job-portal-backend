<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

abstract class BaseTestCase extends TestCase
{
	protected const BASE_URL = 'http://localhost/JobPortal';
	protected const DEFAULT_PASSWORD = 'TestPassword123!';

	protected mysqli $db;

	protected function setUp(): void
	{
		parent::setUp();
		$this->setUpDb();
	}

	protected function tearDown(): void
	{
		$this->closeDb();
		parent::tearDown();
	}

	protected function setUpDb(): void
	{
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
	}

	protected function closeDb(): void
	{
		if (isset($this->db) && $this->db instanceof mysqli && $this->db->ping()) {
			$this->db->close();
		}
	}

	// ------------------------------------------------------------------
	// HTTP Helpers
	// ------------------------------------------------------------------

	protected function getJson(string $path, ?string $token = null): array
	{
		$ch = curl_init(self::BASE_URL . $path);
		$headers = ['Accept: application/json'];

		if ($token !== null) {
			$headers[] = 'Authorization: Bearer ' . $token;
		}

		curl_setopt_array($ch, [
			CURLOPT_HTTPGET => true,
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

	protected function postJson(string $path, array $payload, ?string $token = null): array
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
		$this->assertIsArray($decodedBody, 'API response was not valid JSON. Raw: ' . $body);

		return [
			'statusCode' => $statusCode,
			'body' => $decodedBody,
		];
	}

	protected function postForm(string $path, array $fields, ?string $token = null): array
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

	protected function loginAndGetToken(string $email, string $password = self::DEFAULT_PASSWORD): string
	{
		$response = $this->postJson('/api/auth/login.php', [
			'mail' => $email,
			'pword' => $password,
		]);

		$this->assertSame(200, $response['statusCode'], 'Login failed during test setup.');
		$this->assertNotEmpty($response['body']['token'] ?? null, 'No token returned from login.');

		return $response['body']['token'];
	}

	// ------------------------------------------------------------------
	// Factory / Seeding Helpers
	// ------------------------------------------------------------------

	protected function createEmployer(
		string $email,
		string $firstname = 'Test',
		string $lastname = 'Employer',
		string $password = self::DEFAULT_PASSWORD
	): int {
		$passwordHash = password_hash($password, PASSWORD_DEFAULT);
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

	protected function createJobSeeker(
		string $email,
		string $firstname = 'Test',
		string $lastname = 'Applicant',
		string $password = self::DEFAULT_PASSWORD
	): int {
		$passwordHash = password_hash($password, PASSWORD_DEFAULT);
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

		$statement = $this->db->prepare('INSERT INTO job_seekers_table (user_id, cv_url, cv_filename) VALUES (?, ?, ?)');
		$cvUrl = 'https://example.test/seeker_cv.pdf';
		$cvFilename = 'seeker_cv.pdf';
		$statement->bind_param('iss', $userId, $cvUrl, $cvFilename);
		$statement->execute();
		$statement->close();

		return $userId;
	}

	protected function setSeekerDefaultCv(
		int $userId,
		string $url = 'https://example.test/default_cv.pdf',
		string $filename = 'default_cv.pdf'
	): void {
		$statement = $this->db->prepare(
			'UPDATE job_seekers_table SET cv_url = ?, cv_filename = ? WHERE user_id = ?'
		);
		$statement->bind_param('ssi', $url, $filename, $userId);
		$statement->execute();
		$statement->close();
	}

	protected function createCompany(
		int $userId,
		string $name = 'Test Company',
		string $location = 'Lagos',
		string $website = 'https://example.test',
		string $description = 'A test company for integration testing.'
	): int {
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

	protected function linkCompanyToEmployer(int $userId, int $companyId): void
	{
		$statement = $this->db->prepare(
			'UPDATE employers_table SET company_id = ? WHERE user_id = ?'
		);
		$statement->bind_param('ii', $companyId, $userId);
		$statement->execute();
		$statement->close();
	}

	protected function createCategory(string $name = 'Test Category'): int
	{
		$statement = $this->db->prepare('INSERT INTO categories (name) VALUES (?)');
		$statement->bind_param('s', $name);
		$statement->execute();
		$categoryId = $this->db->insert_id;
		$statement->close();

		return $categoryId;
	}

	protected function createActiveJob(
		int $userId,
		int $companyId,
		int $categoryId,
		string $title = 'Test Software Engineer',
		array $overrides = []
	): int {
		$location = $overrides['location'] ?? 'Lagos';
		$employmentType = $overrides['employment_type'] ?? 'fulltime';
		$salaryAmount = $overrides['salary_amount'] ?? 250000.0;
		$currency = $overrides['currency'] ?? 'NGN';
		$salaryDuration = $overrides['salary_duration'] ?? 'Monthly';
		$experienceLevel = $overrides['experience_level'] ?? 'Mid';
		$overview = $overrides['overview'] ?? 'Test job overview.';
		$description = $overrides['description'] ?? 'Test job description.';
		$deadline = $overrides['deadline'] ?? date('Y-m-d', strtotime('+30 days'));
		$status = $overrides['status'] ?? 'active';

		$statement = $this->db->prepare(
			'INSERT INTO jobs_table (
				title, category_id, employment_type, location, salary_amount,
				currency, salary_duration, experience_level, overview, description,
				deadline, employer_id, company_id, status, published_at
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
		);
		$statement->bind_param(
			'sissdssssssiis',
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
			$companyId,
			$status
		);
		$statement->execute();
		$jobId = $this->db->insert_id;
		$statement->close();

		return $jobId;
	}

	protected function createApplication(
		int $jobId,
		int $seekerId,
		string $status = 'pending',
		string $coverLetter = 'Test application cover letter.'
	): int {
		$resumeUrl = 'https://example.test/resume.pdf';
		$resumeFilename = 'resume.pdf';
		$statement = $this->db->prepare(
			'INSERT INTO applications_table (job_id, seeker_id, status, cover_letter, resume_url, resume_filename)
			 VALUES (?, ?, ?, ?, ?, ?)'
		);
		$statement->bind_param('iissss', $jobId, $seekerId, $status, $coverLetter, $resumeUrl, $resumeFilename);
		$statement->execute();
		$applicationId = $this->db->insert_id;
		$statement->close();

		return $applicationId;
	}

	// ------------------------------------------------------------------
	// Database Cleanup Helpers
	// ------------------------------------------------------------------

	protected function cleanupUsersByEmails(array $emails): void
	{
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
				// Delete applications submitted by this user as a seeker
				$statement = $this->db->prepare('DELETE FROM applications_table WHERE seeker_id = ?');
				$statement->bind_param('i', $userId);
				$statement->execute();
				$statement->close();

				// Delete applications submitted to jobs posted by this user as employer
				$statement = $this->db->prepare(
					'DELETE a FROM applications_table a
					 JOIN jobs_table j ON a.job_id = j.job_id
					 WHERE j.employer_id = ?'
				);
				$statement->bind_param('i', $userId);
				$statement->execute();
				$statement->close();

				// Delete saved jobs
				$statement = $this->db->prepare('DELETE FROM saved_jobs_table WHERE user_id = ?');
				$statement->bind_param('i', $userId);
				$statement->execute();
				$statement->close();

				// Delete jobs posted by this user
				$statement = $this->db->prepare('DELETE FROM jobs_table WHERE employer_id = ?');
				$statement->bind_param('i', $userId);
				$statement->execute();
				$statement->close();

				// Find companies created by this user
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
	}

	protected function cleanupCategoriesByNames(array $names): void
	{
		foreach ($names as $name) {
			$statement = $this->db->prepare('DELETE FROM categories WHERE name = ?');
			$statement->bind_param('s', $name);
			$statement->execute();
			$statement->close();
		}
	}
}
