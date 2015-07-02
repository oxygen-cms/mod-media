@extends(app('oxygen.layout'))

@section('content')

<?php

    $title = Lang::get('oxygen/crud::ui.resource.update', [
        'resource' => $blueprint->getDisplayName()
    ]);
?>

@include('oxygen/crud::versionable.itemHeader', ['blueprint' => $blueprint, 'fields' => $fields, 'item' => $item, 'title' => $title])


<div class="Block Block--mini">
    <?php echo App::make('App\Media\PresenterInterface')->preview($item) ?>
</div>

<?php

    use Oxygen\Core\Form\FieldMetadata;
    use Oxygen\Core\Html\Form\EditableField;
    use Oxygen\Core\Html\Form\Label;
    use Oxygen\Core\Html\Form\Row;

    $versionFieldMeta = new FieldMetadata('version', 'select', true);
    $versionFieldMeta->options = [
            'new' => 'Save as New Version',
            'overwrite' => 'Overwrite Existing Version',
            'guess' => 'Create a New Version if Needed'
    ];
    $versionField = new EditableField($versionFieldMeta, 'guess');
    $versionRow = new Row([new Label($versionField->getMeta()), $versionField]);

?>

@include('oxygen/crud::basic.updateForm', ['blueprint' => $blueprint, 'item' => $item, 'fields' => $fields, 'extraFields' => [$versionRow]])

@include('oxygen/crud::versionable.versions', ['item' => $item])

@stop
