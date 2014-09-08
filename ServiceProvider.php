<?php namespace Cysha\Modules\Taylor;

use Illuminate\Foundation\AliasLoader;
use Cysha\Modules\Core\BaseServiceProvider;
use Cysha\Modules\Taylor as Taylor;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        $this->registerBotCommands();
    }

    private function registerBotCommands()
    {
        $this->app['tay:run'] = $this->app->share(function () {
            return new Taylor\Commands\InitBotCommand($this->app);
        });
        $this->commands('tay:run');
    }

}
