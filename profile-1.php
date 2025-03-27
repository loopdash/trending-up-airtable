<?php
include 'config.php';

// Stage 1: Get request list
function profile_set_request($base_id, $table_id, $record_id, $platform, $url)
{
    global $pdo;
    if (!$pdo) return false;

    try {
        // Check if profile with record_id already exists
        // Prepare the SQL query
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as `count` FROM `profile` WHERE `base_id` = :base_id AND `table_id` = :table_id AND `record_id` = :record_id AND `platform` = :platform AND `url` = :url");

        // Bind parameters
        $checkStmt->bindParam(':base_id', $base_id);
        $checkStmt->bindParam(':table_id', $table_id);
        $checkStmt->bindParam(':record_id', $record_id);
        $checkStmt->bindParam(':platform', $platform);
        $checkStmt->bindParam(':url', $url);

        // Execute the query
        $checkStmt->execute();
        $row = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($row['count'] > 0) {
            return;
        }

        // Prepare the SQL query
        $stmt = $pdo->prepare("INSERT INTO `profile` (`base_id`, `table_id`, `record_id`, `platform`, `url`) VALUES (:base_id, :table_id, :record_id, :platform, :url)");

        // Bind parameters
        $stmt->bindParam(':base_id', $base_id);
        $stmt->bindParam(':table_id', $table_id);
        $stmt->bindParam(':record_id', $record_id);
        $stmt->bindParam(':platform', $platform);
        $stmt->bindParam(':url', $url);

        // Execute the query
        $stmt->execute();
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}

function profile_get_request($base_id, $table_id)
{
    global $pdo;
    if (!$pdo) return false;

    if (!$table_id) return;

    $records = get_air_records($base_id, $table_id);

    if ($records !== false) {
        $stmt = $pdo->prepare("TRUNCATE `profile`");
        $stmt->execute();

        try {
            foreach ($records as $record) {
                $record_id = $record['id'];
                $fields = transformKeys($record['fields']);

                $lastUpdate = new DateTime($fields['last_update']);
                $currentDateTime = new DateTime();
                $interval = $currentDateTime->diff($lastUpdate);

                if ($interval->h >= 12 || $interval->days > 0) {
                    if (isset($fields['instagram_url'])) {
                        profile_set_request($base_id, $table_id, $record_id, 'instagram', standardizeUrl($fields['instagram_url']));
                    }

                    if (isset($fields['tiktok_url'])) {
                        profile_set_request($base_id, $table_id, $record_id, 'tiktok', standardizeUrl($fields['tiktok_url']));
                    }

                    if (isset($fields['youtube_url'])) {
                        profile_set_request($base_id, $table_id, $record_id, 'youtube', standardizeUrl($fields['youtube_url']));
                    }
                }
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
        }
    } else {
        echo "Failed to retrieve records.\n";
    }
}

profile_get_request($base_id, $table_ids['PROFILE']); // Stage 1: Get request list
