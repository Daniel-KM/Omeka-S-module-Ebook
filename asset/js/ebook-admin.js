// Kept as long as pull request #1260 is not passed.
Omeka.ebookManageSelectedActions = function() {
    var selectedOptions = $('[value="update-selected"], [value="delete-selected"], #batch-form .batch-inputs .batch-selected');
    if ($('.batch-edit td input[type="checkbox"]:checked').length > 0) {
        selectedOptions.removeAttr('disabled');
    } else {
        selectedOptions.attr('disabled', true);
        $('.batch-actions-select').val('default');
        $('.batch-actions .active').removeClass('active');
        $('.batch-actions .default').addClass('active');
    }
};

(function($, window, document) {
    $(function() {

        var batchSelect = $('#batch-form .batch-actions-select');
        batchSelect.append(
            $('<option class="batch-selected" disabled="disabled"></option>').val('ebook-selected').html(Omeka.jsTranslate('Create ebook with selected'))
        );
        batchSelect.append(
            $('<option></option>').val('ebook-all').html(Omeka.jsTranslate('Create ebook with all'))
        );
        var batchActions = $('#batch-form .batch-actions');
        batchActions.append(
            $('<input type="submit" class="ebook-selected" formaction="ebook">').val(Omeka.jsTranslate('Go'))
        );
        batchActions.append(
            $('<input type="submit" class="ebook-all" formaction="ebook">').val(Omeka.jsTranslate('Go'))
        );
        var resourceType = window.location.pathname.split('/').pop();
        batchActions.append(
            $('<input type="hidden" name="resource_type">').val(resourceType)
        );

        // Kept as long as pull request #1260 is not passed.
        $('.select-all').change(function() {
            Omeka.ebookManageSelectedActions();
        });
        $('.batch-edit td input[type="checkbox"]').change(function() {
            Omeka.ebookManageSelectedActions();
        });

        // For the page admin site navigation.
        $('body.sites.edit #page-actions').prepend(
            $('<a class="button"></a>')
                .prop('href', window.location.pathname.substr(0, window.location.pathname.lastIndexOf('/')) + '/ebook')
                .html(Omeka.jsTranslate('Create ebook'))
        );
    });
}(window.jQuery, window, document));
