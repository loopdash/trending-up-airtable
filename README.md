1. database.sql: Middleware database to manage profile & post request between AirTable & BrightData
2. config.php: All api & database configuration
3. function.php: contains all necessary functions
4. profile-1.php/post-1.php: get profile & post rows from AirTable to the Middleware. Set the CRON once in 24 hours for those 2 files
5. profile-2.php/post-2.php: generates snapshot ids, fetch result from BrightData and then sends back to AirTable. Set the CRON 20-30 minutes interval

## Syncing more than one posts table

`$table_ids['POST']` in `config.php` can be a single table ID (uses `$base_id`) or a list of tables, each with its own base:

```php
$table_ids = [
    'PROFILE' => 'tblY7BERKU1mtwQK5',
    'POST' => [
        ['base_id' => 'appbWkOwjZ9yxbckI', 'table_id' => 'tblXXXXXXXXXXXXXX'], // AAGC/TU Paid Program Posts
        ['base_id' => 'appbWkOwjZ9yxbckI', 'table_id' => 'tblYYYYYYYYYYYYYY'], // Conference '26 Posts
    ],
];
```

Each posts table needs a `URL` column plus `Like Count`, `Comment Count`, `View Count` and `Last Update` fields. After changing the list, run `post-1.php` once by hand instead of waiting for the daily cron, then let `post-2.php` run.

