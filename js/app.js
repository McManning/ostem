
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
    $.post('/update', payload);
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
});

/* TODO: Let them browse old emails?
function addNews() {
    var typeIcon = 'email';
    var title = 'New Message';
    var date = '9/14/2015';
    var message = '<p>oSTEM is heading out to <strong>Kingmakers</strong>, the game parlour. Kingsmakers has hundreds of board and card games that we can play, but it <strong>costs $5 for the night.</strong></p>'+
        '<p>We\'ll meet at <strong>5:30PM</strong> at the <strong>Brutus status in the Ohio Union.</strong> From there, we\'ll head out for dinner before going to Kingsmakers.</p>'+
        '<strong>Other News</strong>'+
        '<p>Results from the poll are in and the majority of people said that they prefer Wednesday meetings. So our meeting time next semester will continue to be on Wednesdays.</p>';

    var id = $('#news').children('li').length + 1;

    var $li = $('<li></li>')
                .addClass('collection-item')
                .addClass('avatar')
                .html(
                    '<i class="material-icons circle green">' + typeIcon + '</i>'+
                    '<span class="title truncate">' + title + '</span>'+
                    '<p>Sent on <strong>' + date + '</strong></p>'+
                    '<a href="#news-modal-' + id + '" class="secondary-content modal-trigger">READ MORE</a>'+
                    '<div id="news-modal-' + id + '" class="modal">'+
                    '    <div class="modal-content">'+
                    '        <span class="right modal-close"><i class="material-icons">close</i></span>'+
                    '        <h4>' + title + '</h4>'+ message +
                    '    </div>'+
                    '</div>'
                )
                .hide();

    $('#news').append($li);

    $li.slideDown();

    // Initialise modal plugin on the new element
    $('a[href="#news-modal-' + id + '"]').leanModal();
}*/


