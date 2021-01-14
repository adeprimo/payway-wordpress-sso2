angular.module('tulo.admin', ['Tulo.Admin.ProductListController']);

(function ($) {

    $('.tulo_product_row').each(function(){
        bindproductactions(this);
    });
    

    function bindproductactions(row)
    {
        $(row).find('.delete').on('click', deleterow_clicked);
        $(row).find('.shortcodes button').on('click', shortcode_clicked);
        
    }

    function shortcode_clicked(e)
    {
        e.preventDefault();

        return false;
    }
    function deleterow_clicked(e)
    {
        e.preventDefault();
        $(this).closest('.tulo_product_row').remove();
        return false;
    }

    
    $.fn.insertAtCaret = function (inserttext, afterselection) {
        var element = $(this)[0];
        if (document.selection) {
            element.focus();
            var sel = document.selection.createRange();
            if (afterselection)
            {
                sel.text = inserttext + sel.text + afterselection;
            }
            else
                sel.text = inserttext;
            element.focus();
        } else if (element.selectionStart || element.selectionStart === 0) {
            var startPos = element.selectionStart;
            var endPos = element.selectionEnd;
            var scrollTop = element.scrollTop;
            if(afterselection)
                element.value = element.value.substring(0, startPos) + inserttext + element.value.substring(startPos, endPos) + afterselection + element.value.substring(endPos, element.value.length);
            else
                element.value = element.value.substring(0, startPos) + inserttext + element.value.substring(endPos, element.value.length);
            element.focus();
            element.selectionStart = startPos + inserttext.length;
            element.selectionEnd = startPos + inserttext.length;
            element.scrollTop = scrollTop;
        } else {
            element.value += inserttext + afterselection;
            element.focus();
        }
    };
    
})(jQuery);

