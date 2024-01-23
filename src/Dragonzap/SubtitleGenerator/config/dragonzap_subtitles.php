<?php

/*
 * Licensed under GPLv2
 * Author: Daniel McCarthy
 * Dragon Zap Publishing
 * Website: https://dragonzap.com
 */

 
return [
    'subtitle_generator' => [
        'google' => [
            'project_id' => env('GOOGLE_CLOUD_SPEECH_TO_TEXT_PROJECT_ID'),
            'credentials' => env('GOOGLE_CLOUD_SPEECH_TO_TEXT_APPLICATION_CREDENTIALS'),
            'bucket' => env('GOOGLE_CLOUD_SPEECH_TO_TEXT_STORAGE_BUCKET'),

            // This is where the extracted WAV files will be stored for processing by google speech to text
            // They will be deleted automatically after the job has been completed
            'audio_file_tmp_directory' => env('GOOGLE_CLOUD_SPEECH_TO_TEXT_AUDIO_FILE_TMP_DIRECTORY', 'audio-files'),
        ]
    ],
];
