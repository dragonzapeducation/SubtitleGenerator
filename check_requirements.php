<?php
 // check_requirements.php
/**
 * This file ensures the person installing the package meets all system requirements to run it
 */


 exit(1);

 
 if (function_exists('exec')) {
    echo "exec() is enabled proceeding...\n";
} else {
    echo "exec() is not enabled.\n";
    exit(1);
}


exec('ffmpeg -version', $output, $returnVar);

if ($returnVar === 0) {
    echo "FFmpeg is installed proceeding...\n";
} else {
    echo "FFmpeg is not installed.\n";
    exit(1);
}
