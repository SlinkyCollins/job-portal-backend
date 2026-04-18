<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/Validator.php';
require_once __DIR__ . '/../../../config/api_response.php';

$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

$profileData = json_decode(file_get_contents('php://input'));
$fullname = trim($profileData->fullname ?? '');
$phone = $profileData->phone ?? null;
$bio = $profileData->bio ?? null;
$address = $profileData->address ?? null;
$country = $profileData->country ?? null;
$linked_providers = $profileData->linked_providers ?? null;

// Check if this is a partial update for linked_providers only (when linking accounts)
$isPartialUpdate = $linked_providers !== null && empty($fullname) && empty($phone) && empty($address) && empty($country);

if ($isPartialUpdate) {
    $partialValidator = new Validator([
        'linked_providers' => $linked_providers,
    ]);
    $partialValidator->rule('linked_providers', 'required|json');

    if (!$partialValidator->validate()) {
        apiResponse(false, $partialValidator->firstError(), 400);
        exit;
    }

    // Update users_table for linked_providers
    $updateUser = $dbconnection->prepare("UPDATE users_table SET linked_providers = ? WHERE user_id = ?");
    $updateUser->bind_param('si', $linked_providers, $user_id);
    if ($updateUser->execute()) {
        apiResponse(true, 'Linked providers updated successfully.', 200);
    } else {
        apiResponse(false, 'Failed to update linked providers.', 500);
    }
    $updateUser->close();
    $dbconnection->close();
    exit;
}

// List of valid country codes
$validCountries = [
    'AF',
    'AX',
    'AL',
    'DZ',
    'AS',
    'AD',
    'AO',
    'AI',
    'AQ',
    'AG',
    'AR',
    'AM',
    'AW',
    'AU',
    'AT',
    'AZ',
    'BS',
    'BH',
    'BD',
    'BB',
    'BY',
    'BE',
    'BZ',
    'BJ',
    'BM',
    'BT',
    'BO',
    'BA',
    'BW',
    'BV',
    'BR',
    'IO',
    'BN',
    'BG',
    'BF',
    'BI',
    'KH',
    'CM',
    'CA',
    'CV',
    'KY',
    'CF',
    'TD',
    'CL',
    'CN',
    'CX',
    'CC',
    'CO',
    'KM',
    'CG',
    'CD',
    'CK',
    'CR',
    'CI',
    'HR',
    'CU',
    'CY',
    'CZ',
    'DK',
    'DJ',
    'DM',
    'DO',
    'EC',
    'EG',
    'SV',
    'GQ',
    'ER',
    'EE',
    'ET',
    'FK',
    'FO',
    'FJ',
    'FI',
    'FR',
    'GF',
    'PF',
    'TF',
    'GA',
    'GM',
    'GE',
    'DE',
    'GH',
    'GI',
    'GR',
    'GL',
    'GD',
    'GP',
    'GU',
    'GT',
    'GG',
    'GN',
    'GW',
    'GY',
    'HT',
    'HM',
    'VA',
    'HN',
    'HK',
    'HU',
    'IS',
    'IN',
    'ID',
    'IR',
    'IQ',
    'IE',
    'IM',
    'IL',
    'IT',
    'JM',
    'JP',
    'JE',
    'JO',
    'KZ',
    'KE',
    'KI',
    'KP',
    'KR',
    'KW',
    'KG',
    'LA',
    'LV',
    'LB',
    'LS',
    'LR',
    'LY',
    'LI',
    'LT',
    'LU',
    'MO',
    'MK',
    'MG',
    'MW',
    'MY',
    'MV',
    'ML',
    'MT',
    'MH',
    'MQ',
    'MR',
    'MU',
    'YT',
    'MX',
    'FM',
    'MD',
    'MC',
    'MN',
    'MS',
    'MA',
    'MZ',
    'MM',
    'NA',
    'NR',
    'NP',
    'NL',
    'AN',
    'NC',
    'NZ',
    'NI',
    'NE',
    'NG',
    'NU',
    'NF',
    'MP',
    'NO',
    'OM',
    'PK',
    'PW',
    'PS',
    'PA',
    'PG',
    'PY',
    'PE',
    'PH',
    'PN',
    'PL',
    'PT',
    'PR',
    'QA',
    'RE',
    'RO',
    'RU',
    'RW',
    'SH',
    'KN',
    'LC',
    'PM',
    'VC',
    'WS',
    'SM',
    'ST',
    'SA',
    'SN',
    'CS',
    'SC',
    'SL',
    'SG',
    'SK',
    'SI',
    'SB',
    'SO',
    'ZA',
    'GS',
    'ES',
    'LK',
    'SD',
    'SR',
    'SJ',
    'SZ',
    'SE',
    'CH',
    'SY',
    'TW',
    'TJ',
    'TZ',
    'TH',
    'TL',
    'TG',
    'TK',
    'TO',
    'TT',
    'TN',
    'TR',
    'TM',
    'TC',
    'TV',
    'UG',
    'UA',
    'AE',
    'GB',
    'US',
    'UM',
    'UY',
    'UZ',
    'VU',
    'VE',
    'VN',
    'VG',
    'VI',
    'WF',
    'EH',
    'YE',
    'ZM',
    'ZW'
];

$validator = new Validator([
    'fullname' => $fullname,
    'phone' => $phone,
    'address' => $address,
    'country' => $country,
    'linked_providers' => $linked_providers,
]);

$validator->rule('fullname', 'required');
$validator->rule('phone', 'required|regex:/^\+?[0-9]{7,15}$/');
$validator->rule('address', 'required');
$validator->rule('country', 'required');
$validator->rule('linked_providers', 'nullable|json');
$validator->after(function (Validator $validator) {
    $fullnameValue = (string) $validator->value('fullname', '');

    if (!$validator->hasError('fullname') && !preg_match('/\s/', $fullnameValue)) {
        $validator->addError('fullname', 'Full name must include a first and last name separated by a space.');
    }
});
$validator->after(function (Validator $validator) use ($validCountries) {
    $countryValue = strtoupper((string) $validator->value('country', ''));

    if (!$validator->hasError('country') && !in_array($countryValue, $validCountries, true)) {
        $validator->addError('country', 'Invalid country code.');
    }
});

if (!$validator->validate()) {
    apiResponse(false, 'Validation failed.', 400, [], $validator->errors());
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Split fullname
    $nameParts = explode(' ', $fullname, 2);
    $firstname = $nameParts[0];
    $lastname = $nameParts[1] ?? '';

    // Start transaction
    $dbconnection->begin_transaction();

    // Update users_table: Update firstname/lastname 
    $updateUser = $dbconnection->prepare("UPDATE users_table SET firstname = ?, lastname = ? WHERE user_id = ?");
    $updateUser->bind_param('ssi', $firstname, $lastname, $user_id);
    $userSuccess = $updateUser->execute();
    $updateUser->close();

    // Update job_seekers_table for profile fields
    $updateSeeker = $dbconnection->prepare("UPDATE job_seekers_table SET fullname = ?, phone = ?, bio = ?, address = ?, country = ? WHERE user_id = ?");
    $updateSeeker->bind_param('sssssi', $fullname, $phone, $bio, $address, $country, $user_id);
    $seekerSuccess = $updateSeeker->execute();
    $updateSeeker->close();

    // Commit or rollback
    if ($userSuccess && $seekerSuccess) {
        $dbconnection->commit();
        apiResponse(true, 'Profile updated successfully.');
    } else {
        $dbconnection->rollback();
        apiResponse(false, 'Profile update failed.', 500);
    }
} else {
    apiResponse(false, 'Invalid request method.', 405);
}

$dbconnection->close();
?>
