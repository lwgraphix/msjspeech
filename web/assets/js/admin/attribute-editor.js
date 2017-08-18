String.prototype.replaceAll = function(search, replacement) {
    var target = this;
    return target.replace(new RegExp(search, 'g'), replacement);
};

var fields = [];
var fields_count = fields.length;

function addNewField()
{
    var field_template = $("#input_template").html().replaceAll(/\$1/, fields_count);
    fields_count++;
    $("#fields").append(field_template);
}

function removeField(object)
{
    // check if persisted field
    $(object).parent().parent().parent().remove();
}

function saveFields()
{
    var data = $("#attr_0").serializeArray();
    console.log(data);
    console.log(_serializeToObject(data));
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
    else if (value == 1)
    {
        $("#attr_"+ attr_id +"_text").hide();
        $("#attr_"+ attr_id +"_dropdown").show();
    }
    else if (value == 2)
    {
        $("#attr_"+ attr_id +"_dropdown").hide();
        $("#attr_"+ attr_id +"_text").hide();
    }
}