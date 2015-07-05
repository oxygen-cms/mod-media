<div class="Row--noLayout">
    <div class="FileUpload">
        {{ Form::file('file[]', array('multiple')) }}
        <span class="FileUpload-message FileUpload--js">Drop files here</span>
        <span class="FileUpload-subMessage FileUpload--js">(or click to select)</span>
        <span class="FileUpload-message FileUpload--noJs">Click to select files</span>
        <span class="FileUpload-subMessage FileUpload--noJs">(then click the 'Upload' button)</span>
    </div>

    <?php
    $fields = [];

    $fields[] = new EditableField($fields->getField('name'), app('request'));

    $fields[] = new EditableField($fields->getField('slug'), app('request'));

    // using a Collection resolves an issue where the integer array keys of $media are ignored
    $media = new Collection($media);
    $media->put('_new', '(Create New)');

    $headVersion = new FieldMetadata('headVersion', 'select', true);
    $headVersion->options = $media;
    $headVersion = new EditableField($headVersion, '_new');
    $fields[] = $headVersion;

    foreach($fields as $field) {
        $row = new Row([ new Label($field->getMeta()), $field ]);
        echo $row->render();
    }
    ?>
</div>

<div class="Row Form-footer">
    <a href="{{{ URL::route($blueprint->getRouteName('getList')) }}}" class="Button Button-color--white">Close</a>
    <button type="submit" class="Button Button-color--green">Upload</button>
</div>