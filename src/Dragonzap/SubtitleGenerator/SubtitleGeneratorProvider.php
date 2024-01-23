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

class SubtitleGeneratorProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/dragonzap.php' => config_path('dragonzap.php'),
        ], 'config');
    
        $this->mergeConfigFrom(
            __DIR__.'/config/dragonzap.php', 'dragonzap'
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

