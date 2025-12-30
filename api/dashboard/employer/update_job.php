<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

$user = validateJWT('employer');
$user_id = $user['user_id'];

$data = json_decode(file_get_contents("php://input"));

if (empty($data->job_id) || empty($data->title)) {
    echo json_encode(["status" => false, "message" => "Missing required fields"]);
    exit;
}

try {
    // 1. Update Main Job Details
    $query = "UPDATE jobs_table SET 
                title=?, category_id=?, employment_type=?, location=?, 
                salary_amount=?, currency=?, salary_duration=?, 
                experience_level=?, english_fluency=?, overview=?, 
                description=?, responsibilities=?, requirements=?, 
                nice_to_have=?, benefits=?, deadline=?
              WHERE job_id=? AND employer_id=?";

    $stmt = $dbconnection->prepare($query);

    // sissdsssssssssssii
    $types = "sissdsssssssssssii";

    $stmt->bind_param(
        $types,
        $data->title,
        $data->category_id,
        $data->employment_type,
        $data->location,
        $data->salary_amount,
        $data->currency,
        $data->salary_duration,
        $data->experience_level,
        $data->english_fluency,
        $data->overview,
        $data->description,
        $data->responsibilities,
        $data->requirements,
        $data->nice_to_have,
        $data->benefits,
        $data->deadline,
        $data->job_id,
        $user_id
    );

    if ($stmt->execute()) {

        // 2. --- UPDATE TAGS LOGIC ---
        // We only update tags if the 'tags' array is actually sent in the request
        if (isset($data->tags) && is_array($data->tags)) {
            $job_id = $data->job_id;

            // A. Remove ALL existing tag links for this job
            // (We don't delete the tags themselves from 'tags' table, just the links in 'job_tags')
            $deleteTags = $dbconnection->prepare("DELETE FROM job_tags WHERE job_id = ?");
            $deleteTags->bind_param("i", $job_id);
            $deleteTags->execute();
            $deleteTags->close();

            // B. Re-insert the current list of tags
            foreach ($data->tags as $tagName) {
                $tagName = trim($tagName);
                if (empty($tagName))
                    continue;

                // Check if tag exists in 'tags' table
                $checkTag = $dbconnection->prepare("SELECT id FROM tags WHERE name = ?");
                $checkTag->bind_param("s", $tagName);
                $checkTag->execute();
                $resTag = $checkTag->get_result();

                $tag_id = 0;

                if ($rowTag = $resTag->fetch_assoc()) {
                    // Tag exists
                    $tag_id = $rowTag['id'];
                } else {
                    // Tag doesn't exist, create it
                    $insertTag = $dbconnection->prepare("INSERT INTO tags (name) VALUES (?)");
                    $insertTag->bind_param("s", $tagName);
                    if ($insertTag->execute()) {
                        $tag_id = $dbconnection->insert_id;
                    }
                    $insertTag->close();
                }
                $checkTag->close();

                // Link Job to Tag
                if ($tag_id > 0) {
                    $linkTag = $dbconnection->prepare("INSERT INTO job_tags (job_id, tag_id) VALUES (?, ?)");
                    $linkTag->bind_param("ii", $job_id, $tag_id);
                    $linkTag->execute();
                    $linkTag->close();
                }
            }
        }
        // --- END TAGS LOGIC ---

        echo json_encode(["status" => true, "message" => "Job updated successfully"]);
    } else {
        throw new Exception("Update failed: " . $stmt->error);
    }

    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
}
?>