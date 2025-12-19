<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/cloudinary.php';

// 1. Validate JWT (Employer Role)
$user = validateJWT('employer');
$user_id = $user['user_id'];

// 2. Get POST Data
$name = $_POST['name'] ?? '';
$location = $_POST['location'] ?? '';
$website = $_POST['website'] ?? '';
$description = $_POST['description'] ?? '';

// 3. Basic Validation
if (empty($name) || empty($location) || empty($description)) {
    echo json_encode(['status' => false, 'message' => 'Company Name, Location, and Description are required.']);
    exit;
}

// 4. Handle Logo Upload (Cloudinary)
$logo_url = null;
$logo_public_id = null;

if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['logo'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['status' => false, 'message' => 'Invalid file type. Only JPG and PNG allowed.']);
        exit;
    }
    if ($file['size'] > $maxSize) {
        echo json_encode(['status' => false, 'message' => 'File too large. Max 2MB.']);
        exit;
    }

    try {
        // CHECK FOR EXISTING PHOTO AND DELETE IF EXISTS
        $checkQuery = "SELECT logo_public_id FROM companies WHERE user_id = ?";
        $stmt = $dbconnection->prepare($checkQuery);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result->fetch_assoc();
        $stmt->close();

        // If exists, delete from Cloudinary
        if ($existing && !empty($existing['logo_public_id'])) {
            try {
                $cloudinary->uploadApi()->destroy($existing['logo_public_id'], ['resource_type' => 'image']);
            } catch (Exception $e) {
                // Continue even if delete fails (don't block the new upload)
                error_log("Failed to delete old image: " . $e->getMessage());
            }
        }

        // Upload to Cloudinary
        $uploadResult = $cloudinary->uploadApi()->upload($file['tmp_name'], [
            'folder' => 'jobnet/company_logos',
            'resource_type' => 'image',
            'public_id' => 'company_' . $user_id . '_' . time(),
            'overwrite' => true
        ]);
        $logo_url = $uploadResult['secure_url'];
        $logo_public_id = $uploadResult['public_id'];
    } catch (Exception $e) {
        echo json_encode(['status' => false, 'message' => 'Logo upload failed: ' . $e->getMessage()]);
        exit;
    }
}

// 5. Database Operations (MySQLi)
try {
    // Check if company exists for this user
    $checkQuery = "SELECT id FROM companies WHERE user_id = ?";
    $stmt = $dbconnection->prepare($checkQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingCompany = $result->fetch_assoc();
    $stmt->close();

    if ($existingCompany) {
        // --- UPDATE EXISTING COMPANY ---
        $company_id = $existingCompany['id'];

        if ($logo_url) {
            $updateQuery = "UPDATE companies SET name=?, location=?, website=?, description=?, logo_url=?, logo_public_id=? WHERE id=?";
            $stmt = $dbconnection->prepare($updateQuery);
            $stmt->bind_param("ssssssi", $name, $location, $website, $description, $logo_url, $logo_public_id, $company_id);
        } else {
            $updateQuery = "UPDATE companies SET name=?, location=?, website=?, description=? WHERE id=?";
            $stmt = $dbconnection->prepare($updateQuery);
            $stmt->bind_param("ssssi", $name, $location, $website, $description, $company_id);
        }

        if ($stmt->execute()) {
            echo json_encode(['status' => true, 'message' => 'Company profile updated successfully.']);
        } else {
            throw new Exception("Update failed: " . $stmt->error);
        }
        $stmt->close();

    } else {
        // --- CREATE NEW COMPANY ---
        $dbconnection->begin_transaction();

        try {
            $insertQuery = "INSERT INTO companies (name, location, website, description, user_id, logo_url, logo_public_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $dbconnection->prepare($insertQuery);
            $stmt->bind_param("ssssiss", $name, $location, $website, $description, $user_id, $logo_url, $logo_public_id);

            if (!$stmt->execute()) {
                throw new Exception("Insert failed: " . $stmt->error);
            }

            $new_company_id = $dbconnection->insert_id;
            $stmt->close();

            // Link new company to employers_table
            $linkQuery = "UPDATE employers_table SET company_id = ? WHERE user_id = ?";
            $linkStmt = $dbconnection->prepare($linkQuery);
            $linkStmt->bind_param("ii", $new_company_id, $user_id);

            if (!$linkStmt->execute()) {
                throw new Exception("Linking failed: " . $linkStmt->error);
            }
            $linkStmt->close();

            $dbconnection->commit();
            echo json_encode(['status' => true, 'message' => 'Company profile created successfully.']);

        } catch (Exception $e) {
            $dbconnection->rollback();
            throw $e;
        }
    }

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$dbconnection->close();
?>