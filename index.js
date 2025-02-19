import 'dotenv/config';
import fetch from 'node-fetch';
import Airtable from 'airtable';

const API_KEY = process.env.BRIGHT_DATA_API;
const IG_DATASET_ID = 'gd_l1vikfch901nx3by4';
const TIKTOK_DATASET_ID = 'gd_l1villgoiiidt09ci';
const YOUTUBE_DATASET_ID = 'gd_lk538t2k2p1k3oos71';
const AIRTABLE_API_KEY = process.env.AIRTABLE_API_KEY;
const AIRTABLE_BASE_ID = process.env.AIRTABLE_BASE_ID;
const AIRTABLE_TABLE_NAME = process.env.AIRTABLE_TABLE_NAME;

// Initialize Airtable
const base = new Airtable({ apiKey: AIRTABLE_API_KEY }).base(AIRTABLE_BASE_ID);

// Function to trigger BrightData dataset collection
async function triggerDataset(handle, platform) {
    let datasetId;
    let url;

    switch (platform) {
        case 'instagram':
            datasetId = IG_DATASET_ID;
            url = `https://www.instagram.com/${handle}`;
            break;
        case 'tiktok':
            datasetId = TIKTOK_DATASET_ID;
            url = `https://www.tiktok.com/@${handle}`;
            break;
        case 'youtube':
            datasetId = YOUTUBE_DATASET_ID;
            url = `https://www.youtube.com/@${handle}/about`;
            break;
        default:
            throw new Error(`Unsupported platform: ${platform}`);
    }

    const response = await fetch(`https://api.brightdata.com/datasets/v3/trigger?dataset_id=${datasetId}&include_errors=true`, {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${API_KEY}`,
            'Content-Type': 'application/json',
        },
        body: JSON.stringify([{ url }]),
    });

    const data = await response.json();
    return data.snapshot_id;
}

// Function to check if snapshot data is ready
async function waitForSnapshot(snapshotId, retries = 10, delay = 5000) {
    const url = `https://api.brightdata.com/datasets/v3/snapshot/${snapshotId}/?format=json`;

    for (let attempt = 1; attempt <= retries; attempt++) {
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${API_KEY}`,
            },
        });

        if (response.status === 200) {
            const data = await response.json();
            if (data && data.length > 0) {
                return data;
            }
        }

        console.log(`Snapshot not ready yet (attempt ${attempt}/${retries}). Retrying in ${delay / 1000} seconds...`);
        await new Promise(resolve => setTimeout(resolve, delay));
    }

    throw new Error('Snapshot data not ready after maximum retries.');
}

// Function to update Airtable with specific account fields
async function updateAirtable(recordId, account, platform) {
    const updates = {
        url: account.input?.url || '',
        followers: platform === 'youtube' ? account.subscribers || 0 : account.followers || 0,
        following: account.following || 0,
        posts_count: account.posts_count || 0,
        is_verified: account.is_verified || false,
        avg_engagement: account.avg_engagement || 0,
        biography: account.biography || '',
        category_name: account.category_name || ''
    };

    await base(AIRTABLE_TABLE_NAME).update(recordId, updates);
}

// Main function to loop through Airtable records and update follower counts
(async () => {
    try {
        console.log('Fetching Airtable records...');
        const records = await base(AIRTABLE_TABLE_NAME).select().all();

        for (const record of records) {
            const handle = record.get('Name');
            const platform = record.get('platform');

            if (!handle || !platform) continue;

            console.log(`Processing ${platform} handle: ${handle}`);
            const snapshotId = await triggerDataset(handle, platform);
            console.log(`Snapshot ID for ${handle} (${platform}): ${snapshotId}`);

            const snapshotData = await waitForSnapshot(snapshotId);
            const account = snapshotData[0];

            if (account) {
                console.log(`Updating Airtable for ${handle} (${platform}).`);
                await updateAirtable(record.id, account, platform);
            } else {
                console.log(`No data found for ${handle} (${platform}).`);
            }
        }

        console.log('Airtable update complete.');
    } catch (error) {
        console.error('Error:', error.message);
        if (error.response) {
            console.error('Error details:', await error.response.text());
        }
    }
})();
