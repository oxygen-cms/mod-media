<div>
    <div class="Row">
    <div class="FileUpload">
        <div class="FileUpload-dropzone">
            <div class="FileUpload-drop FileUpload--js">Drop files here</div>
            <div class="FileUpload-click Button Button-color--grey">
                Click to select files
                <input name="file[]" type="file" multiple>
            </div>
        </div>
    </div>
    </div>

    <?php
    use Illuminate\Support\Collection;
    use Oxygen\Core\Form\FieldMetadata;
    use Oxygen\Core\Html\Form\EditableField;
    use Oxygen\Core\Html\Form\Label;
    use Oxygen\Core\Html\Form\Row;

    $f = [];

    $f[] = new EditableField($fields->getField('name'));

    $f[] = new EditableField($fields->getField('slug'));

    $headVersion = new FieldMetadata('headVersion', 'select', true);
    $headVersion->options = $media;
    $headVersion->options['_new'] = '(Create New)';
    $headVersion = new EditableField($headVersion, '_new');
    $f[] = $headVersion;

    foreach($f as $field) {
        $row = new Row([ new Label($field->getMeta()), $field ]);
        echo $row->render();
    }
    ?>
</div>

<div class="Row Form-footer">
    <a href="{{ URL::route($blueprint->getRouteName('getList')) }}" class="Button Button-color--white">Close</a>
    <button type="submit" class="Button Button-color--green">Upload</button>
</div>