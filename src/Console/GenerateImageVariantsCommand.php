<?php


namespace OxygenModule\Media\Console;

use Exception;
use Illuminate\Console\Command;
use OxygenModule\Media\ImageVariantGenerator;
use OxygenModule\Media\ImageVariantGeneratorOutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class GenerateImageVariantsCommand extends Command implements ImageVariantGeneratorOutputInterface {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'media:generate-variants';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates multiple sizes of images for making images "responsive" (improves load times)';
    /**
     * @var ProgressBar|null
     */
    private $bar;

    public function writeln(string $line) {
        if($this->bar) {
            $this->bar->clear();
        }
        $this->output->writeln($line);
        if($this->bar) {
            $this->bar->display();
        }
    }

    /**
     * Execute the console command.
     *
     * @return void
     * @throws Exception
     */
    public function handle(ImageVariantGenerator $generator) {
        $generator->generateAllImageVariants($this);
    }

    public function setProgressTotal(int $total) {
        $this->bar = $this->output->createProgressBar($total);
    }

    public function advanceProgress() {
        $this->bar->advance();
    }

    public function clearProgress() {
        $this->bar->clear();
        $this->bar = null;
    }
}
