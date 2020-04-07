@extends(app('oxygen.layout'))

@section('content')

<?php

    use Oxygen\Core\Html\Form\Form;
    use Oxygen\Core\Html\Header\Header;

    $title = __('oxygen/mod-media::ui.upload.title');

    $header = Header::fromBlueprint(
        $blueprint,
        $title
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
        $form->setAsynchronous(true);

        $form->addContent(View::make('oxygen/mod-media::uploadForm', ['fields' => $crudFields, 'media' => $media]));

        echo $form->render();
    ?>
</div>

@stop
