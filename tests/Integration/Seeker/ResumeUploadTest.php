<?php

declare(strict_types=1);

final class ResumeUploadTest extends BaseTestCase
{
	private const SEEKER_EMAIL = 'test.seeker.resume@jobnet.test';
	private const EMPLOYER_EMAIL = 'test.employer.resume@jobnet.test';

	private string $tempPdfPath;
	private string $tempInvalidPath;

	protected function setUp(): void
	{
		parent::setUp();
		$this->deleteTestData();

		// Create temporary test files
		$this->tempPdfPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_resume_' . uniqid() . '.pdf';
		file_put_contents($this->tempPdfPath, "%PDF-1.4\n%Test PDF content for resume upload\n%%EOF");

		$this->tempInvalidPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_image_' . uniqid() . '.png';
		file_put_contents($this->tempInvalidPath, "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR");
	}

	protected function tearDown(): void
	{
		if (file_exists($this->tempPdfPath)) {
			@unlink($this->tempPdfPath);
		}
		if (file_exists($this->tempInvalidPath)) {
			@unlink($this->tempInvalidPath);
		}

		$this->deleteTestData();
		parent::tearDown();
	}

	public function test_seeker_can_successfully_upload_valid_pdf_resume(): void
	{
		$seekerId = $this->createJobSeeker(self::SEEKER_EMAIL);
		$token = $this->loginAndGetToken(self::SEEKER_EMAIL);

		$fields = [
			'file' => new CURLFile($this->tempPdfPath, 'application/pdf', 'my_cv.pdf'),
			'filename' => 'My First Resume',
		];

		$response = $this->postForm(
			'/api/dashboard/seeker/upload_cv.php',
			$fields,
			$token,
			['X-Mock-Cloudinary: true']
		);

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertSame('CV uploaded successfully.', $response['body']['message']);
		$this->assertSame('My First Resume', $response['body']['filename']);
		$this->assertStringContainsString('fl_attachment', $response['body']['url']);
		$this->assertNotEmpty($response['body']['public_id']);

		// Verify database record in job_seekers_table
		$statement = $this->db->prepare(
			'SELECT cv_url, cv_filename, cv_public_id FROM job_seekers_table WHERE user_id = ?'
		);
		$statement->bind_param('i', $seekerId);
		$statement->execute();
		$row = $statement->get_result()->fetch_assoc();
		$statement->close();

		$this->assertIsArray($row);
		$this->assertSame($response['body']['url'], $row['cv_url']);
		$this->assertSame('My First Resume', $row['cv_filename']);
		$this->assertSame($response['body']['public_id'], $row['cv_public_id']);
	}

	public function test_subsequent_upload_replaces_existing_resume_and_public_id(): void
	{
		$seekerId = $this->createJobSeeker(self::SEEKER_EMAIL);

		// Seed initial CV record
		$oldPublicId = 'jobnet/cvs/old_cv_initial.pdf';
		$oldCvUrl = 'https://res.cloudinary.com/jobnet-mock/raw/upload/fl_attachment/' . $oldPublicId;
		$statement = $this->db->prepare(
			'UPDATE job_seekers_table SET cv_url = ?, cv_filename = ?, cv_public_id = ? WHERE user_id = ?'
		);
		$oldFilename = 'Old Initial Resume';
		$statement->bind_param('sssi', $oldCvUrl, $oldFilename, $oldPublicId, $seekerId);
		$statement->execute();
		$statement->close();

		$token = $this->loginAndGetToken(self::SEEKER_EMAIL);

		// Perform second upload with new filename
		$fields = [
			'file' => new CURLFile($this->tempPdfPath, 'application/pdf', 'updated_cv.pdf'),
			'filename' => 'Updated Senior Resume',
		];

		$response = $this->postForm(
			'/api/dashboard/seeker/upload_cv.php',
			$fields,
			$token,
			['X-Mock-Cloudinary: true']
		);

		$this->assertSame(200, $response['statusCode']);
		$this->assertTrue($response['body']['status']);
		$this->assertSame('CV uploaded successfully.', $response['body']['message']);
		$this->assertSame('Updated Senior Resume', $response['body']['filename']);
		$this->assertNotSame($oldPublicId, $response['body']['public_id']);

		// Verify database record updated
		$statement = $this->db->prepare(
			'SELECT cv_url, cv_filename, cv_public_id FROM job_seekers_table WHERE user_id = ?'
		);
		$statement->bind_param('i', $seekerId);
		$statement->execute();
		$row = $statement->get_result()->fetch_assoc();
		$statement->close();

		$this->assertIsArray($row);
		$this->assertSame('Updated Senior Resume', $row['cv_filename']);
		$this->assertSame($response['body']['public_id'], $row['cv_public_id']);
		$this->assertSame($response['body']['url'], $row['cv_url']);
	}

	public function test_invalid_file_type_is_rejected(): void
	{
		$seekerId = $this->createJobSeeker(self::SEEKER_EMAIL);
		$token = $this->loginAndGetToken(self::SEEKER_EMAIL);

		$fields = [
			'file' => new CURLFile($this->tempInvalidPath, 'image/png', 'photo.png'),
			'filename' => 'Image Disguised As Resume',
		];

		$response = $this->postForm(
			'/api/dashboard/seeker/upload_cv.php',
			$fields,
			$token,
			['X-Mock-Cloudinary: true']
		);

		$this->assertSame(400, $response['statusCode']);
		$this->assertFalse($response['body']['status']);
		$this->assertSame('Invalid file type. Only PDF and DOCX allowed.', $response['body']['message']);

		// Verify database was NOT updated
		$statement = $this->db->prepare(
			'SELECT cv_url, cv_filename, cv_public_id FROM job_seekers_table WHERE user_id = ?'
		);
		$statement->bind_param('i', $seekerId);
		$statement->execute();
		$row = $statement->get_result()->fetch_assoc();
		$statement->close();

		$this->assertIsArray($row);
		$this->assertNull($row['cv_public_id']);
	}

	public function test_missing_filename_is_rejected(): void
	{
		$this->createJobSeeker(self::SEEKER_EMAIL);
		$token = $this->loginAndGetToken(self::SEEKER_EMAIL);

		// Omit filename field
		$fields = [
			'file' => new CURLFile($this->tempPdfPath, 'application/pdf', 'my_cv.pdf'),
			'filename' => '',
		];

		$response = $this->postForm(
			'/api/dashboard/seeker/upload_cv.php',
			$fields,
			$token,
			['X-Mock-Cloudinary: true']
		);

		$this->assertSame(400, $response['statusCode']);
		$this->assertFalse($response['body']['status']);
		$this->assertSame('Filename is required.', $response['body']['message']);
	}

	public function test_unauthenticated_request_is_rejected(): void
	{
		$fields = [
			'file' => new CURLFile($this->tempPdfPath, 'application/pdf', 'my_cv.pdf'),
			'filename' => 'Unauthenticated Resume',
		];

		$response = $this->postForm(
			'/api/dashboard/seeker/upload_cv.php',
			$fields,
			null,
			['X-Mock-Cloudinary: true']
		);

		$this->assertSame(401, $response['statusCode']);
		$this->assertFalse($response['body']['status']);
	}

	public function test_employer_role_cannot_upload_resume(): void
	{
		$this->createEmployer(self::EMPLOYER_EMAIL);
		$token = $this->loginAndGetToken(self::EMPLOYER_EMAIL);

		$fields = [
			'file' => new CURLFile($this->tempPdfPath, 'application/pdf', 'my_cv.pdf'),
			'filename' => 'Employer Trying To Upload',
		];

		$response = $this->postForm(
			'/api/dashboard/seeker/upload_cv.php',
			$fields,
			$token,
			['X-Mock-Cloudinary: true']
		);

		$this->assertSame(403, $response['statusCode']);
		$this->assertFalse($response['body']['status']);
	}

	private function deleteTestData(): void
	{
		$this->cleanupUsersByEmails([
			self::SEEKER_EMAIL,
			self::EMPLOYER_EMAIL,
		]);
	}
}
