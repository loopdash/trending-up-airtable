<?php
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
