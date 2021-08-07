<?php


namespace OxygenModule\Media\Console;


use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use OxygenModule\Media\Entity\Media;
use OxygenModule\Media\Repository\MediaRepositoryInterface;

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

    protected function getFilenames(Media $item): array {
        return array_map(function($item) { return $item['filename']; }, $item->getVariants());
    }

    public function handle(MediaRepositoryInterface $repository, Filesystem $filesystem) {
        $items = $repository->all();

        $dir = config('oxygen.mod-media.directory.filesystem') . '/';

        $filenames = [];
        foreach($items as $item) {
            foreach($this->getFilenames($item) as $filename) {
                if(!isset($filenames[$filename])) {
                    $filenames[$filename] = [ 'references' => [], 'found' => false ];
                }
                $filenames[$filename]['references'][] = $item->getFullPath();
            }
        }

        $files = $filesystem->files($dir);

        foreach($files as $file) {
            $filename = $file->getFilename();
            if(!isset($filenames[$filename])) {
                $filenames[$filename] = ['references' => [], 'found' => true];
            } else {
                $filenames[$filename]['found'] = true;
            }
        }

        foreach($filenames as $filename => $file) {
            if(!$file['found']) {
                $this->output->writeln('<fg=yellow>Warning:</> ' . $filename . ' (referenced by <fg=yellow>' . implode('</>, <fg=yellow>', array_unique($file['references'])) . '</>) not found');
            }
        }

        $garbage = array_filter($filenames, function($item) { return count($item['references']) === 0; });

        if(count($garbage) === 0) {
            $this->output->writeln('<fg=green>No unreferenced files found! Nothing to do.</>');
            return;
        }

        $this->output->writeln('Found ' . count($garbage) . ' unreferenced files:');
        foreach($garbage as $garbageFile => $info) {
            $this->output->writeln('- ' . $garbageFile);
        }

        if($this->confirm('Do you wish to delete these files?')) {
            $toDelete = array_map(function($filename) use($dir) {return $dir . '/' . $filename; }, array_keys($garbage));
            $filesystem->delete($toDelete);
            $this->output->writeln(count($garbage) . ' files deleted');
        }

    }

}
