<?php
include 'config.php';

// Stage 2: Generate snapshot ID
function post_get_request_list()
{
    global $pdo;
    if (!$pdo) return false;

    try {
        $stmt = $pdo->prepare("SELECT * FROM `post` WHERE `stage` = 'REQUEST' ORDER BY RAND() LIMIT 50");
        $stmt->execute();

        $data = $stmt->fetchAll();

        if (empty($data)) {
            return false;
        }

        // Group results by at_platform with only ID and URLs
        $request_data = [];
        foreach ($data as $row) {
            $request_data[] = [
                'id' => $row['id'],
                'platform' => $row['platform'],
                'url' => $row['url']
            ];
        }

        return $request_data;
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}

function post_gen_snapshot_id()
{
    global  $pdo, $dataset_ids;
    if (!$pdo) return false;

    if ($request_data = post_get_request_list()) {
        foreach ($request_data as $entry) {
            $id = $entry['id'];
            $url = $entry['url'];
            $platform = $entry['platform'];

            // Assuming $dataset_ids is an array with platform as key
            if (isset($dataset_ids['POST'][$platform])) {
                if ($snapshot_id = get_bright_snapshot_id($dataset_ids['POST'][$platform], $url)) {
                    $pdo->exec("UPDATE `post` SET `snapshot_id` = '" . $snapshot_id . "', `stage` = 'SNAPSHOT' WHERE `id` = '" . $id . "' AND `stage` = 'REQUEST'");
                }

                // else {
                //     $stmt = $pdo->prepare("DELETE FROM `post` WHERE  `id`= :id");
                //     $stmt->bindParam(':id', $id);
                //     $stmt->execute();
                // }
            } else {
                error_log("No dataset ID found for platform: " . $platform);
            }
        }
    } else {
        echo '';
    }
}
// End of Stage 2

// Stage 3: Fetch data from BrightData and update the database
function post_gen_snapshot_data()
{
    global $pdo;
    if (!$pdo) return false;

    try {
        // Fetch all rows where status is SUCCESS and snapshot_id exists
        $stmt = $pdo->prepare("SELECT DISTINCT * FROM `post` WHERE `stage` = 'SNAPSHOT' AND `snapshot_id` IS NOT NULL ORDER BY RAND() LIMIT 50");
        $stmt->execute();

        $request_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($request_list)) {
            echo '';
            return false;
        }

        foreach ($request_list as $row) {
            $id = $row['id'];
            $snapshot_id = $row['snapshot_id'];
            $platform = $row['platform'];

            if (get_bright_snapshot_status($snapshot_id)) {
                $data = get_bright_snapshot_data($snapshot_id);

                if ($data) {
                    $formatted_data = json_decode($data, true);

                    if ($platform == 'INSTAGRAM') {
                        $dt_data = [
                            'like_count' => $formatted_data[0]['likes'] ?? 0,
                            'comment_count' => $formatted_data[0]['num_comments'] ?? 0,
                            'view_count' => $formatted_data[0]['video_view_count'] ?? 0
                        ];
                    }

                    if ($platform == 'TIKTOK') {
                        $dt_data = [
                            'like_count' => $formatted_data[0]['digg_count'] ?? 0,
                            'comment_count' => $formatted_data[0]['comment_count'] ?? 0,
                            'view_count' => $formatted_data[0]['digg_count'] ?? 0
                        ];
                    }

                    if ($platform == 'YOUTUBE') {
                        $dt_data = [
                            'like_count' => $formatted_data[0]['likes'] ?? 0,
                            'comment_count' => $formatted_data[0]['num_comments'] ?? 0,
                            'view_count' => $formatted_data[0]['views'] ?? 0
                        ];
                    }

                    $bd_data_json = json_encode($dt_data);

                    $stmt = $pdo->prepare("UPDATE `post` SET `data` = :bd_data, `stage` = 'READY' WHERE `stage` = 'SNAPSHOT' AND `snapshot_id` = :bd_snapshot_id");
                    $stmt->bindParam(':bd_data', $bd_data_json);
                    $stmt->bindParam(':bd_snapshot_id', $snapshot_id);
                    $stmt->execute();
                }
            }

            // else {
            //     $stmt = $pdo->prepare("DELETE FROM `post` WHERE  `id`= :id");
            //     $stmt->bindParam(':id', $id);
            //     $stmt->execute();
            // }
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}
// End of Stage 3

// Stage 4: Update Airtable with the data
function post_get_table_record()
{
    global $pdo;
    if (!$pdo) return false;

    try {
        $stmt = $pdo->prepare("SELECT * FROM `post` WHERE `stage` = 'READY' ORDER BY RAND() LIMIT 50");
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($data)) {
            return false;
        }

        $groupedData = [];
        foreach ($data as $row) {
            $record_id = $row['record_id'];
            if (!isset($groupedData[$record_id])) {
                $groupedData[$record_id] = [
                    // 'id' => $row['id'],
                    'base_id' => $row['base_id'],
                    'table_id' => $row['table_id'],
                    'record_id' => $row['record_id'],
                    'data' => json_decode($row['data'], true)
                ];
            } else {
                $currentData = json_decode($row['data'], true);
                foreach ($currentData as $key => $value) {
                    if (is_array($value)) {
                        $groupedData[$record_id]['data'][$key] = array_merge($groupedData[$record_id]['data'][$key], $value);
                    } else {
                        $groupedData[$record_id]['data'][$key] = $value;
                    }
                }
            }
        }

        return array_values($groupedData);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}

function post_set_table_record()
{
    global $API_AIRTABLE, $pdo;
    if (!$pdo) return false;

    if ($records = post_get_table_record()) {
        foreach ($records as $record) {
            $base_id = $record['base_id'];
            $table_id = $record['table_id'];
            $record_id = $record['record_id'];
            $fields = $record['data'];

            $url = "https://api.airtable.com/v0/" . $base_id . "/" . $table_id . "/$record_id";

            $data = [
                "fields" => [
                    'Last Update' => date('Y-m-d H:i:s'),
                    'Like Count' => $fields['like_count'] ?? null,
                    'Comment Count' => $fields['comment_count'] ?? 0,
                    'View Count' => $fields['view_count'] ?? 0
                ]
            ];

            $options = [
                'http' => [
                    'header'  => [
                        "Authorization: Bearer " . $API_AIRTABLE,
                        "Content-Type: application/json"
                    ],
                    'method'  => 'PATCH',
                    'content' => json_encode($data)
                ]
            ];

            $context  = stream_context_create($options);
            $result = file_get_contents($url, false, $context);

            if ($result === FALSE) {
                die('Error updating record');
            }

            $stmt = $pdo->prepare("DELETE FROM `post` WHERE  `record_id`= :record_id");
            $stmt->bindParam(':record_id', $record_id);
            $stmt->execute();
        }
    }
}
// End of Stage 4

post_gen_snapshot_id(); // Stage 2: Generate snapshot ID
post_gen_snapshot_data(); // Stage 3: Fetch data from BrightData and update the database
post_set_table_record(); // Stage 4: Update Airtable with the data
