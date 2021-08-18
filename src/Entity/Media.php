<?php

namespace OxygenModule\Media\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Expr\Value;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping AS ORM;
use Illuminate\Contracts\Support\Arrayable;
use Oxygen\Data\Behaviour\Accessors;
use Oxygen\Data\Behaviour\CacheInvalidator;
use Oxygen\Data\Behaviour\CacheInvalidatorInterface;
use Oxygen\Data\Behaviour\Fillable;
use Oxygen\Data\Behaviour\FillableInterface;
use Oxygen\Data\Behaviour\HasUpdatedAt;
use Oxygen\Data\Behaviour\PrimaryKey;
use Oxygen\Data\Behaviour\PrimaryKeyInterface;
use Oxygen\Data\Behaviour\SoftDeletes;
use Oxygen\Data\Behaviour\Searchable;
use Oxygen\Data\Behaviour\Timestamps;
use Oxygen\Data\Behaviour\Versionable;
use Oxygen\Data\Behaviour\Versions;
use Oxygen\Data\Validation\Rules\Unique;
use Oxygen\Data\Validation\Validatable;
use Oxygen\Data\Validation\ValidationService;

/**
 * @ORM\Entity
 * @ORM\Table(name="media")
 * @ORM\HasLifecycleCallbacks
 */
class Media implements PrimaryKeyInterface, Validatable, CacheInvalidatorInterface, Searchable, Versionable, HasUpdatedAt, Arrayable, FillableInterface {

    use PrimaryKey, Timestamps, SoftDeletes, Versions, CacheInvalidator;
    use Accessors, Fillable;

    const TYPE_IMAGE = 0;
    const TYPE_DOCUMENT = 1;
    const TYPE_AUDIO = 2;
    const IMAGE_MIME_MAP = [
        'webp' => ['mime' => 'image/webp', 'type' => self::TYPE_IMAGE],
        'jpg' => ['mime' => 'image/jpeg', 'type' => self::TYPE_IMAGE],
        'jpeg' => ['mime' => 'image/jpeg', 'type' => self::TYPE_IMAGE],
        'png' => ['mime' => 'image/png', 'type' => self::TYPE_IMAGE],
        'gif' => ['mime' => 'image/gif', 'type' => self::TYPE_IMAGE],
        'pdf' => ['mime' => 'application/pdf', 'type' => self::TYPE_DOCUMENT],
        'mp3' => ['mime' => 'audio/mpeg', 'type' => self::TYPE_AUDIO],
        'm4a' => ['mime' => 'audio/m4a', 'type' => self::TYPE_AUDIO],
        'ogg' => ['mime' => 'audio/ogg', 'type' => self::TYPE_AUDIO],
        'ogx' => ['mime' => 'audio/ogg', 'type' => self::TYPE_AUDIO],
        'mpga' => ['mime' => 'audio/mpeg', 'type' => self::TYPE_AUDIO],
        'aif' => ['mime' => 'audio/aiff', 'type' => self::TYPE_AUDIO],
        'wav' => ['mime' => 'audio/wav', 'type' => self::TYPE_AUDIO],
    ];

    /**
     * @ORM\Column(type="string")
     */
    protected $name;

    /**
     * @ORM\Column(type="string")
     */
    protected $slug;

    /**
     * @ORM\Column(type="string")
     */
    protected $filename;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    protected $alternativeFiles;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $author;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $caption;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $description;

    /**
     * @ORM\Column(type="integer")
     */
    protected $type;

    /**
     * @ORM\Column(name="`default`", type="string", nullable=true)
     */
    protected $default;

    /**
     * @ORM\OneToMany(targetEntity="OxygenModule\Media\Entity\Media", mappedBy="headVersion", cascade={"persist", "remove", "merge"})
     * @ORM\OrderBy({ "updatedAt" = "DESC" })
     */
    private $versions;

    /**
     * @ORM\ManyToOne(targetEntity="OxygenModule\Media\Entity\Media",  inversedBy="versions")
     * @ORM\JoinColumn(name="head_version", referencedColumnName="id")
     */
    private $headVersion;

    /**
     * @ORM\ManyToOne(targetEntity="OxygenModule\Media\Entity\MediaDirectory",  inversedBy="childFiles")
     * @ORM\JoinColumn(name="directory", referencedColumnName="id", nullable=true)
     */
    private $parentDirectory;

    /**
     * Constructs a new Media item.
     */

    public function __construct() {
        $this->versions = new ArrayCollection();
        $this->type = self::TYPE_IMAGE;
        $this->parentDirectory = null;
        $this->alternativeFiles = [];
    }

    /**
     * Returns a new name for the Media item.
     */
    public function getNewName() {
        $name = $this->name;
        $result = preg_match_all("/.*?(\\d+)$/", $name, $matches);
        if($result > 0) {
            $name = preg_replace_callback("/(.*?)(\\d+)$/", function($matches) {
                return $matches[1] . (((int) $matches[2]) + 1);
            }, $name);
        } else {
            $name .= ' 2';
        }
        return $name;
    }

    /**
     * Returns a new slug for the Media item.
     */
    public function getNewSlug() {
        $slug = $this->slug;
        $result = preg_match_all("/.*?(\\d+)$/", $slug, $matches);
        if($result > 0) {
            $slug = preg_replace_callback("/(.*?)(\\d+)$/", function($matches) {
                return $matches[1] . (((int) $matches[2]) + 1);
            }, $slug);
        } else {
            $slug .= '-2';
        }
        return $slug;
    }

    /**
     * Sets the filename.
     *
     * @param string $filename new filename, should be the hash of the file contents
     */
    public function setFilename(string $filename) {
        $this->filename = $filename;
    }

    /**
     * Returns the extension of the media item.
     */
    public function getExtension() {
        $parts = explode('.', $this->filename);
        return end($parts);
    }

    /**
     * Returns the extension of the media item.
     */
    public function getMimeType() {
        return self::IMAGE_MIME_MAP[$this->getExtension()]['mime'];
    }

    /**
     * Returns a list of alternative files for this media item.
     *
     * Specifically, will be a list of different-sized versions of an image,
     * to serve responsive images.
     *
     * @return array
     */
    public function getVariants(): array {
        $variants = $this->alternativeFiles === null ? [] : array_map(function(array $variant) {
            $info = pathinfo($variant['filename']);
            if(!isset($variant['mime'])) {
                $variant['mime'] = self::IMAGE_MIME_MAP[$info['extension']]['mime'];
            }
            return $variant;
        }, $this->alternativeFiles);

        $variants[] = [
            'filename' => $this->getFilename(),
            'mime' => self::IMAGE_MIME_MAP[$this->getExtension()]['mime'],
            'width' => null
        ];
        return $variants;
    }

    public function clearVariants(): array {
        return $this->alternativeFiles = [];
    }

    /**
     * Returns an array of validation rules used to validate the model.
     *
     * @return array
     */

    public function getValidationRules() {
        return [
            'name' => [
                'required',
                'max:255'
            ],
            'slug' => [
                'required',
                'slug',
                'max:255',
                $this->getUniqueSlugValidationRule()
            ],
            'filename' => [
                'required',
                'slugExtended'
            ],
            'author' => [
                'nullable',
                'name',
                'max:255'
            ],
            'default' => [
                'nullable',
                'integer'
            ]
        ];
    }

    /**
     * Returns the path to this image, including any directories.
     *
     * @return string
     */
    public function getFullPath() {
        $path = $this->parentDirectory !== null ? $this->parentDirectory->getFullPath() : '';
        $path .= '/' . $this->slug . '.' . $this->getExtension();
        return ltrim($path, '/');
    }

    /**
     * `name` must be unique, amongst directories that are siblings.
     *
     * @return Unique
     */
    private function getUniqueSlugValidationRule(): Unique {
        $unique = Unique::amongst(Media::class)->field('slug')->ignoreWithId($this->getId())
            ->addWhere('parentDirectory', ValidationService::EQUALS, $this->parentDirectory ? $this->parentDirectory->getId() : null);
        if($this->isHead()) {
            $unique = $unique->addWhere('headVersion', ValidationService::EQUALS, ValidationService::NULL);
        } else {
            $unique->addWhere('id', ValidationService::EQUALS, $this->getId());
        }
        return $unique;
    }

    /**
     * @param string|int|null $parentDirectory
     * @return $this
     */
    public function setParentDirectory($parentDirectory): Media {
        if(is_integer($parentDirectory)) {
            $this->parentDirectory = app(EntityManager::class)->getReference(MediaDirectory::class, $parentDirectory);
        } else {
            $this->parentDirectory = $parentDirectory;
        }
        return $this;
    }

    public function getFilename() {
        return $this->filename;
    }

    /**
     * Adds a new variant to this image.
     *
     * @param string $filename
     * @param int|null $width
     * @param string $mimeType
     */
    public function addVariant(string $filename, ?int $width, string $mimeType) {
        $this->alternativeFiles[] = [
            'filename' => $filename,
            'width' => $width,
            'mime' => $mimeType
        ];
    }

    /**
     * @return int
     */
    public function getType(): int {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getTypeAsString(): string {
        if($this->type == self::TYPE_IMAGE) {
            return 'Image';
        } else if($this->type == self::TYPE_AUDIO) {
            return 'Audio';
        } else if($this->type == self::TYPE_DOCUMENT) {
            return 'Document';
        } else {
            return 'Unknown';
        }
    }

    /**
     * Returns true if there already exists a variant at this width.
     * @param int|null $size null if original size
     * @param string|null $desiredMime the requested mime type, or null if don't care
     * @return bool
     */
    public function hasVariant(?int $size, ?string $desiredMime = null) {
        foreach($this->getVariants() as $variant) {
            if($variant['width'] === $size && ($desiredMime === null || $variant['mime'] === $desiredMime)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the fields that should be fillable.
     *
     * @return array
     */
    public function getFillableFields(): array {
        return ['name', 'slug', 'author', 'alt', 'caption', 'description', 'default', 'type', 'parentDirectory'];
    }

    /**
     * Returns the fields that should be searched.
     *
     * @return array
     */
    public static function getSearchableFields() {
        return ['name', 'slug', 'description', 'filename'];
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray() {
        return [
            'id' => $this->getId(),
            'name' => $this->name,
            'slug' => $this->slug,
            'filename' => $this->filename,
            'variants' => $this->getVariants(),
            'extension' => $this->getExtension(),
            'fullPath' => $this->getFullPath(),
            'author' => $this->author,
            'caption' => $this->caption,
            'description' => $this->description,
            'type' => $this->type,
            'default' => $this->default,
            'headVersion' => $this->headVersion === null ? null : $this->headVersion->getId(),
            'createdAt' => $this->createdAt !== null ? $this->createdAt->format(\DateTimeInterface::ATOM) : null,
            'updatedAt' => $this->updatedAt !== null ? $this->updatedAt->format(\DateTimeInterface::ATOM) : null,
            'deletedAt' => $this->deletedAt !== null ? $this->deletedAt->format(\DateTimeInterface::ATOM) : null,
            'parentDirectory' => $this->parentDirectory !== null ? $this->parentDirectory->toArray() : null
        ];
    }

    /**
     * @return mixed
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getCaption() {
        return $this->caption;
    }
}
