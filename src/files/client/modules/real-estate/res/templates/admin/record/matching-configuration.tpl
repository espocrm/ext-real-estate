
<div class="button-container">
    <div class="btn-group">
        <button type="button" class="btn btn-primary" data-action="save">{{translate 'Save'}}</button>
        <button type="button" class="btn btn-default" data-action="cancel">{{translate 'Cancel'}}</button>
    </div>
</div>
<div class="panel panel-default">
    <div class="panel-heading"><h4 class="panel-title">{{translate 'typeFieldsMap' category='messages' scope='RealEstateMatchingConfiguration'}}</h4></div>
    <div class="panel-body panel-body-form">
        {{#each typeDataList}}
        <div class="row">
            <div class="cell col-md-12 form-group" data-name="{{fieldName}}">
                <label class="control-label">{{labelText}}</label>
                <div class="field" data-name="{{fieldName}}">{{{var fieldKey ../this}}}</div>
            </div>
        </div>
        {{/each}}
    </div>
</div>
