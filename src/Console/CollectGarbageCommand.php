<?php


namespace OxygenModule\Media\Console;


use Illuminate\Console\Command;

class CollectGarbageCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'media:collect-garbage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes underlying filesystem resources which are no longer referenced by any media items.';

    public function handle() {
        // TODO: implement collect-garbage
    }

}
