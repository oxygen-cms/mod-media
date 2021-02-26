<?php

namespace OxygenModule\Media\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping AS ORM;
use Oxygen\Data\Behaviour\Accessors;
use Oxygen\Data\Behaviour\CacheInvalidator;
use Oxygen\Data\Behaviour\CacheInvalidatorInterface;
use Oxygen\Data\Behaviour\Fillable;
use Oxygen\Data\Behaviour\PrimaryKey;
use Oxygen\Data\Behaviour\PrimaryKeyInterface;
use Oxygen\Data\Behaviour\SoftDeletes;
use Oxygen\Data\Behaviour\Searchable;
use Oxygen\Data\Behaviour\Timestamps;
use Oxygen\Data\Behaviour\Versions;
use Oxygen\Data\Validation\Validatable;

/**
 * @ORM\Entity
 * @ORM\Table(name="media")
 * @ORM\HasLifecycleCallbacks
 */

class Media implements PrimaryKeyInterface, Validatable, CacheInvalidatorInterface, Searchable {

    use PrimaryKey, Timestamps, SoftDeletes, Versions, CacheInvalidator;
    use Accessors, Fillable;

    const TYPE_IMAGE = 0;
    const TYPE_DOCUMENT = 1;
    const TYPE_AUDIO = 2;

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
     * Constructs a new Media item.
     */

    public function __construct() {
        $this->versions = new ArrayCollection();
        $this->type = self::TYPE_IMAGE;
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
     * Generates a new unique filename for the Media resource.
     *
     * @param string $extension
     * @return $this
     */

    public function makeNewFilename($extension = null) {
        if($extension === null) {
            $extension = $this->getExtension();
        }

        $this->filename = md5($this->slug . rand()) . '.' . $extension;
        return $this;
    }

    /**
     * Returns the extension of the media item.
     */
    public function getExtension() {
        $parts = explode('.', $this->filename);
        return end($parts);
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
                'slugExtended',
                'max:255',
                $this->getUniqueValidationRule('slug')
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
     * Returns the fields that should be fillable.
     *
     * @return array
     */

    protected function getFillableFields() {
        return ['name', 'slug', 'author', 'alt', 'caption', 'description', 'default', 'type'];
    }

    /**
     * Returns the fields that should be searched.
     *
     * @return array
     */
    public static function getSearchableFields() {
        return ['name', 'slug', 'description'];
    }

}
