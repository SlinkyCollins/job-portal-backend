<?php

declare(strict_types=1);

final class CompanyProfileTest extends BaseTestCase
{
	private const EMPLOYER_1_EMAIL = 'test.company.employer1@jobnet.test';
	private const EMPLOYER_2_EMAIL = 'test.company.employer2@jobnet.test';
	private const SEEKER_EMAIL = 'test.company.seeker@jobnet.test';

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

	public function test_employer_can_update_existing_company_profile(): void
	{
		$employerId = $this->createEmployer(self::EMPLOYER_1_EMAIL);
		$companyId = $this->createCompany($employerId, 'Old Company Name', 'Lagos', 'https://old.test', 'Old description.');
		$this->linkCompanyToEmployer($employerId, $companyId);

		$token = $this->loginAndGetToken(self::EMPLOYER_1_EMAIL);

		$updatedData = [
			'name' => 'Acme Technologies Ltd',
			'location' => 'Abuja, Nigeria',
			'website' => 'https://acme.test',
			'description' => 'A premier engineering and cloud consultancy firm.',
		];

		// Submit company update via postForm
		$response = $this->postForm('/api/dashboard/employer/save_company_profile.php', $updatedData, $token);

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertSame('Company profile updated successfully.', $response['body']['message']);

		// Verify database state
		$stmt = $this->db->prepare('SELECT name, location, website, description FROM companies WHERE id = ?');
		$stmt->bind_param('i', $companyId);
		$stmt->execute();
		$dbCompany = $stmt->get_result()->fetch_assoc();
		$stmt->close();

		$this->assertNotNull($dbCompany);
		$this->assertSame('Acme Technologies Ltd', $dbCompany['name']);
		$this->assertSame('Abuja, Nigeria', $dbCompany['location']);
		$this->assertSame('https://acme.test', $dbCompany['website']);
		$this->assertSame('A premier engineering and cloud consultancy firm.', $dbCompany['description']);

		// Verify retrieval via get_company_profile.php
		$getResponse = $this->getJson('/api/dashboard/employer/get_company_profile.php', $token);
		$this->assertSame(200, $getResponse['statusCode']);
		$this->assertTrue($getResponse['body']['status']);
		$this->assertTrue($getResponse['body']['has_company']);
		$this->assertSame('Acme Technologies Ltd', $getResponse['body']['data']['name']);
		$this->assertSame('Abuja, Nigeria', $getResponse['body']['data']['location']);
		$this->assertSame('https://acme.test', $getResponse['body']['data']['website']);
		$this->assertSame('A premier engineering and cloud consultancy firm.', $getResponse['body']['data']['description']);
	}

	public function test_company_update_strictly_updates_authenticated_employer_and_does_not_affect_another_employer(): void
	{
		// Employer 1 with Company 1
		$employer1Id = $this->createEmployer(self::EMPLOYER_1_EMAIL);
		$company1Id = $this->createCompany($employer1Id, 'Alpha Logistics', 'Lagos', 'https://alpha.test', 'Alpha description');
		$this->linkCompanyToEmployer($employer1Id, $company1Id);

		// Employer 2 with Company 2
		$employer2Id = $this->createEmployer(self::EMPLOYER_2_EMAIL);
		$company2Id = $this->createCompany($employer2Id, 'Beta Financials', 'Port Harcourt', 'https://beta.test', 'Beta description');
		$this->linkCompanyToEmployer($employer2Id, $company2Id);

		$token1 = $this->loginAndGetToken(self::EMPLOYER_1_EMAIL);
		$token2 = $this->loginAndGetToken(self::EMPLOYER_2_EMAIL);

		// Employer 1 updates their company
		$updateResponse = $this->postForm('/api/dashboard/employer/save_company_profile.php', [
			'name' => 'Alpha Global Freight',
			'location' => 'Kano',
			'website' => 'https://alphaglobal.test',
			'description' => 'Updated Alpha description',
		], $token1);

		$this->assertSame(200, $updateResponse['statusCode']);
		$this->assertTrue($updateResponse['body']['status']);

		// Verify Company 1 in DB was updated
		$stmt1 = $this->db->prepare('SELECT name, location FROM companies WHERE id = ?');
		$stmt1->bind_param('i', $company1Id);
		$stmt1->execute();
		$company1 = $stmt1->get_result()->fetch_assoc();
		$stmt1->close();
		$this->assertSame('Alpha Global Freight', $company1['name']);
		$this->assertSame('Kano', $company1['location']);

		// Verify Company 2 in DB was NOT changed
		$stmt2 = $this->db->prepare('SELECT name, location, website, description FROM companies WHERE id = ?');
		$stmt2->bind_param('i', $company2Id);
		$stmt2->execute();
		$company2 = $stmt2->get_result()->fetch_assoc();
		$stmt2->close();
		$this->assertSame('Beta Financials', $company2['name'], 'Employer 2 company name must remain unchanged.');
		$this->assertSame('Port Harcourt', $company2['location'], 'Employer 2 location must remain unchanged.');
		$this->assertSame('https://beta.test', $company2['website']);
		$this->assertSame('Beta description', $company2['description']);

		// Verify Employer 2 retrieval returns their own original company data
		$getResponse2 = $this->getJson('/api/dashboard/employer/get_company_profile.php', $token2);
		$this->assertSame(200, $getResponse2['statusCode']);
		$this->assertSame('Beta Financials', $getResponse2['body']['data']['name']);
	}

	public function test_employer_without_company_can_create_new_company_profile(): void
	{
		// Create employer with no company attached
		$employerId = $this->createEmployer(self::EMPLOYER_1_EMAIL);
		$token = $this->loginAndGetToken(self::EMPLOYER_1_EMAIL);

		// Verify initial state has no company
		$initGet = $this->getJson('/api/dashboard/employer/get_company_profile.php', $token);
		$this->assertSame(200, $initGet['statusCode']);
		$this->assertFalse($initGet['body']['has_company']);
		$this->assertNull($initGet['body']['data']);

		// Create company via save_company_profile.php
		$createResponse = $this->postForm('/api/dashboard/employer/save_company_profile.php', [
			'name' => 'Brand New Startup',
			'location' => 'Ibadan, Nigeria',
			'website' => 'https://newstartup.test',
			'description' => 'A fast-growing fintech startup.',
		], $token);

		$this->assertSame(200, $createResponse['statusCode']);
		$this->assertTrue($createResponse['body']['status']);
		$this->assertSame('Company profile created successfully.', $createResponse['body']['message']);

		// Verify company record was created in DB and linked to employer
		$stmt = $this->db->prepare(
			'SELECT c.id, c.name, e.company_id
			 FROM companies c
			 JOIN employers_table e ON e.user_id = c.user_id
			 WHERE c.user_id = ?'
		);
		$stmt->bind_param('i', $employerId);
		$stmt->execute();
		$record = $stmt->get_result()->fetch_assoc();
		$stmt->close();

		$this->assertNotNull($record);
		$this->assertSame('Brand New Startup', $record['name']);
		$this->assertSame((int) $record['id'], (int) $record['company_id'], 'Company ID must be linked in employers_table.');
	}

	public function test_company_profile_update_validates_required_fields(): void
	{
		$employerId = $this->createEmployer(self::EMPLOYER_1_EMAIL);
		$companyId = $this->createCompany($employerId, 'Original Co', 'Lagos', 'https://orig.test', 'Original desc.');
		$this->linkCompanyToEmployer($employerId, $companyId);
		$token = $this->loginAndGetToken(self::EMPLOYER_1_EMAIL);

		// Missing name
		$noNameResponse = $this->postForm('/api/dashboard/employer/save_company_profile.php', [
			'name' => '',
			'location' => 'Lagos',
			'description' => 'Valid description',
		], $token);
		$this->assertSame(400, $noNameResponse['statusCode']);
		$this->assertFalse($noNameResponse['body']['status']);
		$this->assertSame('Company name, location, and description are required.', $noNameResponse['body']['message']);

		// Missing location
		$noLocationResponse = $this->postForm('/api/dashboard/employer/save_company_profile.php', [
			'name' => 'Valid Name',
			'location' => '',
			'description' => 'Valid description',
		], $token);
		$this->assertSame(400, $noLocationResponse['statusCode']);
		$this->assertFalse($noLocationResponse['body']['status']);
		$this->assertSame('Company name, location, and description are required.', $noLocationResponse['body']['message']);

		// Missing description
		$noDescResponse = $this->postForm('/api/dashboard/employer/save_company_profile.php', [
			'name' => 'Valid Name',
			'location' => 'Valid Location',
			'description' => '',
		], $token);
		$this->assertSame(400, $noDescResponse['statusCode']);
		$this->assertFalse($noDescResponse['body']['status']);
		$this->assertSame('Company name, location, and description are required.', $noDescResponse['body']['message']);

		// Verify database was untouched
		$stmt = $this->db->prepare('SELECT name FROM companies WHERE id = ?');
		$stmt->bind_param('i', $companyId);
		$stmt->execute();
		$current = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		$this->assertSame('Original Co', $current['name']);
	}

	public function test_unauthenticated_and_non_employer_roles_are_rejected(): void
	{
		// Unauthenticated GET company profile -> 401
		$unauthGet = $this->getJson('/api/dashboard/employer/get_company_profile.php');
		$this->assertSame(401, $unauthGet['statusCode']);

		// Unauthenticated POST save company profile -> 401
		$unauthPost = $this->postForm('/api/dashboard/employer/save_company_profile.php', [
			'name' => 'Acme',
			'location' => 'Lagos',
			'description' => 'Desc',
		]);
		$this->assertSame(401, $unauthPost['statusCode']);

		// Seeker role attempting employer company profile endpoints -> 403
		$this->createJobSeeker(self::SEEKER_EMAIL);
		$seekerToken = $this->loginAndGetToken(self::SEEKER_EMAIL);

		$seekerGet = $this->getJson('/api/dashboard/employer/get_company_profile.php', $seekerToken);
		$this->assertSame(403, $seekerGet['statusCode']);

		$seekerPost = $this->postForm('/api/dashboard/employer/save_company_profile.php', [
			'name' => 'Acme',
			'location' => 'Lagos',
			'description' => 'Desc',
		], $seekerToken);
		$this->assertSame(403, $seekerPost['statusCode']);
	}

	private function deleteTestData(): void
	{
		$this->cleanupUsersByEmails([
			self::EMPLOYER_1_EMAIL,
			self::EMPLOYER_2_EMAIL,
			self::SEEKER_EMAIL,
		]);
	}
}
