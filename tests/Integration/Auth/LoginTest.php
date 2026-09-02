<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LoginTest extends TestCase
{
    private const BASE_URL = 'http://localhost/JobPortal';
    private const TEST_EMAIL = 'test.login@jobnet.test';
    private const TEST_PASSWORD = 'TestPassword123!';

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

        fwrite(STDOUT, "ENV: " . ($_ENV['ENV'] ?? 'not set') . PHP_EOL);
        fwrite(STDOUT, "DB: " . ($_ENV['DB_NAME_TEST'] ?? 'not set') . PHP_EOL);
        fwrite(STDOUT, "Connected DB: " . $this->db->query("SELECT DATABASE()")->fetch_row()[0] . PHP_EOL);

        if ($this->db->connect_error) {
            $this->fail(
                'Could not connect to test database: ' .
                $this->db->connect_error
            );
        }

        // Ensure the test starts from a known state.
        $this->db->query(
            "DELETE FROM users_table WHERE email = '" .
            self::TEST_EMAIL .
            "'"
        );
    }

    protected function tearDown(): void
    {
        // Remove the test user after each test.
        $this->db->query(
            "DELETE FROM users_table WHERE email = '" .
            self::TEST_EMAIL .
            "'"
        );

        $this->db->close();

        parent::tearDown();
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        // Arrange
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

        $userId = $this->db->insert_id;

        $stmt->close();

        // Act
        $response = $this->postJson(
            '/api/auth/login.php',
            [
                'mail' => self::TEST_EMAIL,
                'pword' => self::TEST_PASSWORD,
            ]
        );

        // Assert
        $this->assertSame(200, $response['statusCode']);

        $this->assertTrue($response['body']['status']);

        $this->assertSame(
            'Login successful.',
            $response['body']['message']
        );

        $this->assertArrayHasKey(
            'token',
            $response['body']
        );

        $this->assertNotEmpty(
            $response['body']['token']
        );

        $this->assertSame(
            $userId,
            $response['body']['user']['user_id']
        );

        $this->assertSame(
            'job_seeker',
            $response['body']['user']['role']
        );

        $this->assertSame(
            self::TEST_EMAIL,
            $response['body']['user']['email']
        );
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        // Arrange
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

        // Act
        $response = $this->postJson(
            '/api/auth/login.php',
            [
                'mail' => self::TEST_EMAIL,
                'pword' => 'WrongPassword123!',
            ]
        );

        // Assert
        $this->assertSame(401, $response['statusCode']);

        $this->assertFalse($response['body']['status']);

        $this->assertSame(
            'Incorrect password.',
            $response['body']['message']
        );
    }

    public function test_user_cannot_login_with_nonexistent_email(): void
    {
        // Act
        $response = $this->postJson(
            '/api/auth/login.php',
            [
                'mail' => 'randomUser@gmail.com',
                'pword' => 'randompassword123',
            ]
        );

        // Assert
        $this->assertSame(404, $response['statusCode']);

        $this->assertFalse($response['body']['status']);

        $this->assertSame(
            'User not found. Please try signing up.',
            $response['body']['message']
        );
    }

    public function test_suspended_user_cannot_login(): void
    {
        // Arrange
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
        $suspended = 1;

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

        // Act 
        $response = $this->postJson(
            '/api/auth/login.php',
            [
                'mail' => self::TEST_EMAIL,
                'pword' => self::TEST_PASSWORD,
            ]
        );

        // Assert
        $this->assertSame(403, $response['statusCode']);

        $this->assertFalse($response['body']['status']);

        $this->assertSame(
            'Your account has been suspended. Please contact support.',
            $response['body']['message']
        );
    }

    public function test_user_cannot_login_with_invalid_email_format(): void
    {
        $response = $this->postJson(
            '/api/auth/login.php',
            [
                'mail' => 'invalid-email',
                'pword' => self::TEST_PASSWORD,
            ]
        );

        $this->assertSame(400, $response['statusCode']);
        $this->assertFalse($response['body']['status']);
        $this->assertSame(
            'Validation failed.',
            $response['body']['message']
        );
    }

    public function test_user_cannot_login_with_empty_credentials(): void
    {
        $response = $this->postJson(
            '/api/auth/login.php',
            [
                'mail' => '',
                'pword' => '',
            ]
        );

        $this->assertSame(400, $response['statusCode']);
        $this->assertFalse($response['body']['status']);
        $this->assertSame(
            'Validation failed.',
            $response['body']['message']
        );

        $this->assertArrayHasKey('errors', $response['body']);
    }

    private function postJson(string $path, array $payload): array
    {
        $ch = curl_init(self::BASE_URL . $path);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $body = curl_exec($ch);

        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);

            $this->fail(
                'HTTP request failed: ' . $error
            );
        }

        $statusCode = curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        echo "\nURL: " . self::BASE_URL . $path . "\n";
        echo "HTTP Status: " . $statusCode . "\n";
        echo "Response: " . $body . "\n";

        curl_close($ch);

        $decodedBody = json_decode(
            $body,
            true
        );

        $this->assertIsArray(
            $decodedBody,
            'API response was not valid JSON.'
        );

        return [
            'statusCode' => $statusCode,
            'body' => $decodedBody,
        ];
    }
}