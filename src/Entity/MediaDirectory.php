<?php


namespace OxygenModule\Media\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping AS ORM;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Oxygen\Data\Behaviour\Accessors;
use Oxygen\Data\Behaviour\Fillable;
use Oxygen\Data\Behaviour\PrimaryKey;
use Oxygen\Data\Behaviour\PrimaryKeyInterface;
use Oxygen\Data\Behaviour\Searchable;
use Oxygen\Data\Behaviour\SoftDeletes;
use Oxygen\Data\Behaviour\Timestamps;
use Oxygen\Data\Pagination\PaginationService;
use Oxygen\Data\Validation\Rules\Unique;
use Oxygen\Data\Validation\Validatable;
use Doctrine\Common\Collections\Criteria;
use Oxygen\Data\Validation\ValidationService;
use OxygenModule\Media\Repository\MediaDirectoryRepositoryInterface;

/**
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 */
class MediaDirectory implements PrimaryKeyInterface, Validatable, Arrayable {

    use PrimaryKey, Timestamps, SoftDeletes;
    use Accessors, Fillable;

    /**
     * @ORM\Column(type="string", nullable=false)
     */
    protected $slug;

    /**
    * @ORM\Column(type="string")
    */
    protected $name;

    /**
     * @ORM\ManyToOne(targetEntity="OxygenModule\Media\Entity\MediaDirectory", inversedBy="childDirectories")
     * @ORM\JoinColumn(name="parent_directory", referencedColumnName="id")
     */
    private $parentDirectory;

    /**
     * @ORM\OneToMany(targetEntity="OxygenModule\Media\Entity\MediaDirectory", mappedBy="parentDirectory", cascade={"persist", "remove", "merge"}, fetch="EXTRA_LAZY")
     * @ORM\OrderBy({ "name" = "ASC" })
     */
    private $childDirectories;

    /**
     * @ORM\OneToMany(targetEntity="OxygenModule\Media\Entity\Media", mappedBy="parentDirectory", fetch="EXTRA_LAZY")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $childFiles;

    /**
     * Returns an array of validation rules used to validate the model.
     *
     * @return array
     */
    public function getValidationRules() {
        return [
            'name' => [
                'nullable',
                'max:255',
                $this->getUniqueAmongstSiblingsValidationRule('name')
            ],
            'slug' => [
                'required',
                'max:255',
                'slug',
                $this->getUniqueAmongstSiblingsValidationRule('slug')
            ]
        ];
    }

    /**
     * `name` must be unique, amongst directories that are siblings.
     *
     * @return Unique
     */
    private function getUniqueAmongstSiblingsValidationRule($field): Unique {
        return Unique::amongst(MediaDirectory::class)
            ->field($field)->ignoreWithId($this->getId())
            ->addWhere('parentDirectory', ValidationService::EQUALS, $this->parentDirectory ? $this->getParentDirectory()->getId() : null);
    }

    /**
     * @return MediaDirectory|null
     */
    private function getParentDirectory(): ?MediaDirectory {
        return $this->parentDirectory;
    }

    /**
     * Returns the path of this folder, e.g.:
     * /foo
     * /foo/bar
     * /foo-something/baz/qux
     *
     * @return string
     */
    public function getFullPath(): string {
        $parentPath = $this->parentDirectory !== null ? $this->parentDirectory->getFullPath() : '';
        return ltrim($parentPath . '/' . $this->slug, '/');
    }

    /**
     * @param MediaDirectory|integer|null $parent
     */
    public function setParent($parent) {
        if(is_integer($parent)) {
            $this->parentDirectory = app(EntityManager::class)->getReference(MediaDirectory::class, $parent);
        } else {
            $this->parentDirectory = $parent;
        }
    }

    /**
     * @param string $slug
     */
    public function setSlug(string $slug) {
        $this->slug = $slug;
    }

    /**
     * @param string $name
     */
    public function setName(string $name) {
        if($this->slug === null || $this->slug === Str::slug($this->name)) {
            $this->slug = Str::slug($name);
        }
        $this->name = $name;
    }

    /**
     * @return Collection
     */
    public function getChildDirectories(): Collection {
        return $this->childDirectories;
    }

    /**
     * Returns the fields that should be fillable.
     *
     * @return array
     */
    protected function getFillableFields(): array {
        return ['name', 'slug', 'parentDirectory'];
    }

    /**
     * @return String
     */
    public function getName(): string {
        if($this->name === null) {
            return Str::studly($this->slug);
        } else {
            return $this->name;
        }
    }

    /**
     * @return string
     */
    public function getSlug(): string {
        return $this->slug;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray() {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'slug' => $this->getSlug(),
            'fullPath' => $this->getFullPath(),
            'parentDirectory' => $this->parentDirectory !== null ? $this->getParentDirectory()->toArray() : null
        ];
    }

    /**
     * @param PaginationService $pagination
     * @param int $perPage
     * @param int $currentPage
     * @return LengthAwarePaginator
     */
    public function paginateChildFiles(PaginationService $pagination, $perPage, $currentPage): LengthAwarePaginator {
        $criteria = Criteria::create()
            ->andWhere(new Comparison('headVersion', Comparison::EQ, null))
            ->andWhere(new Comparison('deletedAt', Comparison::EQ, null))
            ->setFirstResult($perPage * ($currentPage - 1))->setMaxResults($perPage);
        $totalItems = $this->childFiles->count();
        $items = $this->childFiles->matching($criteria)->toArray();
        return $pagination->make($items, $totalItems, $perPage);
    }
}
