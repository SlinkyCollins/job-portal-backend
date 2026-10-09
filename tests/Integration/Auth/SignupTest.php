<?php

declare(strict_types=1);

final class SignupTest extends BaseTestCase
{
    private const TEST_EMAIL = 'test.signup@jobnet.test';
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

    public function test_user_can_signup_as_job_seeker(): void
    {
        $firstname = 'Test';
        $lastname = 'Signup';
        $email = self::TEST_EMAIL;
        $password = self::TEST_PASSWORD;
        $role = 'job_seeker';
        $terms = 1;

        $response = $this->postJson(
            '/api/auth/signup.php',
            [
                'fname' => $firstname,
                'lname' => $lastname,
                'mail' => $email,
                'pword' => $password,
                'role' => $role,
                'terms' => $terms,
            ]
        );

        $this->assertSame(
            201,
            $response['statusCode'],
            'Expected HTTP status code 201 for successful signup.'
        );

        $this->assertTrue($response['body']['status']);

        $this->assertSame(
            'User signed up successfully.',
            $response['body']['message']
        );

        $result = $this->db->query(
            "SELECT u.user_id, u.password, js.user_id AS seeker_user_id
             FROM users_table u
             INNER JOIN job_seekers_table js ON js.user_id = u.user_id
             WHERE u.email = '$email' AND u.role = '$role'"
        );

        $user = $result->fetch_assoc();

        $this->assertIsArray($user, 'Expected user and job seeker records to exist.');
        $this->assertSame($user['user_id'], $user['seeker_user_id']);
        $this->assertNotSame($password, $user['password']);
    }

    public function test_user_can_signup_as_employer(): void
    {
        $firstname = 'Test';
        $lastname = 'Signup';
        $email = self::TEST_EMAIL;
        $password = self::TEST_PASSWORD;
        $role = 'employer';
        $terms = 1;

        $response = $this->postJson(
            '/api/auth/signup.php',
            [
                'fname' => $firstname,
                'lname' => $lastname,
                'mail' => $email,
                'pword' => $password,
                'role' => $role,
                'terms' => $terms,
            ]
        );

        $this->assertSame(
            201,
            $response['statusCode'],
            'Expected HTTP status code 201 for successful signup.'
        );

        $this->assertTrue($response['body']['status']);

        $this->assertSame(
            'User signed up successfully.',
            $response['body']['message']
        );

        $result = $this->db->query(
            "SELECT u.user_id, u.password, e.user_id AS employer_user_id
             FROM users_table u
             INNER JOIN employers_table e ON e.user_id = u.user_id
             WHERE u.email = '$email' AND u.role = '$role'"
        );

        $user = $result->fetch_assoc();

        $this->assertIsArray($user, 'Expected user and employer records to exist.');
        $this->assertSame($user['user_id'], $user['employer_user_id']);
        $this->assertNotSame($password, $user['password']);
    }

    public function test_user_cannot_signup_with_invalid_data(): void
    {
        $firstname = '';
        $lastname = 'Signup';
        $email = '2.mailcom';
        $password = self::TEST_PASSWORD;
        $role = 'employer';
        $terms = 1;

        $response = $this->postJson(
            '/api/auth/signup.php',
            [
                'fname' => $firstname,
                'lname' => $lastname,
                'mail' => $email,
                'pword' => $password,
                'role' => $role,
                'terms' => $terms,
            ]
        );

        $this->assertSame(400, $response['statusCode'], 'Expected HTTP status code 400 for validation failure.');
        $this->assertFalse($response['body']['status']);
        $this->assertSame(
            'Validation failed.',
            $response['body']['message']
        );

        $result = $this->db->query(
            "SELECT user_id FROM users_table WHERE email = '" . $email . "'"
        );

        $this->assertNull($result->fetch_assoc(), 'Expected user record to not exist.');
    }

    public function test_user_cannot_signup_with_existing_email(): void
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

        $response = $this->postJson(
            '/api/auth/signup.php',
            [
                'fname' => $firstname,
                'lname' => $lastname,
                'mail' => self::TEST_EMAIL,
                'pword' => self::TEST_PASSWORD,
                'role' => $role,
                'terms' => 1,
            ]
        );

        $this->assertSame(
            409,
            $response['statusCode'],
            'Expected HTTP status code 409 for existing email.'
        );

        $this->assertFalse($response['body']['status']);
        $this->assertSame(
            'Email already exists.',
            $response['body']['message']
        );

        $resultCount = $this->db->query(
            "SELECT COUNT(*) as count FROM users_table WHERE email = '" . self::TEST_EMAIL . "'"
        );

        $userCount = (int) $resultCount->fetch_assoc()['count'];

        $this->assertSame(
            1,
            $userCount,
            'Expected exactly one user record; duplicate signup must not create a second user.'
        );
    }

    public function test_user_cannot_signup_with_social_account_email(): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO users_table
                (firstname, lastname, email, password, role, google_id, terms_accepted)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $firstname = 'Test';
        $lastname = 'Login';
        $email = self::TEST_EMAIL;
        $password = null;
        $role = 'job_seeker';
        $googleId = '6rXy4AnZeRTKbPKlR194o68wC9X2';
        $terms = 1;

        $stmt->bind_param(
            'ssssssi',
            $firstname,
            $lastname,
            $email,
            $password,
            $role,
            $googleId,
            $terms
        );

        $stmt->execute();
        $stmt->close();

        $response = $this->postJson(
            '/api/auth/signup.php',
            [
                'fname' => $firstname,
                'lname' => $lastname,
                'mail' => self::TEST_EMAIL,
                'pword' => self::TEST_PASSWORD,
                'role' => $role,
                'terms' => $terms,
            ]
        );

        $this->assertSame(
            409,
            $response['statusCode'],
            'Expected HTTP status code 409 for existing email.'
        );

        $this->assertFalse($response['body']['status']);
        $this->assertSame(
            'This email is already registered via Google/Facebook. Please log in using your social account or contact support to add a password.',
            $response['body']['message']
        );

        $resultCount = $this->db->query(
            "SELECT COUNT(*) as count FROM users_table WHERE email = '" . self::TEST_EMAIL . "'"
        );

        $userCount = (int) $resultCount->fetch_assoc()['count'];

        $this->assertSame(
            1,
            $userCount,
            'Expected exactly one user record; duplicate signup must not create a second user.'
        );
    }

    private function deleteTestUser(): void
    {
        $this->cleanupUsersByEmails([self::TEST_EMAIL]);
    }
}