
function enableEditables() {
    $('.editable')
        .attr('contenteditable', 'true')
        .on('blur.editable', function() {
            var content = $(this).html().trim();
            if (!content.length) {
                $(this).html('EDIT ME :(');
            }

            // Update other editables with the same ID to the same content
            // TODO: This updates this one as well, probably not the best idea.
            $('.editable[data-editable-id="' + $(this).data('editable-id') + '"]')
                .html($(this).html().trim());
        });

    // Disable anchors so clicking editable ones won't submit
    $('a').on('click.edit-mode', function(e) {
        e.preventDefault();
        return false;
    });
}

function disableEditables() {
    var payload = {};

    // Disable editables and compose a payload we can
    // fire off to persist the changes to the page 
    $('.editable').each(function() {
        $(this)
            .removeAttr('contenteditable')
            .off('blur.editable');

        payload[$(this).data('editable-id')] = $(this).html().trim();
    });

    // Re-enable anchors
    $('a').off('click.edit-mode');

    // Push it to the server
    $.post('/admin/update', payload);
}

$(function() {
    $('.collapsible').collapsible({
        accordion : false
    });

    $('.scrollspy').scrollSpy();
    
    $('.parallax').parallax();

    $('.modal-trigger').leanModal();
    $('.tooltipped').tooltip({delay: 0});

    $(".button-collapse").sideNav({
        menuWidth: 300,
        edge: 'left'
    });

    $('.toggle-editables').click(function() {
        if ($(this).hasClass('active')) {
            $(this).removeClass('active');
            $(this).find('i').html('mode_edit');
            disableEditables();
        } 
        else {
            $(this).addClass('active');
            $(this).find('i').html('save');
            enableEditables();
        }
        return false;
    });

    $('form.sign-up input').focus(function() {
        $(this).siblings('label').html('What\'s your primary email?');
        $(this).siblings('.signup').show();
    });

    $('form.sign-up input').blur(function() {
        $(this).siblings('label').html('Join our mailing list!');

        if (!$(this).siblings('label').hasClass('active')) {
            $(this).siblings('.signup').hide();
        }
    }); 

    $('form.sign-up').submit(function(e) {
        var $form = $(this);
        var email = $(this).find('input').val();

        if (email.length) {

            $.post('/subscribe', $form.serialize())
                .done(function() {
                    $form.find('.input-field').hide();
                    $form.find('.thanks').show();
                })
                .fail(function() {
                    alert('Some sort of error occurred. That\'s not good :(');
                });
        }
        
        e.preventDefault();
        return false;
    });

    // If we're on the admin page, initialise Quill editor for the newsletter
    if ($('#newsletter').length > 0) {
        var editor = new Quill('#newsletter-editor', {
            modules: {
                'toolbar': { container: '#newsletter-toolbar' },
                'link-tooltip': true
            },
            theme: 'snow'
        });

        /**
         * Submit a newsletter to be sent to the mailing list
         */
        $('#newsletter').submit(function(e) {
            
            var $button = $(this).find('button[type="submit"]');

            if (!$button.hasClass('disabled')) {
                if (editor.getText().trim().length < 1) {
                    alert('You must enter some content!');
                    e.preventDefault();
                    return false;
                }

                if ($('#newsletter-subject').val().length < 1) {
                    alert('You must specify a newsletter subject!');
                    e.preventDefault();
                    return false;
                }

                if (confirm('Are you sure you want to send this to all subscribers?')) {
                    // Clone HTML to a hidden input so we can submit it with the form
                    $('#newsletter-html').val(editor.getHTML());

                    $button
                        .addClass('disabled')
                        .html('<i class="material-icons">email</i> Sending...');

                    $.post('/admin/newsletter/send', 
                            $('#newsletter').serialize()
                        )
                        .fail(function() {
                            $button
                                .removeClass('disabled')
                                .html('<i class="material-icons">send</i> Send Newsletter');
                            alert('An error has occurred!');
                        })
                        .done(function() {
                            $button
                                .removeClass('disabled')
                                .html('<i class="material-icons">send</i> Send Newsletter');
                            editor.setHTML('');
                            $('#newsletter-html').val('');
                            $('#newsletter-subject').val('');
                            
                            alert('Newsletter has been sent!');
                        });
                }
            }

            e.preventDefault();
            return false;
        });

        /**
         * Save draft button for our newsletter editor
         */
        $('#newsletter .save').click(function(e) {
            var $button = $(this);

            if (!$button.hasClass('disabled')) {
                $button.addClass('disabled');

                // Clone HTML to a hidden input so we can submit it with the form
                $('#newsletter-html').val(editor.getHTML());

                $.post('/admin/newsletter/draft', 
                        $('#newsletter').serialize()
                    )
                    .fail(function() {
                        $button.removeClass('disabled');
                        alert('An error occurred while saving!');
                    })
                    .done(function() {
                        $button.removeClass('disabled');
                    });
            }

            e.preventDefault();
            return false;
        });

        /**
         * Clear draft button for our newsletter editor
         */
        $('#newsletter .clear').click(function(e) {
            if (confirm('Are you sure you want to clear the current draft?')) {
                editor.setHTML('');
                $('#newsletter-html').val('');
                $('#newsletter-subject').val('');

                $.post('/admin/newsletter/draft', 
                    $('#newsletter').serialize()
                );
                // Silently ignore errors in clearing the draft

                e.preventDefault();
                return false;
            }
        });
    }

    /**
     * Admin profile editor
     */        
    $('#profile').submit(function(e) {
        var $form = $(this);

        var $p1 = $form.find('#password');
        var $p2 = $form.find('#password2');

        // If there's a password change
        if ($p1.val().length > 0) {

            // Sanity check
            if ($p1.val() !== $p2.val()) {
                alert('Passwords must match!');
                e.preventDefault();
                return false;
            }

            // Post updates
            $.post('/admin/profile', $form.serialize())
                .fail(function() {
                    alert('An error has occurred!');
                })
                .done(function() {
                    alert('Profile updated!');

                    // Clear inputs
                    $p1.val('');
                    $p2.val('');
                });
        }

        e.preventDefault();
        return false;
    });
    
    if ($('#listserv').length > 0) {
        var listservTable = $('#listserv').DataTable({
            dom: 'rtip',
            responsive: true
        });

        $('#listserv-add').submit(function(e) {
            $.post('/subscribe', $(this).serialize())
                .fail(function() {
                    alert('Err!');
                })
                .done(function(response) {
                    // TODO: add to table?
                    //listservTable.row.add(response).draw(false);
                    alert('Added! (Note: They won\'t be in the table until you refresh)');
                });

            e.preventDefault();
            return false; 
        });

        $('#listserv').on('click', 'a.unsubscribe', function() {

            var $row = $(this).closest('tr');
            var email = $row.find('td.email').html();
            var uuid = $row.find('td.email').data('uuid');

            if (confirm('Are you sure you want to unsubscribe ' + email + '?')) {
                $.post('/unsubscribe/' + uuid)
                    .fail(function() {
                        alert('An error has occurred!');
                    })
                    .done(function() {
                        // Axe it from the datatable
                        listservTable.row($row).remove().draw();
                    });
            }

            return false; 
        });

        // Bind search box to update DataTables
        $('#listserv-search').keyup(function() {
            var dt = $('#listserv').DataTable();
            dt.search($(this).val()).draw();
        });
    }
});
