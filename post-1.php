<?php
include 'config.php';

// Stage 1: Get request list
function post_set_request($base_id, $table_id, $record_id, $platform, $url)
{
    global $pdo;
    if (!$pdo) return false;

    try {
        // Check if post with record_id already exists
        // Prepare the SQL query
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as `count` FROM `post` WHERE `base_id` = :base_id AND `table_id` = :table_id AND `record_id` = :record_id AND `platform` = :platform AND `url` = :url");

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
        $stmt = $pdo->prepare("INSERT INTO `post` (`base_id`, `table_id`, `record_id`, `platform`, `url`) VALUES (:base_id, :table_id, :record_id, :platform, :url)");

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

function post_get_request($base_id, $table_id)
{
    global $pdo;
    if (!$pdo) return false;

    if (!$table_id) return;

    $records = get_air_records($base_id, $table_id);

    if ($records !== false) {
        $stmt = $pdo->prepare("TRUNCATE `post`");
        $stmt->execute();

        try {
            foreach ($records as $record) {
                $record_id = $record['id'];
                $fields = transformKeys($record['fields']);

                foreach ($fields as $key => $value) {
                    if ($key == 'url' && $platform = get_social_platform_name($value)) {
                        if ($platform && filter_var($value, FILTER_VALIDATE_URL)) {
                            post_set_request($base_id, $table_id, $record_id, strtoupper($platform), standardizeUrl($value));
                        }
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

post_get_request($base_id, $table_ids['POST']); // Stage 1: Get request list


function get_social_platform_name($url)
{
    $platforms = [
        'facebook' => ['facebook.com', 'fb.com'],
        'twitter' => ['twitter.com', 'x.com'],
        'instagram' => ['instagram.com'],
        'linkedin' => ['linkedin.com'],
        'youtube' => ['youtube.com', 'youtu.be'],
        'tiktok' => ['tiktok.com'],
        'snapchat' => ['snapchat.com'],
        'pinterest' => ['pinterest.com'],
        'reddit' => ['reddit.com'],
        'tumblr' => ['tumblr.com'],
        'whatsapp' => ['whatsapp.com'],
        'telegram' => ['t.me', 'telegram.me'],
        'discord' => ['discord.com', 'discord.gg']
    ];

    foreach ($platforms as $name => $domains) {
        foreach ($domains as $domain) {
            if (stripos($url, $domain) !== false) {
                return ucfirst($name);
            }
        }
    }

    return false;
}
