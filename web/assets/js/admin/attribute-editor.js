String.prototype.replaceAll = function(search, replacement) {
    var target = this;
    return target.replace(new RegExp(search, 'g'), replacement);
};

function addNewField()
{
    var field_template = $("#input_template").html().replaceAll(/\$1/, fields_count);
    $("#fields").append(field_template);
    $('html, body').animate({
        scrollTop: $("#attr_" + fields_count).offset().top - 25
    }, 1000);
    fields_count++;
}

function removeField(object)
{
    // check if persisted field
    $(object).parent().parent().parent().remove();
}

function removePersistedField(delete_url)
{
    $.post(delete_url, {id: window.delete_attribute_id}, function(response)
    {
        $("#delete-attribute-confirm").modal('hide');
        removeField(window.delete_attribute_object);
    });

}

function removeConfirmField(id, object)
{
    window.delete_attribute_id = id;
    window.delete_attribute_object = object;
    $("#delete-attribute-confirm").modal('show');
}

function saveFields()
{
    var fields = [];
    $("#fields").children().each(function() {
        var id = $(this).data('id');
        var data = _serializeToObject($(this).serializeArray());
        data["id"] = id;
        fields.push(data);
    });

    $.post(window.location, {data: fields}, function(response) {
        window.location = window.location; // refresh page scroll
    });
}

function _serializeToObject(data)
{
    var object = {};
    data.forEach(function(item) {
        if (item.name == 'dropdown_item' && object[item.name] === undefined)
        {
            object[item.name] = [];
            object[item.name].push(item.value);
        }
        else
        {
            if (item.name == 'dropdown_item')
            {
                object[item.name].push(item.value);
            }
            else
            {
                object[item.name] = item.value;
            }
        }
    });
    return object;
}

function _addDropdownItem(attr_id)
{
    var template = '<div class="form-group form-inline"><input type="text" name="dropdown_item" class="form-control" style="width:300px; margin-right: 10px"><button onclick="_removeDropdownItem(this)" class="btn btn-danger"><i class="fa fa-remove"></i></button></div>';
    $("#attr_"+ attr_id +"_dropdown_items").append(template);
}

function _removeDropdownItem(object)
{
    $(object).parent().remove();
}

function _showFields(attr_id, value)
{
    if (value == 0)
    {
        $("#attr_"+ attr_id +"_dropdown").hide();
        $("#attr_"+ attr_id +"_text").show();
    }
    else if (value == 1 || value == 2)
    {
        $("#attr_"+ attr_id +"_text").hide();
        $("#attr_"+ attr_id +"_dropdown").show();
    }
    else if (value == 3)
    {
        $("#attr_"+ attr_id +"_dropdown").hide();
        $("#attr_"+ attr_id +"_text").hide();
    }
}