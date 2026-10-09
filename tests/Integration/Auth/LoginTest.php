<?php

declare(strict_types=1);

final class LoginTest extends BaseTestCase
{
    private const TEST_EMAIL = 'test.login@jobnet.test';
    private const TEST_PASSWORD = 'TestPassword123!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->deleteTestUser();
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser();
        parent::tearDown();
    }

    public function test_issued_jwt_authenticates_seeker_dashboard(): void
    {
        $hashedPassword = password_hash(
            self::TEST_PASSWORD,
            PASSWORD_DEFAULT
        );

        $stmt = $this->db->prepare(
            "INSERT INTO users_table
                (firstname, lastname, email, password, role, suspended)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        $firstname = 'Test';
        $lastname = 'Login';
        $email = self::TEST_EMAIL;
        $role = 'job_seeker';
        $suspended = 0;

        $stmt->bind_param(
            'sssssi',
            $firstname,
            $lastname,
            $email,
            $hashedPassword,
            $role,
            $suspended
        );

        $stmt->execute();
        $stmt->close();

        $login = $this->postJson(
            '/api/auth/login.php',
            [
                'mail' => self::TEST_EMAIL,
                'pword' => self::TEST_PASSWORD,
            ]
        );

        $this->assertSame(200, $login['statusCode']);
        $this->assertNotEmpty($login['body']['token']);

        $dashboard = $this->getJson(
            '/api/dashboard/seeker/seeker_dashboard.php',
            $login['body']['token']
        );

        $this->assertSame(200, $dashboard['statusCode']);
        $this->assertTrue($dashboard['body']['status']);
        $this->assertSame(
            self::TEST_EMAIL,
            $dashboard['body']['user']['email']
        );

        $wrongRole = $this->getJson(
            '/api/dashboard/employer/employer_dashboard.php',
            $login['body']['token']
        );

        $this->assertSame(403, $wrongRole['statusCode']);
        $this->assertFalse($wrongRole['body']['status']);
        $this->assertSame(
            'Access denied. Requires employer role.',
            $wrongRole['body']['message']
        );
    }

    private function deleteTestUser(): void
    {
        $this->cleanupUsersByEmails([self::TEST_EMAIL]);
    }
}