@extends(app('oxygen.layout'))

@section('content')

@include('oxygen/crud::versionable.itemHeader', ['blueprint' => $blueprint, 'fields' => $fields, 'item' => $item, 'title' => $title])


<div class="Block Block--mini">
    <?php
        use OxygenModule\Media\Presenter\PresenterInterface;
        echo App::make(PresenterInterface::class)->preview($item);
    ?>
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
    $versionField = new EditableField($versionFieldMeta, app('request'), 'guess');
    $versionRow = new Row([new Label($versionField->getMeta()), $versionField]);

?>

@include('oxygen/crud::basic.updateForm', ['blueprint' => $blueprint, 'item' => $item, 'fields' => $fields, 'extraFields' => [$versionRow]])

@include('oxygen/crud::versionable.versions', ['item' => $item])

@stop
