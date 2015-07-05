@extends(app('oxygen.layout'))

@section('content')

<?php

    use App\Entity\Media;
    use Illuminate\Support\Collection;
    use Oxygen\Core\Form\FieldMetadata;
    use Oxygen\Core\Html\Form\EditableField;
    use Oxygen\Core\Html\Form\Label;
    use Oxygen\Core\Html\Form\Row;
    use Oxygen\Core\Html\Header\Header;

    $header = Header::fromBlueprint(
        $blueprint,
        'Upload'
    );

    $header->setBackLink(URL::route($blueprint->getRouteName('getList')));

?>

<!-- =====================
            HEADER
     ===================== -->

<div class="Block">
    {!! $header->render() !!}
</div>

<!-- =====================
             INFO
     ===================== -->

<div class="Block Block--padded">
    <?php
        $form = new Form($blueprint->getAction('postUpload'));
        $form->setUseMultipartFormData(true);

        $form->addContent(View::make('oxygen/mod-media::uploadForm', ['fields' => $fields]));

        echo $form->render();
    ?>
</div>

@stop
