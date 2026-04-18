<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/Validator.php';
require_once __DIR__ . '/../../../config/api_response.php';
require_once __DIR__ . '/../../../config/config_helper.php';

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

$validCountries = appConfig('validation.valid_countries', []);
if (!is_array($validCountries) || count($validCountries) === 0) {
    apiResponse(false, 'Country validation config is missing or invalid.', 500);
    exit;
}

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
