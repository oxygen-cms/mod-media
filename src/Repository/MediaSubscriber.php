<?php
    
namespace OxygenModule\Media\Repository;

use OxygenModule\Media\Entity\Media;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository as Config;
use Illuminate\Filesystem\Filesystem;

class MediaSubscriber implements EventSubscriber {

    /**
     * Filesystem instance.
     *
     * @var Filesystem
     */

    protected $files;

    /**
     * Laravel Config
     *
     * @var Config
     */

    protected $config;

    /**
     * Constructs the MediaSubscriber.
     *
     * @param Filesystem    $filesystem
     * @param Config        $config
     * @param CacheManager  $cache
     */

    public function __construct(Filesystem $filesystem, Config $config, CacheManager $cache) {
        $this->files = $filesystem;
        $this->config = $config;
        $this->cache = $cache;
    }

    /**
     * Returns an array of events this subscriber wants to listen to.
     *
     * @return array
     */

    public function getSubscribedEvents() {
        return [
            Events::prePersist,
            Events::preUpdate,
            Events::preRemove
        ];
    }

    /**
     * Clears the Media cache.
     *
     * @param LifecycleEventArgs $args
     * @return void
     */

    public function prePersist(LifecycleEventArgs $args) {
        $this->invalidateCache();
    }

    /**
     * Clears the Media cache.
     *
     * @param LifecycleEventArgs $args
     * @return void
     */

    public function preUpdate(LifecycleEventArgs $args) {
        $this->invalidateCache();
    }

    /**
     * Clears the media cache.
     */

    protected function invalidateCache() {
        $this->cache->forget('media.list');
    }

    /**
     * Makes sure that the file is in sync with the DB record.
     *
     * @param LifecycleEventArgs $args
     * @return void
     */

    public function preRemove(LifecycleEventArgs $args) {
        $this->invalidateCache();

        $entity = $args->getEntity();

        if(!($entity instanceof Media)) { return; }

        $filename = $entity->getFilename();

        $query = $args->getEntityManager()
            ->createQueryBuilder()
            ->select('count(o.id)')
            ->from(get_class(   $entity), 'o')
            ->andWhere('o.filename = :filename')
            ->setParameter('filename', $filename);
        if($entity->isHead()) {
            $query = $query->andWhere('o.headVersion != :headVersion')
                ->setParameter('headVersion', $entity->getHead());
        }

        $count = (int) $query->getQuery()
            ->getSingleScalarResult();

        // if there is only one entity with this filename
        if($count <= 1) {
            $path = $this->config->get('media.directory.filesystem') . '/' . $entity->getFilename();
            if($this->files->exists($path)) {
                $this->files->delete($path);
            }
        }
    }
}