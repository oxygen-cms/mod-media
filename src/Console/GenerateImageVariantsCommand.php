<?php


namespace OxygenModule\Media\Console;

use Intervention\Image\Facades\Image as ImageFacade;
use Illuminate\Console\Command;
use Oxygen\Data\Repository\ExcludeTrashedScope;
use Oxygen\Data\Repository\ExcludeVersionsScope;
use Oxygen\Data\Repository\QueryParameters;
use OxygenModule\Media\Controller\MediaController;
use OxygenModule\Media\Entity\Media;
use OxygenModule\Media\Repository\MediaRepositoryInterface;

class GenerateImageVariantsCommand extends Command {
    const MIN_RESPONSIVE_WIDTH = 400;

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
     * @var \Symfony\Component\Console\Helper\ProgressBar
     */
    private $bar;

    private function output($msg) {
        $this->bar->clear();
        $this->output->writeln($msg);
        $this->bar->display();
    }

    /**
     * Execute the console command.
     *
     * @return void
     * @throws \Exception
     */
    public function handle(MediaRepositoryInterface $repository, MediaController $controller) {
        $generated = 0;
        $missing = 0;
        $skipped = 0;

        $all = $repository->all(new QueryParameters([new ExcludeVersionsScope(), new ExcludeTrashedScope()]));

        $this->bar = $this->output->createProgressBar(count($all));
        foreach($all as $media) {
            $this->bar->advance();
            if($media->getType() !== Media::TYPE_IMAGE) {
                $this->output('<fg=yellow>Skipping</> ' . $media->getFullPath() . ' - type=' . $media->getTypeAsString());
                $skipped++;
                continue;
            }

            $filepath = config('oxygen.mod-media.directory.filesystem') . '/' . $media->getFilename();

            if(!file_exists($filepath)) {
                $this->output('<fg=red>Error</> ' . $media->getFullPath() . ' - original does not exist');
                $missing++;
                continue;
            }

            $this->output('<fg=green>Generating</> variants for ' . $media->getFullPath());

            $image = ImageFacade::make($filepath);
            if($image->width() < self::MIN_RESPONSIVE_WIDTH) {
                $this->output('<fg=yellow>Skipping</> width too small');
                $skipped++;
                continue;
            }

            $prevCount = count($media->getVariants());
            $controller->generateImageVariants($media);
            $generatedVariants = count($media->getVariants()) - $prevCount;

            $this->output('<fg=green>Generated</> ' . $generatedVariants . ' variants for ' . $media->getFullPath());

            $generated += $generatedVariants;
        }

        $repository->flush();

        $this->bar->clear();
        $this->output->writeln('<fg=green>Summary:</> skipped items: ' . $skipped . ', generated variants: ' . $generated . ', missing files: ' . $missing);
    }

}
