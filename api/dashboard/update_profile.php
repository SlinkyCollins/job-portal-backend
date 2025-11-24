<?php
require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/middleware.php';

$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

$profileData = json_decode(file_get_contents('php://input'));
$fullname = trim($profileData->fullname ?? '');
$phone = $profileData->phone ?? null;
$bio = $profileData->bio ?? null;
$address = $profileData->address ?? null;
$country = $profileData->country ?? null;

// Validation

// List of valid country codes
$validCountries = [
    'AF', 'AX', 'AL', 'DZ', 'AS', 'AD', 'AO', 'AI', 'AQ', 'AG', 'AR', 'AM', 'AW', 'AU', 'AT', 'AZ',
    'BS', 'BH', 'BD', 'BB', 'BY', 'BE', 'BZ', 'BJ', 'BM', 'BT', 'BO', 'BA', 'BW', 'BV', 'BR', 'IO',
    'BN', 'BG', 'BF', 'BI', 'KH', 'CM', 'CA', 'CV', 'KY', 'CF', 'TD', 'CL', 'CN', 'CX', 'CC', 'CO',
    'KM', 'CG', 'CD', 'CK', 'CR', 'CI', 'HR', 'CU', 'CY', 'CZ', 'DK', 'DJ', 'DM', 'DO', 'EC', 'EG',
    'SV', 'GQ', 'ER', 'EE', 'ET', 'FK', 'FO', 'FJ', 'FI', 'FR', 'GF', 'PF', 'TF', 'GA', 'GM', 'GE',
    'DE', 'GH', 'GI', 'GR', 'GL', 'GD', 'GP', 'GU', 'GT', 'GG', 'GN', 'GW', 'GY', 'HT', 'HM', 'VA',
    'HN', 'HK', 'HU', 'IS', 'IN', 'ID', 'IR', 'IQ', 'IE', 'IM', 'IL', 'IT', 'JM', 'JP', 'JE', 'JO',
    'KZ', 'KE', 'KI', 'KP', 'KR', 'KW', 'KG', 'LA', 'LV', 'LB', 'LS', 'LR', 'LY', 'LI', 'LT', 'LU',
    'MO', 'MK', 'MG', 'MW', 'MY', 'MV', 'ML', 'MT', 'MH', 'MQ', 'MR', 'MU', 'YT', 'MX', 'FM', 'MD',
    'MC', 'MN', 'MS', 'MA', 'MZ', 'MM', 'NA', 'NR', 'NP', 'NL', 'AN', 'NC', 'NZ', 'NI', 'NE', 'NG',
    'NU', 'NF', 'MP', 'NO', 'OM', 'PK', 'PW', 'PS', 'PA', 'PG', 'PY', 'PE', 'PH', 'PN', 'PL', 'PT',
    'PR', 'QA', 'RE', 'RO', 'RU', 'RW', 'SH', 'KN', 'LC', 'PM', 'VC', 'WS', 'SM', 'ST', 'SA', 'SN',
    'CS', 'SC', 'SL', 'SG', 'SK', 'SI', 'SB', 'SO', 'ZA', 'GS', 'ES', 'LK', 'SD', 'SR', 'SJ', 'SZ',
    'SE', 'CH', 'SY', 'TW', 'TJ', 'TZ', 'TH', 'TL', 'TG', 'TK', 'TO', 'TT', 'TN', 'TR', 'TM', 'TC',
    'TV', 'UG', 'UA', 'AE', 'GB', 'US', 'UM', 'UY', 'UZ', 'VU', 'VE', 'VN', 'VG', 'VI', 'WF', 'EH',
    'YE', 'ZM', 'ZW'
];

$errors = [];

if (empty($fullname)) {
    $errors[] = 'Full name is required.';
}
if (!preg_match('/\s/', $fullname)) {
    $errors[] = 'Full name must include a first and last name separated by a space.';
}
if (empty($phone)) {
    $errors[] = 'Phone is required.';
}
if (empty($address)) {
    $errors[] = 'Address is required.';
}
if (empty($country)) {
    $errors[] = 'Country is required.';
}
if (!preg_match("/^\+?[0-9]{7,15}$/", $phone)) {
    $errors[] = 'Invalid phone number format.';
}
if (!in_array(strtoupper($country), $validCountries)) {
    $errors[] = 'Invalid country code.';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'errors' => $errors]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Split fullname
    $nameParts = explode(' ', $fullname, 2);
    $firstname = $nameParts[0];
    $lastname = $nameParts[1] ?? '';

    // Update users_table
    $updateUser = $dbconnection->prepare("UPDATE users_table SET firstname = ?, lastname = ? WHERE user_id = ?");
    $updateUser->bind_param('ssi', $firstname, $lastname, $user_id);
    $userSuccess = $updateUser->execute();
    $updateUser->close();

    // Update job_seekers_table
    $updateSeeker = $dbconnection->prepare("UPDATE job_seekers_table SET fullname = ?, phone = ?, bio = ?, address = ?, country = ? WHERE user_id = ?");
    $updateSeeker->bind_param('sssssi', $fullname, $phone, $bio, $address, $country, $user_id);
    $seekerSuccess = $updateSeeker->execute();
    $updateSeeker->close();

    if ($userSuccess && $seekerSuccess) {
        echo json_encode(['status' => true, 'msg' => 'Profile updated successfully.']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => false, 'msg' => 'Profile update failed.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => false, 'msg' => 'Invalid request method.']);
}

$dbconnection->close();
?>