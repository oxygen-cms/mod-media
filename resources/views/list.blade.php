@extends(app('oxygen.layout'))

@section('content')

<?php

    use OxygenModule\Media\Entity\Media;
    use OxygenModule\Media\Presenter\PresenterInterface;
    use Oxygen\Core\Blueprint\Blueprint;
    use Oxygen\Core\Html\Header\Header;

    $title = __(
        $isTrash ? 'oxygen/crud::ui.resource.listTrash' : 'oxygen/crud::ui.resource.list',
        ['resources' => $blueprint->getDisplayName(Blueprint::PLURAL)]
    );

    $sectionHeader = Header::fromBlueprint(
        $blueprint,
        $title
    );

    if($isTrash) {
        $sectionHeader->setBackLink(URL::route($blueprint->getRouteName('getList')));
    }

?>

<!-- =====================
            HEADER
     ===================== -->

<div class="Block">
    {!! $sectionHeader->render() !!}
</div>

<!-- =====================
             LIST
     ===================== -->

@if($items->isEmpty())
    <div class="Block">
        <h2 class="heading-gamma margin-large">
            @lang('oxygen/crud::ui.noItems')
        </h2>
    </div>
@else

    <div class="Row--layout Row--equalCells Row--wrap">
        <?php
        foreach($items as $item):
            $itemHeader = Header::fromBlueprint($blueprint, $crudFields, ['model' => $item, 'span' => 'oneThird'], Header::TYPE_BLOCK, 'item');

            if(method_exists($item, 'isPublished')) {
                $icon = $item->isPublished() ? 'globe' : 'pencil-square';
                $itemHeader->setIcon($icon);
            }

            $itemHeader->setContent(App::make(PresenterInterface::class)->preview($item));

            echo $itemHeader->render();
        endforeach;
        ?>
    </div>

@endif

{!! $items->render() !!}

@stop
