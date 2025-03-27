<?php
include 'function.php';

$API_AIRTABLE = 'patJaDknA6kBVllul.293f10ca3040701e67f73879a02c2be377b42cb9ee708f87497d00f215a86bf1';
$API_BRIGHTDATA = '154dba8a771fe6ce8d8c4099e2a52670323524004b661c9c8ecd519f14b575f7';

$db_host = "localhost";
$db_name = "airtable";
$db_user = "root";
$db_pass = "";

$base_id = 'app9Hdi3IYV8q7KZS';

$table_ids = [
    'PROFILE' => 'tblY7BERKU1mtwQK5',
    'POST' => 'tblUt35yIroVOcqKQ',
];

$dataset_ids = [
    'PROFILE' => [
        'INSTAGRAM' => 'gd_l1vikfch901nx3by4',
        'TIKTOK' => 'gd_l1villgoiiidt09ci',
        'YOUTUBE' => 'gd_lk538t2k2p1k3oos71',
    ],
    'POST' => [
        'INSTAGRAM' => 'gd_lk5ns7kz21pck8jpis',
        'TIKTOK' => 'gd_lu702nij2f790tmv9h',
        'YOUTUBE' => 'gd_lk56epmy2i5g7lzu0k',
    ]
];

$pdo = null;

try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];

    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}


function standardizeUrl($url)
{
    // Trim any whitespace
    $url = trim($url);

    // Check if the URL already contains a scheme (http or https)
    if (!preg_match("/^(http:\/\/|https:\/\/)/", $url)) {
        $url = "https://" . $url;
    }

    // Ensure proper formatting (optional)
    $url = filter_var($url, FILTER_SANITIZE_URL);

    return $url;
}

function transformKeys($array)
{
    if (!is_array($array)) {
        return $array;
    }

    $newArray = [];

    foreach ($array as $key => $value) {
        $newKey = strtolower(str_replace(' ', '_', $key));

        if (is_array($value)) {
            $newArray[$newKey] = transformKeys($value);
        } else {
            $newArray[$newKey] = $value;
        }
    }

    return $newArray;
}
