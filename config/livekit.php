<?php

/*
 * Real video sessions via LiveKit, added 2026-09-28.
 *
 * For local testing: a free LiveKit Cloud project (https://cloud.livekit.io) — create a
 * project, then Settings > API Keys gives you all three values below. In production
 * this points at the self-hosted LiveKit server on the VPS instead; nothing in the code
 * changes, only these three .env values.
 *
 *   LIVEKIT_URL=wss://your-project-xxxxxxxx.livekit.cloud
 *   LIVEKIT_API_KEY=APIxxxxxxxxxxxx
 *   LIVEKIT_API_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
 *
 * Put the real values in .env, never here. If any of the three is missing, the video
 * room page shows a "video isn't set up yet" notice instead of erroring.
 */
return [

    'url' => env('LIVEKIT_URL'),
    'api_key' => env('LIVEKIT_API_KEY'),
    'api_secret' => env('LIVEKIT_API_SECRET'),

    // Every therapy session gets its own room, named this prefix + the session's UUID.
    'room_prefix' => env('LIVEKIT_ROOM_PREFIX', 'mindwell-session-'),

];
