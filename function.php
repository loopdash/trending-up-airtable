<?php
/**
 * Normalize a table setting from config.php into a list of base/table pairs.
 *
 * Accepts the original single table ID (uses $default_base_id), or a list of
 * ['base_id' => 'app...', 'table_id' => 'tbl...'] entries so tables in
 * different bases can sync side by side.
 *
 * @param string $default_base_id Base used when an entry has no base_id
 * @param string|array $setting A table ID, or a list of table entries
 * @return array List of ['base_id' => ..., 'table_id' => ...]
 */
function get_table_targets($default_base_id, $setting)
{
    if (empty($setting)) {
        return [];
    }

    if (is_string($setting)) {
        return [['base_id' => $default_base_id, 'table_id' => $setting]];
    }

    $targets = [];
    foreach ($setting as $entry) {
        if (is_string($entry)) {
            $targets[] = ['base_id' => $default_base_id, 'table_id' => $entry];
        } elseif (!empty($entry['table_id'])) {
            $targets[] = [
                'base_id' => $entry['base_id'] ?? $default_base_id,
                'table_id' => $entry['table_id'],
            ];
        }
    }

    return $targets;
}

/* * Function to get the latest records from an Airtable base and table
 * @param string $base_id The ID of the Airtable base
 * @param string $table_id The ID of the Airtable table
 * @return array|false An array of records or false on failure
 */
function get_air_records($base_id, $table_id)
{
    global $API_AIRTABLE;

    $url = "https://api.airtable.com/v0/" . $base_id . "/" . urlencode($table_id);
    $headers = [
        "Authorization: Bearer {$API_AIRTABLE}",
        "Content-Type: application/json",
    ];
    $allRecords = [];
    $offset = null;

    while (true) {
        $params = ["pageSize" => 100];

        if ($offset) {
            $params["offset"] = $offset;
        }

        $query = http_build_query($params);
        $fullUrl = $url . "?" . $query;

        $ch = curl_init($fullUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            // Handle cURL errors
            echo 'cURL error: ' . curl_error($ch);
            return false; // Or handle the error as needed.
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode >= 400) {
            // Handle HTTP errors
            echo 'HTTP error: ' . $httpCode . ' - ' . $response;
            return false;
        }

        curl_close($ch);

        $data = json_decode($response, true);

        if (isset($data['records'])) {
            $allRecords = array_merge($allRecords, $data['records']);
        }

        $offset = isset($data['offset']) ? $data['offset'] : null;

        if (!$offset) {
            break;
        }
    }

    return $allRecords;
}

/* * Function to get the latest records from an Airtable base and table with a specific view
 * @param string $base_id The ID of the Airtable base
 * @param string $table_id The ID of the Airtable table
 * @param string $view The name of the view to filter by
 * @return array|false An array of records or false on failure
 */
function get_bright_snapshot_id($dataset_id, $url)
{
    global $API_BRIGHTDATA;
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://api.brightdata.com/datasets/v3/trigger?dataset_id=" . $dataset_id . "&include_errors=true");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $API_BRIGHTDATA,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '[{"url":"' . $url . '"}]');

    $response = curl_exec($ch);
    curl_close($ch);

    $response = json_decode($response);

    // Output response
    if ($response && isset($response->snapshot_id)) {
        return $response->snapshot_id; // snapshot_id
    } else {
        return false;
    }
}

/** Function to get the status of a Bright Data snapshot
 * @param string $snapshot_id The ID of the snapshot
 * @return bool True if the snapshot is ready, false otherwise
 */
function get_bright_snapshot_status($snapshot_id)
{
    global $API_BRIGHTDATA;

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://api.brightdata.com/datasets/v3/progress/" . $snapshot_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $API_BRIGHTDATA,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if (json_decode($response)->status == 'ready') {
        if (json_decode($response)->errors == 0) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

/** Function to get the data of a Bright Data snapshot
 * @param string $snapshot_id The ID of the snapshot
 * @return string The data of the snapshot in JSON format
 */
function get_bright_snapshot_data($snapshot_id)
{
    global $API_BRIGHTDATA;

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://api.brightdata.com/datasets/v3/snapshot/" . $snapshot_id . "?format=json");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $API_BRIGHTDATA,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}
