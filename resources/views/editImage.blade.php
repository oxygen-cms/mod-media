@extends(app('oxygen.layout'))

@section('content')

@include('oxygen/crud::versionable.itemHeader', ['blueprint' => $blueprint, 'item' => $item, 'title' => $title])

<!-- =====================
             INFO
     ===================== -->

<div class="Block Block--padded">
    <?php
        use Oxygen\Core\Html\Editor\Editor;
    ?>

    <div class="ImageEditor">
        <div class="ImageEditor-content">
            <img
                src="{{{ URL::route($blueprint->getRouteName('getRaw'), $item->getId()) }}}"
                alt="{{{ $item->getAlt() }}}"
                class="ImageEditor-image"
                data-root="{{{ URL::route($blueprint->getRouteName('getRaw'), $item->getId()) }}}">
        </div>
        <div class="ImageEditor-panel">
            <div class="ImageEditor-toolbar Row--visual">
                <div class="ButtonTabGroup TabSwitcher-tabs" role="tablist">
                    <button
                      type="button"
                      class="Button Button-color--white" role="tab" data-switch-to-tab="simple" data-default-tab>
                        Simple
                    </button>
                    <button
                      type="button"
                      class="Button Button-color--white" role="tab" data-switch-to-tab="advanced">
                        Advanced
                    </button>
                </div>
                <button type="button" class="Button Button-color--white ImageEditor-toggleFullscreen align-right" data-enabled="false">
                    <div class="Toggle--ifEnabled">
                        <span class="Icon Icon-times"></span>
                    </div>
                    <div class="Toggle--ifDisabled">
                        <span class="Icon Icon-expand"></span>
                    </div>
                </button>
            </div>

            <div class="TabSwitcher-content" style="flex: 1;">
                <form
                    action="{{{ URL::route($blueprint->getRouteName('getRaw'), $item->getId()) }}}"
                    method="GET"
                    class="Form--singleColumn Form--dark Form--warnBeforeExit ImageEditor-form--simple ImageEditor-padded TabSwitcher-tabs TabSwitcher-content"
                    data-tab="simple">
                    <button
                       type="button" class="Accordion-section"
                       data-switch-to-tab="basic" data-default-tab>
                         <span class="Icon Icon-chevron-right Accordion-section-icon"></span>
                         <span class="Accordion-section-message">Basic</span>
                    </button>
                    <div data-tab="basic">
                        <label class="Form-label" for="brightness">Brightness</label>
                        <input name="brightness" id="brightness" type="range" min="-100" max="100">
                        <label class="Form-label" for="contrast">Contrast</label>
                        <input name="contrast" id="contrast" type="range" min="-100" max="100">
                    </div>

                    <button
                       type="button" class="Accordion-section"
                       data-switch-to-tab="crop">
                         <span class="Icon Icon-chevron-right Accordion-section-icon"></span>
                         <span class="Accordion-section-message">Crop</span>
                    </button>
                    <div data-tab="crop">
                        <input name="crop[x]" type="number" placeholder="X" class="Form-input--narrow ImageEditor-crop-input">
                        <label class="Form-label Form-label--inline">&nbsp;x&nbsp;</label>
                        <input name="crop[y]" type="number" placeholder="Y" class="Form-input--narrow ImageEditor-crop-input">
                        <br><br>
                        <input name="crop[width]" type="number" placeholder="Width" class="Form-input--narrow ImageEditor-crop-input">
                        <label class="Form-label Form-label--inline">&nbsp;x&nbsp;</label>
                        <input name="crop[height]" type="number" placeholder="Height" class="Form-input--narrow ImageEditor-crop-input">
                    </div>

                    <button
                       type="button" class="Accordion-section"
                       data-switch-to-tab="size">
                         <span class="Icon Icon-chevron-right Accordion-section-icon"></span>
                         <span class="Accordion-section-message">Size</span>
                    </button>
                    <div data-tab="size">
                        <label class="Form-label">Resize</label>
                        <input name="resize[width]" type="number" placeholder="Width" class="Form-input--narrow ImageEditor-resize-input">
                        <label class="Form-label Form-label--inline">&nbsp;x&nbsp;</label>
                        <input name="resize[height]" type="number" placeholder="Height" class="Form-input--narrow ImageEditor-resize-input">
                        <br><br>
                        <input type="hidden" name="resize[keepAspectRatio]" value="false">
                        <input type="checkbox" name="resize[keepAspectRatio]" value="true" id="resize[keepAspectRatio]" checked>
                        <label for="resize[keepAspectRatio]" class="Form-checkbox-label">Keep Aspect Ratio</label>
                        <br><br>
                        <label class="Form-label">Fit</label>
                        <input name="fit[width]" type="number" placeholder="Width" class="Form-input--narrow">
                        <label class="Form-label Form-label--inline">&nbsp;x&nbsp;</label>
                        <input name="fit[height]" type="number" placeholder="Height" class="Form-input--narrow">
                        <br><br>
                        <select name="fit[position]" id="flip[position]">
                            <option value="top-left">Top Left</option>
                            <option value="top">Top</option>
                            <option value="top-right">Top Right</option>
                            <option value="left">Left</option>
                            <option value="center" selected>Center</option>
                            <option value="right">Right</option>
                            <option value="bottom-left">Bottom Left</option>
                            <option value="bottom">Bottom</option>
                            <option value="bottom-right">Bottom Right</option>
                        </select>
                    </div>

                    <button
                       type="button" class="Accordion-section"
                       data-switch-to-tab="orientation">
                         <span class="Icon Icon-chevron-right Accordion-section-icon"></span>
                         <span class="Accordion-section-message">Orientation</span>
                    </button>
                    <div data-tab="orientation">
                        <label class="Form-label" for="flip">Flip</label>
                        <select name="flip" id="flip">
                            <option value="">None</option>
                            <option value="h">Horizontally</option>
                            <option value="v">Vertically</option>
                            <option value="hv">Both</option>
                        </select>
                        <label class="Form-label" for="rotate">Rotate</label>
                        <input name="rotate[angle]" type="number" placeholder="0" class="Form-input--narrow" min="0" max="360">
                        <br><br>
                        <input name="rotate[backgroundColor]" type="color" value="#ffffff">
                    </div>

                    <button
                       type="button" class="Accordion-section"
                       data-switch-to-tab="colours">
                         <span class="Icon Icon-chevron-right Accordion-section-icon"></span>
                         <span class="Accordion-section-message">Colours</span>
                    </button>
                    <div data-tab="colours">
                        <label class="Form-label">Colorize</label>
                        <input name="colorize[0]" type="number" placeholder="Red" class="Form-input--narrow" min="-100" max="100">
                        <input name="colorize[1]" type="number" placeholder="Green" class="Form-input--narrow" min="-100" max="100">
                        <input name="colorize[2]" type="number" placeholder="Blue" class="Form-input--narrow" min="-100" max="100">
                        <label class="Form-label" for="gamma">Gamma</label>
                        <input name="gamma" id="gamma" type="range" min="1" max="2" value="1" step="0.01">
                        <br><br>
                        <input type="hidden" name="greyscale" value="false">
                        <input type="checkbox" name="greyscale" value="true" id="greyscale">
                        <label for="greyscale" class="Form-checkbox-label">Greyscale</label>
                        <br><br>
                        <input type="hidden" name="invert" value="false">
                        <input type="checkbox" name="invert" value="true" id="invert">
                        <label for="invert" class="Form-checkbox-label">Invert</label>
                    </div>

                    <button
                       type="button" class="Accordion-section"
                       data-switch-to-tab="sharpness">
                         <span class="Icon Icon-chevron-right Accordion-section-icon"></span>
                         <span class="Accordion-section-message">Sharpness</span>
                    </button>
                    <div data-tab="sharpness">
                        <label class="Form-label" for="sharpen">Sharpen</label>
                        <input name="sharpen" id="sharpen" type="range" min="0" max="100" value="0">
                        <label class="Form-label" for="blur">Blur</label>
                        <input name="blur" id="blur" type="range" min="0" max="100" value="0">
                        <label class="Form-label" for="pixelate">Pixelate</label>
                        <input name="pixelate" id="pixelate" type="range" min="0" max="200" value="0">
                    </div>
                </form>
                <form
                    action="{{{ URL::route($blueprint->getRouteName('getRaw'), $item->getId()) }}}"
                    method="GET"
                    class="Form--singleColumn Form--warnBeforeExit Form--dark ImageEditor-form--advanced TabSwitcher-tabs TabSwitcher-content"
                    data-tab="advanced">
                    <?php
                        $codeEditor = new Editor('macro', "{\n\t\n}", Editor::TYPE_MINI, ['rows' => 10, 'class' => 'Editor--fullWidth'], [
                            'language' => 'json',
                            'mode' => 'code'
                        ]);

                        echo $codeEditor->render();
                    ?>
                </form>
            </div>

            <div class="Row--visual">
                <input type="text" name="name" class="Form-input--fullWidth" placeholder="Name" value="{{{ $item->getNewName() }}}"><br><br>
                <input type="text" name="slug" class="Form-input--fullWidth" placeholder="Name" value="{{{ $item->getNewSlug() }}}"><br><br>
                <div class="Form-footer">
                    <button type="button" class="Button Button-color--green ImageEditor-apply">Apply</button>
                    <button type="submit" class="Button Button-color--green Form-submit ImageEditor-save">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>

@stop
