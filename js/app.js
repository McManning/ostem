
function submitEmail($container) {

    // Fake submission logic. TODO: Actually submit! 
    $container
        .removeClass('show-top')
        .addClass('show-right')
        .delay(2000)
        .queue(function(next) {
            $(this).removeClass('show-right')
                .addClass('show-bottom')
                .delay(2000)
                .queue(function(next) {
                    $(this).removeClass('show-bottom')
                        .addClass('show-front');

                    next();
                });
            next();
        });
}

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
}

function disableEditables() {
    var payload = {};

    // Disable editables and compose a payload we can
    // fire off to persist the changes to the page 
    $('.editable').each(function() {
        $(this)
            .removeAttr('contenteditable')
            .off('blur.editable');

        payload[$(this).data('editable-id')] = $(this).html();
    });

    // TODO: Some AJAX!
    console.log(payload);
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

    $('.sign-up-box input').keydown(function(e) {
        if (e.which === 13) {
            submitEmail($(this).closest('.sign-up-box'));
        }
    });

    $('.sign-up-box button').click(function() {
        submitEmail($(this).closest('.sign-up-box'));
    })

    $('.front').click(function() {
        $(this).closest('.sign-up-box')
            .removeClass('show-front')
            .addClass('show-top')
            .find('input')
                .focus();
    });

    $('.toggle-editables').click(function() {
        if ($(this).hasClass('active')) {
            $(this).removeClass('active');
            disableEditables();
        } 
        else {
            $(this).addClass('active');
            enableEditables();
        }
        return false;
    });

    $('input').focus(function() {
        var $parent = $(this).parent();

        $parent.find('label').html('What\'s your primary email?');
        $parent.find('.signup').show();
    });

    $('.sign-up input').blur(function() {
        var $parent = $(this).parent();

        $parent.find('label').html('Join our mailing list!');

        if (!$parent.find('label').hasClass('active')) {
            $parent.find('.signup').hide();
        }
    }); 

    $('form.sign-up').submit(function(e) {
        var email = $(this).find('input').val();

        if (email.length) {
            $(this).find('.input-field').hide();
            $(this).find('.thanks').show();
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


