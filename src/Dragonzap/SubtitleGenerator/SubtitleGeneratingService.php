<?php

namespace Dragonzap\SubtitleGenerator;

use Exception;
use Google\Cloud\Speech\SpeechClient;
use Google\Cloud\Storage\StorageClient;
use Dragonzap\SubtitleGenerator\Exceptions\SubtitleGenerationFailedException;

class SubtitleGeneratingService
{

    protected string $projectId;
    protected string $credentialsPath;

    protected array $credentials;

    // Google drive bucket name
    protected string $bucket;

    // Audio directory
    protected string $audioFileTmpDirectory;

    /**
     * Google_config is optional if provided ensure the array has two keys
     * 
     * [
     *    'project_id' => 'PROJECT ID OF GOOGLE CLOUD',
     *    'credentials' => 'PATH TO GOOGLE CLOUD CREDENTIALS FILE',
     *    'bucket' => 'GOOGLE CLOUD BUCKET NAME WHERE EXTRACTED AUDIO FILES WILL BE STORED',
     *    'audio_file_tmp_directory' => 'DIRECTORY IN BUCKET WHERE EXTRACTED AUDIO FILES WILL BE STORED'
     * 
     * ]
     */
    public function __construct(array|null $google_config = null)
    {
        if ($google_config) {
            // Validate all are passed 
            if (!isset($google_config['project_id'])) {
                throw new SubtitleGenerationFailedException("Google Cloud project id not provided.");
            }
            if (!isset($google_config['credentials'])) {
                throw new SubtitleGenerationFailedException("Google Cloud credentials file path not provided.");
            }
            if (!isset($google_config['bucket'])) {
                throw new SubtitleGenerationFailedException("Google Cloud bucket name not provided.");
            }
            if (!isset($google_config['audio_file_tmp_directory'])) {
                throw new SubtitleGenerationFailedException("Google Cloud audio file tmp directory not provided.");
            }

            $this->projectId = $google_config['project_id'];
            $this->credentialsPath = $google_config['credentials'];
            // Check if the credentials file exists
            if (!file_exists($this->credentialsPath)) {
                throw new SubtitleGenerationFailedException("Google Cloud credentials file not found: " . $this->credentialsPath);
            }

            $this->credentials = json_decode(file_get_contents($this->credentialsPath), true);
            $this->audioFileTmpDirectory = $google_config['audio_file_tmp_directory'];
            $this->bucket = $google_config['bucket'];

            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $this->credentialsPath);
        } else {
            $this->projectId = config('dragonzap_subtitles.subtitle_generator.google.project_id');
            $this->credentialsPath = config('dragonzap_subtitles.subtitle_generator.google.credentials');
            $this->audioFileTmpDirectory = config('dragonzap_subtitles.subtitle_generator.google.audio_file_tmp_directory');
            $this->bucket = config('dragonzap_subtitles.subtitle_generator.google.bucket');

            // Validate all are passed
            if (!$this->projectId) {
                throw new SubtitleGenerationFailedException("Google Cloud project id not provided.");
            }
            if (!$this->credentialsPath) {
                throw new SubtitleGenerationFailedException("Google Cloud credentials file path not provided.");
            }
            if (!$this->bucket) {
                throw new SubtitleGenerationFailedException("Google Cloud bucket name not provided.");
            }
            if (!$this->audioFileTmpDirectory) {
                throw new SubtitleGenerationFailedException("Google Cloud audio file tmp directory not provided.");
            }


            // Check if the credentials file exists
            if (!file_exists($this->credentialsPath)) {
                throw new SubtitleGenerationFailedException("Google Cloud credentials file not found: " . $this->credentialsPath);
            }
            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $this->credentialsPath);
            // Load the credentials from the file
            $this->credentials = json_decode(file_get_contents($this->credentialsPath), true);

        }
    }
    /**
     * Starts to generate subtitles for the given movie path, responds with an operating id 
     * that can be used to check on the progress.
     * 
     * @param string $input_movie_path The path to the movie file to create subtitles for.
     */
    public function beginGeneratingSubtitles(string $input_movie_path): string
    {
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $this->credentialsPath);

        $localFilePath = $input_movie_path; // Path to your local file

        // Create a temporary file name with .wav extension
        $tmpFilePath = tempnam(sys_get_temp_dir(), 'audio_') . '.wav';

        // Extract audio using ffmpeg
        $success = $this->extractAudioWithFfmpeg($localFilePath, $tmpFilePath);
        if (!$success) {
            throw new SubtitleGenerationFailedException("Failed to extract audio from the video file is ffmpeg installed.");
        }

        // Upload the file to Google Cloud Storage
        $gcsUri = $this->uploadToGoogleStorage($tmpFilePath);

        // Delete the temporary file
        unlink($tmpFilePath);


        // Start processing the audio file asynchronously
        return $this->processAudioFile($gcsUri);
    }

    /**
     * Checks the status of the subtitle generation operation with the given operation id.
     * @param string $operation_id The operation id to check the status of.
     * 
     * @return array An array with the status of the operation and the subtitles if the operation is complete.
     * 
     * RETURN FORMAT
     * [
     *     'status' => 'in_progress' | 'success',
     *     'subtitles' => 'WEBVTT\n\n1\n00:00:00.000 --> 00:00:01.000\nHello World\n\n2\n00:00:01.000 --> 00:00:02.000\nThis is a test\n\n3\n00:00:02.000 --> 00:00:03.000\nGoodbye World\n\n'
     * ]
     * 
     * subtitles field only present if status is success
     * 
     * @throws SubtitleGenerationFailedException If the operation failed.
     */
    public function checkSubtitleGenerationOperation(string $operation_id): array
    {
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $this->credentialsPath);

        $speech = new SpeechClient([
            'projectId' => $this->projectId,
            'credentials' => $this->credentials,
        ]);

        $operation = $speech->operation($operation_id);

        // Wait for the operation to complete
        if (!$operation->isComplete()) {
            return [
                'status' => 'in_progress',
                'info' => $operation->info()
            ];
        }


        // Get the results
        $response = $operation->results();
        if (!$response) {
            throw new SubtitleGenerationFailedException("Failed to get the results of the completed operation.");
        }
        $subtitles = $this->createSubtitles($response);

        // Once the operation is complete, get the metadata
        $metadata = $operation->info()['metadata'];

        // Check if the metadata contains the input URI
        if (isset($metadata['uri'])) {
            $inputUri = $metadata['uri'];
            // We want a uri relative to the bucket so we should replace the gs:// bucket name from the uri
            $inputUri = str_replace('gs://' . $this->bucket . '/', '', $inputUri);
            // Delete the file from Google Cloud Storage
            $this->deleteFromGoogleStorage($inputUri);
        }

        return [
            'status' => 'success',
            'subtitles' => $subtitles,
            'info' => $operation->info()
        ];
    }
    private function extractAudioWithFfmpeg($inputFile, $outputFile)
    {
        $command = 'ffmpeg -i ' . $inputFile . ' -vn -acodec pcm_s16le -ar 44100 -ac 1 ' . $outputFile;
        return exec($command) !== FALSE;
    }


    private function uploadToGoogleStorage($filePath)
    {
        // Check if the file exists and is readable
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new Exception("File does not exist or is not readable: " . $filePath);
        }

        $fileContents = file_get_contents($filePath);

        // Generate a unique file name
        $randomFileName = uniqid('audio_', true) . rand(1000, 9999);
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        $targetPath = $this->audioFileTmpDirectory . '/' . $randomFileName . '.' . $fileExtension;

        // Initialize Google Cloud Storage client
        $storage = new StorageClient([
            // Provide your Google Cloud credentials here
            'keyFilePath' => $this->credentialsPath
        ]);

        // Select your bucket
        $bucket = $storage->bucket($this->bucket);

        // Check if the target file already exists in the bucket to avoid overwriting
        if ($bucket->object($targetPath)->exists()) {
            throw new Exception("Target file already exists in the storage: " . $targetPath);
        }

        // Upload the file
        $bucket->upload($fileContents, [
            'name' => $targetPath
        ]);

        return 'gs://' . $this->bucket . '/' . $targetPath;
    }


    private function deleteFromGoogleStorage($gcsUri)
    {
        // Initialize Google Cloud Storage client
        $storage = new StorageClient([
            // Provide your Google Cloud credentials here
            'keyFilePath' => $this->credentialsPath
        ]);

        // Select your bucket
        $bucket = $storage->bucket($this->bucket);

        // Delete the file
        $object = $bucket->object($gcsUri);
        $object->delete();
    }

    private function processAudioFile($gcsUri)
    {


        $speech = new SpeechClient([
            'projectId' => $this->projectId,
            'credentials' => $this->credentials,
        ]);


        // Configure the asynchronous recognition
        $operation = $speech->beginRecognizeOperation($gcsUri, [
            'encoding' => 'LINEAR16',
            'sampleRateHertz' => 44100,
            'languageCode' => 'en-US',
            'enableWordTimeOffsets' => true
        ]);

        return $operation->name();

    }
    private function createSubtitles($response)
    {
        $res_str = "WEBVTT\n\n";
        $sequenceNumber = 1;

        foreach ($response as $result) {
            $words = $result->alternatives()[0]['words'];
            $totalWords = count($words);
            $startIndex = 0;

            while ($startIndex < $totalWords) {
                $res_str .= $sequenceNumber++ . "\n";
                $res_str .= $this->createSubtitlesExtractWords($words, $startIndex, $totalWords);
                $res_str .= "\n\n";
                $startIndex += 15; // Adjust this number as needed
            }
        }

        return $res_str;
    }

    private function createSubtitlesExtractWords($words, $startIndex, $total)
    {
        $endIndex = min($startIndex + 15, $total); // Adjust this number as needed
        $startTime = $this->formatTime($words[$startIndex]['startTime']);
        $endTime = $this->formatTime($words[$endIndex - 1]['endTime']);

        $timed_string = $startTime . ' --> ' . $endTime . "\n";
        for ($i = $startIndex; $i < $endIndex; $i++) {
            $timed_string .= $words[$i]['word'] . ' ';
        }

        return trim($timed_string);
    }

    private function endsWithSentenceTerminator($word)
    {
        $terminators = ['.', '?', '!'];
        foreach ($terminators as $terminator) {
            if (substr($word, -1) === $terminator) {
                return true;
            }
        }
        return false;
    }

    private function isLastWordInAlternative($wordInfo, $words)
    {
        $lastWord = end($words);
        return $wordInfo === $lastWord;
    }

    private function formatTime($timeString)
    {
        // Assuming the time format is something like '59.900s'
        $time = rtrim($timeString, 's');
        $seconds = floor($time);
        $milliseconds = ($time - $seconds) * 1000;

        // Format the time string to HH:MM:SS.mmm (WebVTT format)
        $formattedTime = gmdate('H:i:s', $seconds) . '.' . str_pad($milliseconds, 3, '0', STR_PAD_LEFT);
        return $formattedTime;
    }


}
