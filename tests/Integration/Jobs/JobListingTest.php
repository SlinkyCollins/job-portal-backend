<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class JobListingTest extends TestCase
{
	private const BASE_URL = 'http://localhost/JobPortal';
	private const TEST_EMAIL = 'test.job.listing@jobnet.test';
	private const TEST_CATEGORY = 'Test Job Listing Category';
	private const TEST_KEYWORD = 'Listing Contract';

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

	public function test_job_listing_returns_matching_active_job_for_keyword(): void
	{
		$userId = $this->createEmployer();
		$companyId = $this->createCompany($userId);
		$this->linkCompanyToEmployer($userId, $companyId);
		$categoryId = $this->createCategory();
		$jobId = $this->createActiveJob($userId, $companyId, $categoryId);

		$response = $this->getJson(
			'/api/jobs/all_jobs.php?keyword=' . rawurlencode(self::TEST_KEYWORD)
		);

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertArrayHasKey('jobs', $response['body']);
		$this->assertArrayHasKey('total', $response['body']);
		$this->assertSame(1, (int) $response['body']['total']);
		$this->assertCount(1, $response['body']['jobs']);

		$job = $response['body']['jobs'][0];
		$this->assertSame($jobId, (int) $job['job_id']);
		$this->assertSame('Test Listing Contract Developer', $job['title']);
		$this->assertSame('Lagos', $job['location']);
		$this->assertSame('Test Listing Company', $job['company_name']);
		$this->assertSame(self::TEST_CATEGORY, $job['category_name']);
		$this->assertArrayHasKey('isSaved', $job);
		$this->assertFalse($job['isSaved']);
	}

	private function createEmployer(): int
	{
		$firstname = 'Test';
		$lastname = 'Listing';
		$email = self::TEST_EMAIL;
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
		$name = 'Test Listing Company';
		$location = 'Lagos';
		$website = 'https://example.test';
		$description = 'A test company for job listing integration coverage.';

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
		$title = 'Test Listing Contract Developer';
		$location = 'Lagos';
		$employmentType = 'fulltime';
		$salaryAmount = 250000.0;
		$currency = 'NGN';
		$salaryDuration = 'Monthly';
		$experienceLevel = 'Mid';
		$overview = 'Listing Contract role for backend integration testing.';
		$description = 'A searchable active job used to protect the listing contract.';
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

	private function deleteTestData(): void
	{
		$email = self::TEST_EMAIL;
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

		$categoryName = self::TEST_CATEGORY;
		$statement = $this->db->prepare('DELETE FROM categories WHERE name = ?');
		$statement->bind_param('s', $categoryName);
		$statement->execute();
		$statement->close();

		$statement = $this->db->prepare('DELETE FROM users_table WHERE email = ?');
		$statement->bind_param('s', $email);
		$statement->execute();
		$statement->close();
	}

	private function getJson(string $path): array
	{
		$ch = curl_init(self::BASE_URL . $path);
		curl_setopt_array($ch, [
			CURLOPT_HTTPGET => true,
			CURLOPT_HTTPHEADER => ['Accept: application/json'],
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
