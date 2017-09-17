function copyTable(button_obj, class_name)
{
    var el = $("." + class_name)[0];
    var display = $(el).css('display');
    $(el).css('display', 'table');
    // select
    var body = document.body, range, sel;
    if (document.createRange && window.getSelection) {
        range = document.createRange();
        sel = window.getSelection();
        sel.removeAllRanges();
        try {
            range.selectNodeContents(el);
            sel.addRange(range);
        } catch (e) {
            range.selectNode(el);
            sel.addRange(range);
        }
    } else if (body.createTextRange) {
        range = body.createTextRange();
        range.moveToElementText(el);
        range.select();
    }

    // copy
    document.execCommand('copy');

    // clear range
    if (window.getSelection) {
        if (window.getSelection().empty) {  // Chrome
            window.getSelection().empty();
        } else if (window.getSelection().removeAllRanges) {  // Firefox
            window.getSelection().removeAllRanges();
        }
    } else if (document.selection) {  // IE?
        document.selection.empty();
    }

    // some funny animation
    $(button_obj).text('Copied!');
    $(el).css('display', display);
    setTimeout(function()
    {
        $(button_obj).html('<i class="fa fa-clipboard"></i> Copy table content');
    }, 1000);
}

$(document).ready(function()
{
    var tables = 0;
    $("table").each(function() {
        $(this).addClass('copy_table_' + tables);
        $(this).parent().prepend('<button onclick="copyTable(this, \'copy_table_'+ tables +'\')" style="margin-bottom: 10px" class="btn btn-xs btn-info pull-right"><i class="fa fa-clipboard"></i> Copy table content</button>');
        tables++;
    });
});