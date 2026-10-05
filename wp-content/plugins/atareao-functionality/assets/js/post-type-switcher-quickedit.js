/* global inlineEditPost, jQuery */
/**
 * Post Type Switcher — edición rápida y masiva (JS nativo, sin build tools).
 *
 * Replica fiel del `assets/js/quickedit.js` original: mueve las cajas del
 * selector de tipo a la fila de edición rápida / masiva y parchea
 * `inlineEditPost.edit` para preseleccionar el tipo actual leído de
 * `data-post-type` (pintado por PHP en la columna `post_type`).
 */
function pts_quick_edit() {
    var $ = jQuery;
    var _edit = inlineEditPost.edit;

    $('#bulk-edit')
        .find('.inline-edit-col-right:first .inline-edit-col')
        .append($('#bulk-edit #pts_bulk_edit'));

    $('.inline-edit-row')
        .not('#bulk-edit')
        .find('.inline-edit-col-right:first .inline-edit-col')
        .append($('.inline-edit-row #pts_quick_edit'));

    inlineEditPost.edit = function (id) {
        var args = [].slice.call(arguments);

        _edit.apply(this, args);

        if (typeof id === 'object') {
            id = this.getId(id);
        }

        var
            // edit_row is the quick-edit row, containing the inputs that need to be updated
            edit_row = $('#edit-' + id),

            // post_row is the row shown when a post isn't being edited, which also holds the existing values.
            post_row = $('#post-' + id),

            // get the existing values
            post_type = $('td.post_type span', post_row).data('post-type');

        // set the values in the quick-editor
        $(
            'select[name="pts_post_type"] option[value="' + post_type + '"]',
            edit_row
        ).attr('selected', 'selected');
    };
}

// Another way of ensuring inlineEditPost.edit isn't patched until it's defined
if (typeof inlineEditPost !== 'undefined' && inlineEditPost) {
    pts_quick_edit();
} else {
    jQuery(pts_quick_edit);
}
