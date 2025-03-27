1. database.sql: Middleware database to manage profile & post request between AirTable & BrightData
2. config.php: All api & database configuration
3. function.php: contains all necessary functions
4. profile-1.php/post-1.php: get profile & post rows from AirTable to the Middleware. Set the CRON once in 24 hours for those 2 files
5. profile-2.php/post-2.php: generates snapshot ids, fetch result from BrightData and then sends back to AirTable. Set the CRON 20-30 minutes interval
