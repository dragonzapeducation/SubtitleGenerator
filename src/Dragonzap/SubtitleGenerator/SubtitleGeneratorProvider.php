<?php

/*
 * Licensed under GPLv2
 * Author: Daniel McCarthy
 * Email: daniel@dragonzap.com
 * Dragon Zap Publishing
 * Website: https://dragonzap.com
 */

namespace Dragonzap\SubtitleGenerator;

use Illuminate\Support\ServiceProvider;
use Dragonzap\SubtitleGenerator\SubtitleGeneratingService;

class SubtitleGeneratorProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/dragonzap_subtitles.php' => config_path('dragonzap_subtitles.php'),
        ], 'config');
    
        $this->mergeConfigFrom(
            __DIR__.'/config/dragonzap_subtitles.php', 'dragonzap_subtitles'
        );

        $this->app->singleton(SubtitleGeneratingService::class, function ($app) {
            // NULL will ensure that the laravel dragonzap config file will be used for determining
            // the google project id and credentials path.
            return new SubtitleGeneratingService(null);
        });

        
    }
    
    public function register()
    {
        // Code for bindings, if necessary
    }
}

