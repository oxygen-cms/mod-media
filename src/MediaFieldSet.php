<?php


namespace OxygenModule\Media;

use Oxygen\Core\Form\FieldSet;

class MediaFieldSet extends FieldSet {

    /**
     * Creates the fields in the set.
     *
     * @return array
     */
    public function createFields() {
        return $this->makeFields([
            [
                'name'      => 'id',
                'label'     => 'ID',
            ],
            [
                'name'      => 'name',
                'editable'  => true
            ],
            [
                'name'      => 'slug',
                'editable'  => true
            ],
            [
                'name'      => 'filename'
            ],
            [
                'name'      => 'author',
                'editable'  => true
            ],
            [
                'name'      => 'caption',
                'type'      => 'textarea',
                'editable'  => true,
                'attributes' => [
                    'rows' => 2
                ]
            ],
            [
                'name'      => 'description',
                'type'      => 'textarea',
                'editable'  => true
            ],
            [
                'name'      => 'default',
                'editable'  => true
            ],
            [
                'name'      => 'createdAt',
                'type'      => 'date'
            ],
            [
                'name'      => 'updatedAt',
                'type'      => 'date'
            ],
            [
                'name'      => 'deletedAt',
                'type'      => 'date'
            ]
        ]);
    }

    /**
     * Returns the name of the title field.
     *
     * @return mixed
     */
    public function getTitleFieldName() {
        return 'name';
    }
}