# SubtitleGenerator
PHP library that generates WebVTT subtitles from input video files, requires google cloud bucket and google speech to text API's which can be found at (Google Coud Console)[https://console.cloud.google.com].

## Using without Laravel framework
The library can be used with or without Laravel framework. Seek the (test.php)[https://github.com/dragonzapeducation/SubtitleGenerator/blob/main/test.php] file for use case without Laravel framework.

Run the composer command to install the subtitle package
```
composer require dragonzap/subtitle-generator
```

## Using with Laravel framework

To use this with laravel framework start by installing the subtitle package
Run the composer command to install the subtitle package:
```
composer require dragonzap/subtitle-generator
```

Next open the config/app.php file and update the 'providers' array
```
 'providers' => [

        /*
         * Laravel Framework Service Providers...
         */
        Illuminate\Auth\AuthServiceProvider::class,
        // Add the below provider
        Dragonzap\SubtitleGenerator\SubtitleGeneratorProvider::class
        //...
 ];

```

Next open up your .env file and add the following environment variables
- `GOOGLE_CLOUD_SPEECH_TO_TEXT_PROJECT_ID`: 
  - **Description**: This is the project ID for the Google Cloud Speech-to-Text service. It uniquely identifies your project on the Google Cloud platform.

- `GOOGLE_CLOUD_SPEECH_TO_TEXT_APPLICATION_CREDENTIALS`: 
  - **Description**: Path to the JSON file that contains your service account key. This file provides authentication credentials to your application so it can interact with Google Cloud APIs.

- `GOOGLE_CLOUD_SPEECH_TO_TEXT_STORAGE_BUCKET`: 
  - **Description**: The name of the Google Cloud Storage bucket where files related to the Speech-to-Text processing will be stored.

- `GOOGLE_CLOUD_SPEECH_TO_TEXT_AUDIO_FILE_TMP_DIRECTORY`: 
  - **Description**: The directory where extracted WAV files will be temporarily stored for processing by Google Cloud Speech-to-Text. These files are automatically deleted after the job is completed. If not set in the environment, it defaults to `'audio-files'`.

Next you need to run the following command in your Laravel directory to publish the configuration
```
php artisan vendor:publish --provider="Dragonzap\SubtitleGenerator\SubtitleGeneratorProvider" --force
```

Now you should have a new file called config/dragonzap_subtitles.php that looks like this:
```
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

```
You can reconfigure the config file to use environment variables of your choosing

Next you can create the test command to test this functionality
```
php artisan make:command TestCommand
```

Replace the new command file with the following contents:
```
<?php

namespace App\Console\Commands;

use Dragonzap\SubtitleGenerator\SubtitleGeneratingService;

class TestCommand extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'test:command';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Command description';

  protected $list_id;
  protected SubtitleGeneratingService $service;
  /**
   * Create a new command instance.
   *
   * @return void
   */
  public function __construct(SubtitleGeneratingService $service)
  {
    parent::__construct();
    $this->service = $service;

  }


  public function handle()
  {
      $operation_id = $this->service->beginGeneratingSubtitles(public_path('output.mp4'));

      while(1)
      {
          $result = $this->service->checkSubtitleGenerationOperation($operation_id);
          if ($result['status'] == 'success')
          {
              $this->info('Success');
              $this->info($result['subtitles']);
              break;
          }
          else
          {
              $this->info('In Progress');
          }

          sleep(5);
      }
  }
  
}
```

In this example subtitles will be generated for the output.mp4 file

## Warning
The subtitle generator is still in development and may not function as expected at this time
