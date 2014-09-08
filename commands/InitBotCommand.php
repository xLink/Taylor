<?php namespace Cysha\Modules\Taylor\Commands;

use Cysha\Modules\Core\Commands\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;

class InitBotCommand extends BaseCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'tay:run';

    /**
     * The Readable Module Name.
     *
     * @var string
     */
    protected $readableName = 'Run Taylor Run!';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Makes Taylor do a bit of running...';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        set_time_limit(0);

        $irc = new \Cysha\Modules\Taylor\Helpers\Irc\Client($this->argument('server'));
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['server', InputArgument::OPTIONAL, 'Decides which server to connect Taylor to.', 'default']
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
        );
    }

}
